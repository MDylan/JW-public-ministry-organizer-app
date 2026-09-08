<?php

namespace Tests\Feature;

use App\Models\StaticPage;
use PHPUnit\Framework\Attributes\DataProvider;

class LivewireRouteMountedComponentsTest extends FeatureTestCase
{
    #[DataProvider('mountedRouteProvider')]
    public function test_route_mounted_livewire_components_boot_successfully(
        string $routeName,
        string $actor,
        bool $needsPasswordConfirm,
        bool $needsGroup,
        bool $needsNews,
        bool $needsStaticPage
    ): void {
        $baseUser = $this->createUser(['email' => 'base@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($baseUser, $group, 'member', true);

        $groupAdmin = $this->createUser(['email' => 'group-admin@example.test']);
        $this->attachUserToGroup($groupAdmin, $group, 'roler', true);

        $mainAdmin = $this->createUser([
            'role' => 'mainAdmin',
            'email' => 'main-admin@example.test',
        ]);

        $translator = $this->createUser([
            'role' => 'translator',
            'email' => 'translator@example.test',
        ]);

        $news = null;
        if ($needsNews) {
            $this->actingAs($groupAdmin);
            $news = $this->createGroupNews($group, $groupAdmin);
        }
        $staticPage = $needsStaticPage
            ? StaticPage::factory()->create(['user_id' => $mainAdmin->id])
            : null;

        $user = match ($actor) {
            'member' => $baseUser,
            'group_admin' => $groupAdmin,
            'admin' => $mainAdmin,
            'translator' => $translator,
            'group_servant' => $groupAdmin,
            default => $baseUser,
        };

        $parameters = [];
        if ($needsGroup) {
            $parameters['group'] = $group->id;
        }
        if ($news !== null) {
            $parameters['new'] = $news->id;
        }
        if ($staticPage !== null) {
            $parameters['staticPage'] = $staticPage->id;
        }

        $request = $this->actingAs($user);
        if ($needsPasswordConfirm) {
            $request = $request->withSession($this->passwordConfirmedSession());
        }

        $request->get(route($routeName, $parameters))->assertStatus(200);
    }

    public static function mountedRouteProvider(): array
    {
        return [
            'home' => ['home.home', 'member', false, false, false, false],
            'events calendar' => ['calendar', 'member', false, false, false, false],
            'last events' => ['lastevents', 'member', false, false, false, false],
            'groups list' => ['groups', 'member', false, false, false, false],
            'newsletters group servant' => ['newsletters', 'group_servant', false, false, false, false],

            'admin users' => ['admin.users', 'admin', true, false, false, false],
            'admin settings' => ['admin.settings', 'admin', true, false, false, false],
            'admin staticpages' => ['admin.staticpages', 'admin', true, false, false, false],
            'admin static page create' => ['admin.staticpages_create', 'admin', true, false, false, false],
            'admin static page edit' => ['admin.staticpages_edit', 'admin', true, false, false, true],
            'admin newsletter edit' => ['admin.newsletter_edit', 'admin', true, false, false, false],
            'admin statistics' => ['admin.statistics', 'admin', false, false, false, false],
            'admin translation' => ['admin.translate', 'translator', true, false, false, false],

            'groups users' => ['groups.users', 'member', false, true, false, false],
            'groups news list' => ['groups.news', 'member', false, true, false, false],
            'groups update form' => ['groups.edit', 'group_admin', true, true, false, false],
            'groups delete' => ['groups.delete', 'group_admin', true, true, false, false],
            'groups news create' => ['groups.news_create', 'group_admin', false, true, false, false],
            'groups news edit' => ['groups.news_edit', 'group_admin', false, true, true, false],
            'groups statistics' => ['groups.statistics', 'group_admin', false, true, false, false],
            'groups history' => ['groups.history', 'group_admin', false, true, false, false],
        ];
    }
}
