<?php

namespace Tests\Concerns;

use App\Models\Event;
use App\Models\Group;
use App\Models\GroupDate;
use App\Models\GroupNews;
use App\Models\GroupPosters;
use App\Models\GroupUser;
use App\Models\Settings;
use App\Models\StaticPage;
use App\Models\User;
use Illuminate\Testing\TestResponse;
use Illuminate\Support\Facades\URL;

trait BuildsDomainFixtures
{
    protected function createUser(array $attributes = []): User
    {
        return User::factory()->create(array_merge([
            'password' => bcrypt('password'),
            'email_verified_at' => now(),
            'name' => 'Test User',
            'role' => 'registered',
            'language' => 'hu',
        ], $attributes));
    }

    protected function createGroup(array $attributes = []): Group
    {
        return Group::factory()->create($attributes);
    }

    protected function attachUserToGroup(User $user, Group $group, string $groupRole = 'member', bool $accepted = true): GroupUser
    {
        return GroupUser::factory()
            ->forUser($user)
            ->forGroup($group)
            ->state(['group_role' => $groupRole])
            ->when($accepted, fn ($f) => $f->accepted(), fn ($f) => $f->pending())
            ->create();
    }

    /**
     * $count members into the group, with sortable name and email address.
     *
     * Group::groupUsers() sorts by orderByRaw('name_index, email').
     * The name_index is written by CalulcateUserNameIndexProcess, which
     * UserObserver triggers on EVERY user write: the job sorts all
     * users by NAME (CollectionHelper::sortByCollator)
     * and renumbers them. So among identical names the order is arbitrary -
     * that's why each member here gets a unique, zero-padded name, making
     * pagination predictable.
     *
     * The return value is the array of users, in creation order.
     */
    protected function attachManyUsersToGroup(
        Group $group,
        int $count,
        string $groupRole = 'member',
        bool $accepted = true,
        string $prefix = 'page'
    ): array {
        $users = [];

        for ($i = 1; $i <= $count; $i++) {
            $sequence = str_pad((string) $i, 3, '0', STR_PAD_LEFT);

            $user = $this->createUser([
                'name'  => ucfirst($prefix).' '.$sequence,
                'email' => $prefix.'-'.$sequence.'@example.test',
            ]);
            $this->attachUserToGroup($user, $group, $groupRole, $accepted);

            $users[] = $user;
        }

        return $users;
    }

    /**
     * $count groups for the same user - for Groups\ListGroups
     * pagination, which paginates on the userGroups() relation.
     */
    protected function attachUserToManyGroups(
        User $user,
        int $count,
        string $groupRole = 'member',
        bool $accepted = true
    ): array {
        $groups = [];

        for ($i = 1; $i <= $count; $i++) {
            $group = $this->createGroup(['name' => 'Csoport '.str_pad((string) $i, 3, '0', STR_PAD_LEFT)]);
            $this->attachUserToGroup($user, $group, $groupRole, $accepted);

            $groups[] = $group;
        }

        return $groups;
    }

    /**
     * Child group under the given parent. pwbs_check_group_other_admins()
     * examines the admins of child groups besides the parent, so
     * role-assignment tests need this branch.
     */
    protected function createChildGroup(Group $parent, array $attributes = []): Group
    {
        return Group::factory()->asChildOf($parent)->create($attributes);
    }

    /**
     * The $state structure built by Groups\ListUsers::editUser().
     *
     * updateUser() assumes every key is present - the validator rules
     * (`hidden` => required, `finish_guest_registration` => Rule::In)
     * behave differently for a missing key, so tests must submit the
     * full structure, the same way the modal does.
     */
    protected function editUserState(User $target, array $overrides = []): array
    {
        $state = [
            'group_role'                => 'member',
            'note'                      => null,
            'hidden'                    => 0,
            'user'                      => [
                'name'              => $target->name,
                'phone_number'      => $target->phone_number,
                'congregation'      => $target->congregation,
                'email_verified_at' => $target->email_verified_at,
            ],
            'finish_guest_registration' => 0,
            'message_use'               => 0,
            'message_send_priority'     => 0,
        ];

        if (isset($overrides['user'])) {
            $state['user'] = array_merge($state['user'], $overrides['user']);
            unset($overrides['user']);
        }

        return array_merge($state, $overrides);
    }

    protected function createGroupDate(Group $group, ?string $date = null): GroupDate
    {
        return GroupDate::factory()->create([
            'group_id' => $group->id,
            'date' => $date ?? now()->addDay()->toDateString(),
        ]);
    }

    /**
     * Settings for a single calendar day for event tests: 08:00-12:00, in 60-minute
     * slots, with at most 3 servers per slot.
     *
     * The date_* fields override the group's settings of the same name - both
     * Events\EventEdit and Events\Modal work from these when there is a
     * current_date row. That's why the capacity must be set here, not on the
     * group.
     */
    protected function createEventDate(Group $group, string $date, array $attributes = []): GroupDate
    {
        return GroupDate::factory()->create(array_merge([
            'group_id'            => $group->id,
            'date'                => $date,
            'date_start'          => $date.' 08:00:00',
            'date_end'            => $date.' 12:00:00',
            'date_status'         => 1,
            'date_min_publishers' => 1,
            'date_max_publishers' => 3,
            'date_min_time'       => 60,
            'date_max_time'       => 240,
        ], $attributes));
    }

