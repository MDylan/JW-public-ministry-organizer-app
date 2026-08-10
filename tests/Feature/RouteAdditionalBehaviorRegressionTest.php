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
        // TODO 33.3 re-pointed this test from the removed vendor UI
        // (languages.*) to the in-house editor. The three assertions are the
        // user's stated hard requirement and are unchanged: a plain user is
        // refused, a translator is sent to password confirmation, and a
        // translator with a confirmed password gets the page.
        //
        // The editor is in fact reached through a STRICTER chain than the
        // vendor routes were: admin.translate also carries `verified` and
        // `profileFull`, which the package's own route group did not. The two
        // hardcoded /languages links that used to sit on the settings and
        // translation screens bypassed exactly those two gates.
        $user = $this->createUser(['role' => 'registered', 'email' => 'translator-gate@example.test']);

        $this->actingAs($user)
            ->get(route('admin.translate'))
            ->assertForbidden();

        $user->role = 'translator';
        $user->save();
        $user = $user->fresh();

        $this->actingAs($user)
            ->get(route('admin.translate'))
            ->assertRedirect(route('password.confirm'));

        $this->actingAs($user)
            ->withSession($this->passwordConfirmedSession())
            ->get(route('admin.translate'))
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

    public function test_gdpr_download_requires_auth_and_the_current_password(): void
    {
        // TODO 33.2 narrowed this test to the one GDPR route that survives.
        // The three consent routes it also exercised (gdpr-terms and its two
        // POSTs) went with the package: the page had always returned a 500 - a
        // published view extending a layout this project does not have - and
        // nothing ever linked to it.
        //
        // What is still worth pinning here is that the route contract did NOT
        // change when the registration moved out of the vendor service
        // provider and into routes/web.php: same name, same URI, still behind
        // web + auth.
        $user = $this->createUser([
            'email' => 'gdpr-user@example.test',
            'password' => bcrypt('password'),
        ]);

        $this->post(route('gdpr-download'), ['password' => 'password'])
            ->assertRedirect(route('login'));

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
