<?php

namespace Tests\Feature\Gdpr;

use App\Models\User;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 12: data portability (GDPR Article 20).
 *
 * The chain: Portable::portable() -> loadMissing($gdprWith) -> setHidden($gdprHidden)
 * -> User::toPortableArray() (:258).
 *
 * Until now only one test touched this, RouteAdditionalBehaviorRegressionTest:141 -
 * but it accepted 500 too via assertContains($status, [200, 500]), and it asserted
 * nothing about the downloaded content. In other words, it was the appearance of a
 * safety net, not a safety net.
 */
class DataExportTest extends FeatureTestCase
{
    private function exportableUser(): User
    {
        // fresh(): create() only populates the instance with the given fields, not
        // the columns that come from database defaults (e.g. isAnonymized). The
        // GdprController works with the full model loaded from the session, so the
        // export must be measured against that too.
        return $this->createUser([
            'email' => 'export@example.test',
            'password' => bcrypt('titkos-jelszo'),
            'name' => 'Exportált Név',
            'phone_number' => '+36301112233',
            'congregation' => 'Gyülekezet',
        ])->fresh();
    }

    // =========================================================================
    // 1. What is left out, and what is included
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
        // setHidden() OVERWRITES the model's $hidden list, it does not merge with it.
        // Whatever $hidden conceals but $gdprHidden does not list becomes visible in
        // the export - this cannot be figured out by comparing the two lists, only
        // by reading the trait.
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
        // name, phone_number and congregation have an encrypted cast - they appear
        // decoded in the export. From a GDPR standpoint this is the correct behavior
        // (the data subject receives their own data), but it was documented nowhere.
        // Related: TODO 13.
        $export = $this->exportableUser()->portable();

        $this->assertSame('Exportált Név', $export['name']);
        $this->assertSame('+36301112233', $export['phone_number']);
        $this->assertSame('Gyülekezet', $export['congregation']);
    }

    // =========================================================================
    // 2. Transformation of the relations
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
        // From the groups ONLY the name and the join date remain - the role,
        // the note and all other pivot data are left out of the export.
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
        // CHARACTERIZATION: the renaming only runs for a non-empty list
        // (:280 count() > 0), so for a user with no groups the key
        // 'groups_accepted' remains. The export's shape is thus data-dependent - a
        // consuming script must know both names.
        $export = $this->exportableUser()->portable();

        $this->assertArrayHasKey('groups_accepted', $export);
        $this->assertArrayNotHasKey('groups', $export);
        $this->assertSame([], $export['groups_accepted']);
    }

    // =========================================================================
    // 3. The HTTP branch
    // =========================================================================

    public function test_a_wrong_password_is_rejected_with_403(): void
    {
        // The GdprController re-verifies with Auth::attempt(), so the download
        // requires knowing the password, not just an active session.
        $user = $this->exportableUser();

        $this->actingAs($user)
            ->post(route('gdpr-download'), ['password' => 'rossz-jelszo'])
            ->assertForbidden();
    }

    public function test_a_missing_password_fails_validation_rather_than_authorization(): void
    {
        // Two different rejections, with different status codes: the missing
        // password is caught by the GdprDownload FormRequest (302 + error message),
        // the wrong one by abort_unless (403). This difference matters for TODO 16 -
        // the validation comes from the package, the guard from the controller.
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