    /**
     * Fills a time range with $count events, each with a separate
     * user, whom it also adds to the group as a member.
     *
     * The return value is the array of created users, so the caller
     * can inspect them (e.g. for the busy check).
     */
    protected function fillSlotRange(
        Group $group,
        string $date,
        string $from,
        string $to,
        int $count,
        bool $accepted = true,
        string $emailPrefix = 'slot'
    ): array {
        $users = [];

        for ($i = 1; $i <= $count; $i++) {
            $user = $this->createUser(['email' => $emailPrefix.'-'.$i.'-'.uniqid().'@example.test']);
            $this->attachUserToGroup($user, $group, 'member');

            $this->createEventInRange($group, $user, $date, $from, $to, $accepted);

            $users[] = $user;
        }

        return $users;
    }

    /**
     * A single event for a given range. EventObserver sends a
     * notification on creation and reads auth()->user(), so the caller
     * must be logged in (see the lessons of TODO 04).
     */
    protected function createEventInRange(
        Group $group,
        User $user,
        string $date,
        string $from,
        string $to,
        bool $accepted = true
    ): Event {
        $factory = Event::factory()->forGroup($group)->forUser($user);
        $factory = $accepted ? $factory->accepted() : $factory->pending();

        return $factory->create([
            'day'   => $date,
            'start' => $date.' '.$from.':00',
            'end'   => $date.' '.$to.':00',
        ]);
    }

    /**
     * Events\EventEdit and Events\Modal index the day's table with keys
     * in the "'HHmm'" shape - the apostrophes are part of the key.
     */
    protected function slotKey(string $time): string
    {
        return "'".str_replace(':', '', $time)."'";
    }

    protected function timestampFor(string $date, string $time): int
    {
        return strtotime($date.' '.$time.':00');
    }

    /**
     * Temporarily overrides an environment variable for the duration of the callback.
     *
     * CheckRecaptcha and HttpsProtocol read env() AT RUNTIME
     * (USE_RECAPTCHA, USE_HTTPS), not from configuration - this is exactly the
     * subject of TODO 28. phpunit.xml supplies them via a <server> entry, and
     * Laravel's Env repository reads the $_SERVER array live, so the
     * enabled state can only be measured this way.
     *
     * The restoration happens in a finally block, so that a failing assertion
     * doesn't leave a polluted environment for the next test.
     *
     * @template T
     * @param  callable():T  $callback
     * @return T
     */
    protected function withEnvValue(string $key, ?string $value, callable $callback)
    {
        $hadValue = array_key_exists($key, $_SERVER);
        $original = $_SERVER[$key] ?? null;

        if ($value === null) {
            unset($_SERVER[$key]);
        } else {
            $_SERVER[$key] = $value;
        }

        try {
            return $callback();
        } finally {
            if ($hadValue) {
                $_SERVER[$key] = $original;
            } else {
                unset($_SERVER[$key]);
            }
        }
    }

    protected function createGroupNews(Group $group, User $user, array $attributes = []): GroupNews
    {
        $locale = config('app.locale', 'hu');

        return GroupNews::create(array_merge([
            'group_id' => $group->id,
            'user_id' => $user->id,
            'status' => 1,
            'date' => now()->toDateString(),
            $locale => [
                'title' => 'Teszt hir',
                'content' => 'Teszt tartalom',
            ],
        ], $attributes));
    }

    protected function createGroupPoster(Group $group, array $attributes = []): GroupPosters
    {
        return GroupPosters::create(array_merge([
            'group_id' => $group->id,
            'info' => 'Teszt hirdetmény',
            'show_date' => now()->toDateString(),
            'hide_date' => null,
        ], $attributes));
    }

    protected function createHomeStaticPage(User $user, int $status = 1): StaticPage
    {
        $locale = config('app.locale', 'hu');

        return StaticPage::create([
            'status' => $status,
            'slug' => 'home',
            'position' => 'hidden',
            'user_id' => $user->id,
            $locale => [
                'title' => 'Home',
                'content' => 'Home content',
            ],
        ]);
    }

    protected function seedCoreSettings(): void
    {
        Settings::updateOrCreate(['name' => 'registration'], ['value' => 1]);
        Settings::updateOrCreate(['name' => 'claim_group_creator'], ['value' => 1]);
        Settings::updateOrCreate(['name' => 'default_language'], ['value' => config('app.locale', 'hu')]);
        Settings::updateOrCreate(['name' => 'terms_checkbox'], ['value' => 0]);
    }

    protected function signedRoute(string $name, array $parameters = [], int $minutes = 60): string
    {
        return URL::temporarySignedRoute($name, now()->addMinutes($minutes), $parameters);
    }

    protected function passwordConfirmedSession(): array
    {
        return ['auth.password_confirmed_at' => time()];
    }

    protected function getWithPasswordConfirmation(string $uri): TestResponse
    {
        return $this->withSession($this->passwordConfirmedSession())->get($uri);
    }
}
