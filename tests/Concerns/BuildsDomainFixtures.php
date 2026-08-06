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
     * $count tag a csoportba, sorrendbe rendezhető névvel és e-mail-címmel.
     *
     * A Group::groupUsers() orderByRaw('name_index, email') szerint rendez.
     * A name_index-et a CalulcateUserNameIndexProcess írja, amit a
     * UserObserver MINDEN felhasználó-íráskor elindít: a job az összes
     * felhasználót NÉV szerint rendezi (CollectionHelper::sortByCollator)
     * és újraszámozza őket. Azonos nevek mellett a sorrend tehát esetleges -
     * ezért kap itt minden tag egyedi, nullával tömött nevet, és így lesz a
     * lapozás kiszámítható.
     *
     * A visszatérési érték a felhasználók tömbje, a létrehozás sorrendjében.
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
     * $count csoport ugyanahhoz a felhasználóhoz - a Groups\ListGroups
     * lapozásához, ami a userGroups() reláción paginál.
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
     * Gyermekcsoport a megadott szülő alatt. A pwbs_check_group_other_admins()
     * a szülő mellett a gyermekcsoportok adminjait is átnézi, ezért a
     * szerepkiosztási teszteknek szükségük van erre az ágra.
     */
    protected function createChildGroup(Group $parent, array $attributes = []): Group
    {
        return Group::factory()->asChildOf($parent)->create($attributes);
    }

    /**
     * A Groups\ListUsers::editUser() által épített $state szerkezete.
     *
     * Az updateUser() feltételezi, hogy minden kulcs jelen van - a validátor
     * szabályai (`hidden` => required, `finish_guest_registration` => Rule::In)
     * hiányzó kulcsra máshogy viselkednek, ezért a teszteknek a teljes
     * szerkezetet kell beküldeniük, ahogy a modal is teszi.
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
     * Egy naptári nap beállításai eseménytesztekhez: 08:00-12:00, 60 perces
     * sávokkal, sávonként legfeljebb 3 hírnökkel.
     *
     * A date_* mezők felülírják a csoport azonos nevű beállításait - az
     * Events\EventEdit és az Events\Modal is ezekből dolgozik, ha van
     * current_date sor. Ezért a kapacitást itt kell állítani, nem a
     * csoporton.
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
     * Feltölt egy időtartományt $count eseménnyel, mindegyiket külön
     * felhasználóval, akiket tagként be is léptet a csoportba.
     *
     * A visszatérési érték a létrehozott felhasználók tömbje, hogy a hívó
     * ellenőrizhesse őket (pl. a busy vizsgálathoz).
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
     * Egyetlen esemény adott tartományra. Az EventObserver a létrehozáskor
     * értesítést küld és auth()->user()-t olvas, ezért a hívónak
     * bejelentkezettnek kell lennie (lásd TODO 04 tanulságai).
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
     * Az Events\EventEdit és az Events\Modal a nap tábláját "'HHmm'" alakú
     * kulcsokkal indexeli - az aposztrófok a kulcs részei.
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
     * Ideiglenesen felülír egy környezeti változót a callback idejére.
     *
     * A CheckRecaptcha és a HttpsProtocol FUTÁSIDŐBEN olvas env()-et
     * (USE_RECAPTCHA, USE_HTTPS), nem konfigurációból - ez maga a TODO 28
     * tárgya. A phpunit.xml <server> bejegyzéssel adja meg őket, a Laravel
     * Env repository-ja pedig élőben olvassa a $_SERVER tömböt, tehát a
     * bekapcsolt állapot csak így mérhető.
     *
     * A visszaállítás finally-ben történik, hogy egy elbukó assertion se
     * hagyjon szennyezett környezetet a következő tesztnek.
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
