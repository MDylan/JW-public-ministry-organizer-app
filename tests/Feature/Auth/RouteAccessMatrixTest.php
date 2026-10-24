<?php

namespace Tests\Feature\Auth;

use App\Http\Middleware\Authenticate;
use App\Http\Middleware\GroupAdmin;
use App\Http\Middleware\GroupMember;
use App\Http\Middleware\ProfileFull;
use App\Http\Middleware\RequirePasswordBeforeAction;
use App\Models\Group;
use App\Models\GroupNewsFile;
use App\Models\StaticPage;
use App\Models\User;
use Illuminate\Auth\Middleware\Authorize;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Auth\Middleware\RequirePassword;
use Illuminate\Routing\Route;
use Tests\Feature\FeatureTestCase;

/**
 * Roadmap TODO 70: every guarded route refuses whoever its guard is for.
 *
 * RouteMiddlewareRegressionTest and its siblings check a sample of routes,
 * RouteContractSnapshotTest pins which middleware each route lists. Neither
 * says that the resolved stack actually turns the wrong user away on EVERY
 * route - after the slim skeleton (TODO 56) re-declared the aliases, groups
 * and priority by hand, and two framework hops changed the middleware around
 * them. This walks the live route table instead: each case below takes every
 * route whose gathered (alias-resolved, priority-sorted) middleware contains
 * one guard, and sends it the one user that guard exists to stop.
 *
 * Only refusals are asserted, so placeholder parameters are enough: the guard
 * answers before any controller looks at them. A route with a parameter this
 * file has no value for fails test_every_route_parameter_has_a_value rather
 * than being skipped.
 */
class RouteAccessMatrixTest extends FeatureTestCase
{
    private Group $group;

    /** @var array<string, string> */
    private array $parameters;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group = $this->createGroup();
        $author = $this->createUser(['email' => 'matrix-author@example.test']);
        $news = $this->createGroupNews($this->group, $author);
        $page = StaticPage::query()->first() ?? $this->createHomeStaticPage($author);

        // {file} and {passkey} are model-bound, and SubstituteBindings runs
        // before verified/profileFull/groupMember (they are not on the
        // priority list), so a missing row would answer 404 before the guard
        // under test does: these two need real rows.
        $file = GroupNewsFile::create(['group_new_id' => $news->id, 'name' => 'matrix.pdf', 'file' => 'matrix.pdf']);
        $passkey = $author->passkeys()->create([
            'name' => 'matrix',
            'credential_id' => rtrim(base64_encode(md5('matrix', true)), '='),
            'credential' => ['aaguid' => '00000000-0000-0000-0000-000000000000'],
        ]);

