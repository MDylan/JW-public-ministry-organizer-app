<?php

namespace Tests\Feature\Gdpr;

use App\Models\User;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 12: az adathordozhatóság (GDPR 20. cikk).
 *
 * A lánc: Portable::portable() -> loadMissing($gdprWith) -> setHidden($gdprHidden)
 * -> User::toPortableArray() (:258).
 *
 * Eddig egyetlen teszt érintette, a RouteAdditionalBehaviorRegressionTest:141 -
 * az viszont assertContains($status, [200, 500])-tel az 500-at is elfogadta, és
 * a letöltött tartalomból semmit nem állított. Vagyis a védőháló látszata volt,
 * nem védőháló.
 */
class DataExportTest extends FeatureTestCase
{
    private function exportableUser(): User
    {
        // fresh(): a create() csak a megadott mezőket tölti a példányba, az
        // adatbázis-alapértelmezéssel született oszlopokat (pl. isAnonymized)
        // nem. A GdprController a session-ből betöltött, teljes modellel
        // dolgozik, tehát az exportnak is azon kell mérődnie.
        return $this->createUser([
            'email' => 'export@example.test',
            'password' => bcrypt('titkos-jelszo'),
            'name' => 'Exportált Név',
            'phone_number' => '+36301112233',
            'congregation' => 'Gyülekezet',
        ])->fresh();
    }

    // =========================================================================
    // 1. Mi marad ki, és mi kerül bele
    // =========================================================================

    public function test_every_declared_hidden_field_is_absent(): void
    {
        $export = $this->exportableUser()->portable();

        foreach ([
            'id',
            'role',
            'password',
            'two_factor_secret',
            'two_factor_recovery_codes',
            'remember_token',
            'accepted_gdpr',
            'show_fields',
            'opted_out_of_notifications',
        ] as $field) {
            $this->assertArrayNotHasKey($field, $export, "A(z) {$field} nem kerülhet az exportba.");
        }
    }

    public function test_the_export_reveals_fields_the_normal_api_hides(): void
    {
        // A setHidden() FELÜLÍRJA a modell $hidden listáját, nem kiegészíti.
        // Amit a $hidden rejt, de a $gdprHidden nem sorol fel, az az exportban
        // láthatóvá válik - erre a két lista összevetéséből nem lehet
        // rájönni, csak a trait olvasásából.
        $export = $this->exportableUser()->portable();

        foreach (['language', 'created_at', 'updated_at', 'isAnonymized'] as $field) {
            $this->assertArrayHasKey(
                $field,
                $export,
                "A(z) {$field} a normál API-n rejtett, az exportban mégis benne van."
            );
        }
    }

    public function test_the_encrypted_columns_are_exported_in_clear_text(): void
    {
        // A name, phone_number és congregation encrypted cast - az exportban
        // dekódolva jelennek meg. GDPR szempontból ez a helyes viselkedés (az
        // érintett a saját adatát kapja meg), de sehol nem volt leírva.
        // Kapcsolódik: TODO 13.
        $export = $this->exportableUser()->portable();

        $this->assertSame('Exportált Név', $export['name']);
        $this->assertSame('+36301112233', $export['phone_number']);
        $this->assertSame('Gyülekezet', $export['congregation']);
    }

    // =========================================================================
    // 2. A relációk átalakítása
    // =========================================================================

    public function test_events_lose_the_seven_stripped_fields_but_keep_the_rest(): void
    {
        $user = $this->exportableUser();
        $this->actingAs($user);

        $group = $this->createGroup(['name' => 'Export csoport']);
        $this->attachUserToGroup($user, $group, 'member');

        $date = now()->addDay()->toDateString();
        $this->createEventDate($group, $date);
        $event = $this->createEventInRange($group, $user, $date, '08:00', '09:00');
        $event->update(['comment' => 'Megjegyzés']);

        $export = $user->fresh()->portable();

        $this->assertArrayHasKey('events_only', $export);
        $this->assertCount(1, $export['events_only']);

        $exported = $export['events_only'][0];

        foreach (['id', 'group_id', 'user_id', 'accepted_by', 'accepted_at', 'start', 'end'] as $field) {
            $this->assertArrayNotHasKey($field, $exported);
        }

        $this->assertSame('Megjegyzés', $exported['comment']);
        $this->assertArrayHasKey('day', $exported);
    }

