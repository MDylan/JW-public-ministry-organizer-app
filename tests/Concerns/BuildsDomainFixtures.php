<?php

namespace Tests\Concerns;

use App\Models\Group;
use App\Models\GroupDate;
use App\Models\GroupNews;
use App\Models\Settings;
use App\Models\StaticPage;
use App\Models\User;
use Illuminate\Foundation\Testing\TestResponse;
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

    protected function attachUserToGroup(User $user, Group $group, string $groupRole = 'member', bool $accepted = true): void
    {
        $group->groupUsersAllOnly()->syncWithoutDetaching([
            $user->id => [
                'group_role' => $groupRole,
                'accepted_at' => $accepted ? now() : null,
                'deleted_at' => null,
                'message_use' => 1,
                'message_send_priority' => 1,
            ],
        ]);
    }

    protected function createGroupDate(Group $group, ?string $date = null): GroupDate
    {
        return GroupDate::factory()->create([
            'group_id' => $group->id,
            'date' => $date ?? now()->addDay()->toDateString(),
        ]);
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
