<?php

namespace Tests\Feature\Middleware;

use App\Http\Middleware\GroupAdmin;
use App\Http\Middleware\GroupMember;
use App\Models\Group;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 43.2: groupMember and groupAdmin must accept {group} in both shapes.
 *
 * Livewire 3 registers Router::substituteImplicitBindingsUsing(), so on a route
 * whose action is a page component the SubstituteBindings middleware resolves
 * the component's mount() signature before route middleware runs. {group} is
 * therefore a Group model on groups.news, groups.edit and groups.delete (their
 * mount() type-hints Group $group) and the raw id string on every other group
 * route. Under Livewire 2 it was always the id.
 *
 * Both middleware compared the parameter with contains('id', ...), which is
 * false against a model, so legitimate members and admins got a 403. The
 * route-level tests (LivewireRouteMountedComponentsTest,
 * RouteMiddlewareRegressionTest) cover the real routes; this file pins both
 * shapes directly, including the refusals, so a fix that accepts the model
 * cannot do it by failing open.
 */
class GroupMembershipMiddlewareTest extends FeatureTestCase
{
    private Group $group;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group = $this->createGroup();
    }

    /**
     * Runs the middleware against a request whose {group} parameter holds
     * $parameter, and returns the status it answers with.
     */
    private function statusFor(string $middleware, User $user, mixed $parameter): int
    {
        $this->actingAs($user);

        $request = Request::create('/groups/'.$this->group->id.'/news');
        $route = (new Route('GET', '/groups/{group}/news', fn () => null))->bind($request);
        $route->setParameter('group', $parameter);
        $request->setRouteResolver(fn () => $route);

        try {
            return (new $middleware())->handle($request, fn () => response('ok'))->getStatusCode();
        } catch (HttpException $e) {
            return $e->getStatusCode();
        }
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function shapes(): array
    {
        return [
            'bound model' => ['model'],
            'raw id'      => ['id'],
        ];
    }

    private function parameter(string $shape): mixed
    {
        return $shape === 'model' ? $this->group : (string) $this->group->id;
    }

    #[DataProvider('shapes')]
    public function test_group_member_lets_an_accepted_member_through(string $shape): void
    {
        $member = $this->createUser();
        $this->attachUserToGroup($member, $this->group, 'member', true);

        $this->assertSame(200, $this->statusFor(GroupMember::class, $member, $this->parameter($shape)));
    }

    #[DataProvider('shapes')]
    public function test_group_member_refuses_an_outsider(string $shape): void
    {
        $outsider = $this->createUser();
        $this->attachUserToGroup($outsider, $this->createGroup(), 'member', true);

        $this->assertSame(403, $this->statusFor(GroupMember::class, $outsider, $this->parameter($shape)));
    }

    #[DataProvider('shapes')]
    public function test_group_member_refuses_a_pending_member(string $shape): void
    {
        $pending = $this->createUser();
        $this->attachUserToGroup($pending, $this->group, 'member', false);

        $this->assertSame(403, $this->statusFor(GroupMember::class, $pending, $this->parameter($shape)));
    }

    #[DataProvider('shapes')]
    public function test_group_admin_lets_an_admin_and_a_roler_through(string $shape): void
    {
        $admin = $this->createUser();
        $this->attachUserToGroup($admin, $this->group, 'admin', true);
        $roler = $this->createUser();
        $this->attachUserToGroup($roler, $this->group, 'roler', true);

        $this->assertSame(200, $this->statusFor(GroupAdmin::class, $admin, $this->parameter($shape)));
        $this->assertSame(200, $this->statusFor(GroupAdmin::class, $roler, $this->parameter($shape)));
    }

    #[DataProvider('shapes')]
    public function test_group_admin_refuses_an_ordinary_member(string $shape): void
    {
        $member = $this->createUser();
        $this->attachUserToGroup($member, $this->group, 'member', true);

        $this->assertSame(403, $this->statusFor(GroupAdmin::class, $member, $this->parameter($shape)));
    }

    #[DataProvider('shapes')]
    public function test_group_admin_refuses_the_admin_of_another_group(string $shape): void
    {
        $foreignAdmin = $this->createUser();
        $this->attachUserToGroup($foreignAdmin, $this->createGroup(), 'admin', true);

        $this->assertSame(403, $this->statusFor(GroupAdmin::class, $foreignAdmin, $this->parameter($shape)));
    }
}