        $this->parameters = [
            'group' => (string) $this->group->id,
            'new' => (string) $news->id,
            'file' => (string) $file->id,
            'staticPage' => (string) $page->id,
            'user' => (string) $author->id,
            'id' => (string) $author->id,
            'passkey' => (string) $passkey->id,
            'hash' => 'placeholder',
            'token' => 'placeholder',
            'slug' => 'home',
            'filename' => 'placeholder',
            'year' => '2026',
            'month' => '10',
        ];
    }

    /**
     * The routes whose resolved middleware contains $class, as
     * "METHOD uri" => [method, url].
     *
     * @return array<string, array{0: string, 1: string}>
     */
    private function routesGuardedBy(string $class, ?string $parameter = null): array
    {
        $router = app('router');
        $found = [];

        /** @var Route $route */
        foreach ($router->getRoutes() as $route) {
            $middleware = $router->gatherRouteMiddleware($route);

            $matches = array_filter($middleware, function ($m) use ($class, $parameter) {
                [$name, $argument] = array_pad(explode(':', $m, 2), 2, null);

                return $name === $class && ($parameter === null || $argument === $parameter);
            });

            if ($matches === []) {
                continue;
            }

            $method = $route->methods()[0];
            $found[$method.' '.$route->uri()] = [$method, $this->urlFor($route)];
        }

        return $found;
    }

    private function urlFor(Route $route): string
    {
        $uri = preg_replace_callback('/\{(\w+)(\?)?\}/', function ($m) {
            return $this->parameters[$m[1]] ?? ($m[2] === '?' ? '' : '{'.$m[1].'}');
        }, $route->uri());

        return url(rtrim($uri, '/') ?: '/');
    }

    /**
     * Sends every route the same user and collects the ones that did not
     * answer as $expect says.
     *
     * @param  array<string, array{0: string, 1: string}>  $routes
     */
    private function assertEveryRoute(array $routes, ?User $user, callable $expect, string $what): void
    {
        $failures = [];

        foreach ($routes as $label => [$method, $url]) {
            if ($user !== null) {
                $this->actingAs($user);
            }

            $response = $this->withSession($user === null ? [] : ['auth.password_confirmed_at' => 0])
                ->call($method, $url);

            $problem = $expect($response);
            if ($problem !== null) {
                $failures[] = "{$label}: {$problem}";
            }
        }

        $this->assertSame([], $failures, $what);
    }

    private function redirectsTo(string $routeName): callable
    {
        return function ($response) use ($routeName) {
            if (! $response->isRedirect()) {
                return 'answered '.$response->getStatusCode().', expected a redirect to '.$routeName;
            }

            $location = strtok((string) $response->headers->get('Location'), '?');

            return $location === route($routeName) ? null : 'redirected to '.$location;
        };
    }

    private function forbidden(): callable
    {
        return fn ($response) => $response->getStatusCode() === 403
            ? null
            : 'answered '.$response->getStatusCode().', expected 403';
    }

    public function test_every_route_parameter_has_a_value(): void
    {
        $missing = [];

        foreach (app('router')->getRoutes() as $route) {
            foreach ($route->parameterNames() as $name) {
                if (! array_key_exists($name, $this->parameters)) {
                    $missing[] = $route->uri().' {'.$name.'}';
                }
            }
        }

        $this->assertSame([], $missing, 'Give the new route parameter a value in setUp(), or the matrix cannot reach the route.');
    }

    public function test_a_guest_is_sent_to_the_login_from_every_authenticated_route(): void
    {
        $routes = $this->routesGuardedBy(Authenticate::class);
        $this->assertGreaterThan(50, count($routes), 'The route table is not being read.');

        $this->assertEveryRoute($routes, null, $this->redirectsTo('login'), 'An authenticated route let a guest through.');
    }

    public function test_an_unverified_user_is_sent_to_the_verification_notice(): void
    {
        $routes = $this->routesGuardedBy(EnsureEmailIsVerified::class);
        $this->assertGreaterThan(25, count($routes));

        $user = $this->createUser(['email' => 'matrix-unverified@example.test', 'email_verified_at' => null, 'role' => 'mainAdmin']);
        $this->attachUserToGroup($user, $this->group, 'admin');

        $this->assertEveryRoute($routes, $user, $this->redirectsTo('verification.notice'), 'A verified-only route let an unverified user through.');
    }

    public function test_a_user_without_a_profile_is_sent_to_the_profile_page(): void
    {
        $routes = $this->routesGuardedBy(ProfileFull::class);
        $this->assertGreaterThan(25, count($routes));

        $user = $this->createUser(['email' => 'matrix-noprofile@example.test', 'name' => null, 'role' => 'mainAdmin']);
        $this->attachUserToGroup($user, $this->group, 'admin');

        $this->assertEveryRoute($routes, $user, $this->redirectsTo('user.profile'), 'A full-profile route let a user without a name through.');
    }

    public function test_an_outsider_is_refused_on_every_group_route(): void
    {
        $routes = $this->routesGuardedBy(GroupMember::class) + $this->routesGuardedBy(GroupAdmin::class);
        $this->assertGreaterThan(10, count($routes));

        $outsider = $this->createUser(['email' => 'matrix-outsider@example.test']);
        $this->attachUserToGroup($outsider, $this->createGroup(), 'admin');

        $this->assertEveryRoute($routes, $outsider, $this->forbidden(), 'A group route let the admin of ANOTHER group through.');
    }

    public function test_a_pending_member_is_refused_on_every_group_route(): void
    {
        $routes = $this->routesGuardedBy(GroupMember::class) + $this->routesGuardedBy(GroupAdmin::class);

        $pending = $this->createUser(['email' => 'matrix-pending@example.test']);
        $this->attachUserToGroup($pending, $this->group, 'admin', accepted: false);

        $this->assertEveryRoute($routes, $pending, $this->forbidden(), 'A group route let a member who has not accepted the invitation through.');
    }

    public function test_a_plain_member_is_refused_on_every_group_admin_route(): void
    {
        $routes = $this->routesGuardedBy(GroupAdmin::class);
        $this->assertGreaterThan(5, count($routes));

        $member = $this->createUser(['email' => 'matrix-member@example.test']);
        $this->attachUserToGroup($member, $this->group, 'member');

        $this->assertEveryRoute($routes, $member, $this->forbidden(), 'A group-admin route let a plain member through.');
    }

    public function test_a_registered_user_is_refused_on_every_gated_route(): void
    {
        $routes = $this->routesGuardedBy(Authorize::class);
        $this->assertGreaterThan(10, count($routes));

        $user = $this->createUser(['email' => 'matrix-registered@example.test']);
        $this->attachUserToGroup($user, $this->group, 'member');

        $this->assertEveryRoute($routes, $user, $this->forbidden(), 'A gated route let a user without the role through.');
    }

    public function test_a_translator_is_refused_on_every_admin_route(): void
    {
        $routes = $this->routesGuardedBy(Authorize::class, 'is-admin');
        $this->assertGreaterThan(8, count($routes));

        $translator = $this->createUser(['email' => 'matrix-translator@example.test', 'role' => 'translator']);

        $this->assertEveryRoute($routes, $translator, $this->forbidden(), 'An admin route let a translator through.');
    }

    public function test_every_password_confirmed_route_asks_even_the_most_privileged_user(): void
    {
        // RequirePasswordBeforeAction is the POST/DELETE variant (TODO 70).
        $routes = $this->routesGuardedBy(RequirePassword::class) + $this->routesGuardedBy(RequirePasswordBeforeAction::class);
        $this->assertGreaterThan(15, count($routes));

        // Passes every other guard on every route: verified, a name, the
        // main admin role, admin of the group the parameters point at.
        $admin = $this->createUser(['email' => 'matrix-admin@example.test', 'role' => 'mainAdmin']);
        $this->attachUserToGroup($admin, $this->group, 'admin');

        $this->assertEveryRoute($routes, $admin, $this->redirectsTo('password.confirm'), 'A password-confirmed route went through without a confirmation.');
    }
}