    public function test_groups_are_reduced_to_a_name_and_a_join_date(): void
    {
        // A csoportokból CSAK a név és a csatlakozás dátuma marad - a szerep,
        // a jegyzet és minden más pivot-adat kimarad az exportból.
        $user = $this->exportableUser();
        $group = $this->createGroup(['name' => 'Export csoport']);
        $this->attachUserToGroup($user, $group, 'admin');

        $export = $user->fresh()->portable();

        $this->assertArrayHasKey('groups', $export);
        $this->assertArrayNotHasKey('groups_accepted', $export, 'A kulcs átnevezésre kerül.');
        $this->assertCount(1, $export['groups']);
        $this->assertSame('Export csoport', $export['groups'][0]['name']);
        $this->assertNotNull($export['groups'][0]['accepted_at']);
        $this->assertArrayNotHasKey('group_role', $export['groups'][0]);
    }

    public function test_the_export_shape_changes_when_the_user_has_no_groups(): void
    {
        // KARAKTERIZÁLÁS: az átnevezés csak nem üres listánál fut le
        // (:280 count() > 0), tehát csoport nélküli felhasználónál a kulcs
        // 'groups_accepted' marad. Az export sémája így adatfüggő - egy
        // feldolgozó szkriptnek mindkét nevet ismernie kell.
        $export = $this->exportableUser()->portable();

        $this->assertArrayHasKey('groups_accepted', $export);
        $this->assertArrayNotHasKey('groups', $export);
        $this->assertSame([], $export['groups_accepted']);
    }

    // =========================================================================
    // 3. A HTTP-ág
    // =========================================================================

    public function test_a_wrong_password_is_rejected_with_403(): void
    {
        // A GdprController Auth::attempt()-tel ellenőriz újra, tehát a
        // letöltéshez a jelszó ismerete kell, nem csak az élő munkamenet.
        $user = $this->exportableUser();

        $this->actingAs($user)
            ->post(route('gdpr-download'), ['password' => 'rossz-jelszo'])
            ->assertForbidden();
    }

    public function test_a_missing_password_fails_validation_rather_than_authorization(): void
    {
        // Két különböző elutasítás, más státusszal: a hiányzó jelszót a
        // GdprDownload FormRequest fogja meg (302 + hibaüzenet), a rosszat az
        // abort_unless (403). Ez a különbség a TODO 16 szempontjából számít -
        // a validáció a csomagból jön, az őr a kontrollerből.
        $user = $this->exportableUser();

        $this->actingAs($user)
            ->post(route('gdpr-download'), [])
            ->assertStatus(302)
            ->assertSessionHasErrors('password');
    }

    public function test_the_download_returns_the_portable_array_as_a_json_attachment(): void
    {
        $user = $this->exportableUser();

        $response = $this->actingAs($user)
            ->post(route('gdpr-download'), ['password' => 'titkos-jelszo']);

        $response->assertStatus(200);
        $this->assertSame(
            'attachment; filename="user.json"',
            $response->headers->get('Content-Disposition')
        );

        $payload = $response->json();

        $this->assertSame('export@example.test', $payload['email']);
        $this->assertSame('Exportált Név', $payload['name']);
        $this->assertArrayNotHasKey('password', $payload);
        $this->assertArrayNotHasKey('id', $payload);
    }

    public function test_a_guest_cannot_reach_the_download(): void
    {
        $this->post(route('gdpr-download'), ['password' => 'titkos-jelszo'])
            ->assertRedirect(route('login'));
    }
}
