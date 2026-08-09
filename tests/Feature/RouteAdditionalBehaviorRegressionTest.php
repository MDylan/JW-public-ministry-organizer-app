<?php

namespace Tests\Feature;

use App\Models\GroupNewsFile;
use Illuminate\Support\Facades\Storage;

class RouteAdditionalBehaviorRegressionTest extends FeatureTestCase
{
    public function test_signed_routes_reject_unsigned_requests(): void
    {
        $registered = $this->createUser([
            'email' => 'finish-cancel@example.test',
            'role' => 'registered',
            'email_verified_at' => null,
            'name' => null,
        ]);

        // A `cancel` a v1-patch H óta POST: az aláírás önmagában azt igazolja,
        // hogy a linket mi adtuk ki, azt nem, hogy a felhasználó szándékosan
        // nyitotta meg - egy adatot törlő GET-et böngészőelőtöltés is elsüthet.
        $this->post(route('finish_registration_cancel', ['id' => $registered->id]))->assertForbidden();

        // Az `admin.loginback` KORÁBBAN itt szerepelt, aláírt GET-ként. A
        // visszaút azóta nem URL-ből, hanem szerveroldali sessionből dolgozik,
        // ezért az aláírás-ellenőrzés helyét a session-kötés vette át; azt a
        // Tests\Feature\Auth\ImpersonationTest fedi.

        $user = $this->createUser(['email' => 'signed-delete@example.test']);
        $this->actingAs($user)
            ->get(route('user.deletepersonaldata', ['id' => $user->id]));

        $this->assertSame(0, (int) $user->fresh()->isAnonymized);

        $signedDelete = $this->signedRoute('user.deletepersonaldata', ['id' => $user->id]);
        $this->actingAs($user)
            ->get($signedDelete)
            ->assertRedirect('login');

        $this->assertSame(1, (int) $user->fresh()->isAnonymized);
    }

    public function test_translator_routes_require_translator_role_and_password_confirmation(): void
    {
        $user = $this->createUser(['role' => 'registered', 'email' => 'translator-gate@example.test']);

        $this->actingAs($user)
            ->get(route('languages.index'))
            ->assertForbidden();

        $user->role = 'translator';
        $user->save();
        $user = $user->fresh();

        $this->actingAs($user)
            ->get(route('languages.index'))
            ->assertRedirect(route('password.confirm'));

        $this->actingAs($user)
            ->withSession($this->passwordConfirmedSession())
            ->get(route('languages.index'))
            ->assertStatus(200);

        $this->actingAs($user)
            ->withSession($this->passwordConfirmedSession())
            ->get(route('languages.create'))
            ->assertStatus(200);

        $this->actingAs($user)
            ->withSession($this->passwordConfirmedSession())
            ->get(route('languages.translations.index', ['language' => config('app.locale', 'hu')]))
            ->assertStatus(200);
    }

    public function test_group_member_and_admin_routes_cover_file_download_logout_and_news_delete(): void
    {
        Storage::fake('news_files');

        $group = $this->createGroup();
        $admin = $this->createUser(['email' => 'group-admin-extra@example.test']);
        $member = $this->createUser(['email' => 'group-member-extra@example.test']);
        $outsider = $this->createUser(['email' => 'group-outsider-extra@example.test']);

        $this->attachUserToGroup($admin, $group, 'roler', true);
        $this->attachUserToGroup($member, $group, 'member', true);

        $this->actingAs($admin);
        $news = $this->createGroupNews($group, $admin);
        $newsFile = GroupNewsFile::create([
            'group_new_id' => $news->id,
            'name' => 'document.txt',
            'file' => 'document.txt',
        ]);
        Storage::disk('news_files')->put('document.txt', 'test');

        $this->actingAs($outsider)
            ->get(route('groups.news.filedownload', ['group' => $group->id, 'file' => $newsFile->id]))
            ->assertForbidden();

        $memberDownload = $this->actingAs($member)
            ->get(route('groups.news.filedownload', ['group' => $group->id, 'file' => $newsFile->id]));

        $this->assertContains($memberDownload->getStatusCode(), [200, 302]);

        $logoutResponse = $this->actingAs($member)
            ->withSession($this->passwordConfirmedSession())
            ->get(route('groups.logout', ['group' => $group->id]));

        $logoutResponse->assertStatus(302);
        $this->assertSoftDeleted('group_user', [
            'user_id' => $member->id,
            'group_id' => $group->id,
        ]);

        $this->attachUserToGroup($member, $group, 'member', true);

        $this->actingAs($member)
            ->withSession($this->passwordConfirmedSession())
            ->get(route('groups.news_delete', ['group' => $group->id, 'new' => $news->id]))
            ->assertForbidden();

        $this->flushSession();
        $admin = $admin->fresh();

        $adminDeleteResponse = $this->actingAs($admin)
            ->withSession(array_merge(
                $this->passwordConfirmedSession(),
                ['password_hash_web' => $admin->password]
            ))
            ->get(route('groups.news_delete', ['group' => $group->id, 'new' => $news->id]));

        $adminDeleteResponse->assertStatus(302);
        $this->assertStringContainsString(
            route('groups.news', ['group' => $group->id], false),
            $adminDeleteResponse->headers->get('Location', '')
        );

        $this->assertSoftDeleted('group_news', ['id' => $news->id]);
    }

    public function test_gdpr_routes_require_auth_and_process_acceptance_download_flow(): void
    {
        $user = $this->createUser([
            'email' => 'gdpr-user@example.test',
            'password' => bcrypt('password'),
            'accepted_gdpr' => null,
        ]);

        $this->get(route('gdpr-terms'))->assertRedirect(route('login'));

        // TODO 12: itt korábban assertContains($status, [200, 500]) állt, ami
        // az 500-at is elfogadta - és pontosan azt takarta el, hogy a
        // gdpr-terms oldal TÉNYLEG elszáll (hiányzó 'base' layout). A törött
        // oldalt most a Gdpr\ConsentTermsTest méri, névvel és indoklással;
        // itt csak a route-szerződés marad.

        $this->actingAs($user)
            ->post(route('gdpr-terms-accepted'))
            ->assertRedirect('/');

        $this->assertTrue((bool) $user->fresh()->accepted_gdpr);

        $this->actingAs($user)
            ->post(route('gdpr-terms-denied'))
            ->assertRedirect('/');

        $this->assertFalse((bool) $user->fresh()->accepted_gdpr);

        $this->actingAs($user)
            ->post(route('gdpr-download'), ['password' => 'wrong-password'])
            ->assertStatus(403);

        $downloadResponse = $this->actingAs($user)
            ->post(route('gdpr-download'), ['password' => 'password']);

        $downloadResponse->assertStatus(200);
        $this->assertStringContainsString(
            'attachment; filename="user.json"',
            $downloadResponse->headers->get('Content-Disposition', '')
        );
    }
}
