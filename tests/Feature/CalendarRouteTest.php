<?php

namespace Tests\Feature;

class CalendarRouteTest extends FeatureTestCase
{
    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('calendar'))->assertRedirect(route('login'));
    }

    public function test_authenticated_user_can_access_calendar(): void
    {
        $user = $this->createUser(['email' => 'cal-route-access@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'member', true);

        $this->actingAs($user)
            ->get(route('calendar'))
            ->assertStatus(200);
    }

    public function test_calendar_with_year_and_month_params_returns_200(): void
    {
        $user = $this->createUser(['email' => 'cal-route-params@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'member', true);

        $this->actingAs($user)
            ->get(route('calendar', ['year' => 2025, 'month' => 6]))
            ->assertStatus(200);
    }

    public function test_calendar_with_invalid_year_falls_back_gracefully(): void
    {
        $user = $this->createUser(['email' => 'cal-route-invalid-year@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'member', true);

        $this->actingAs($user)
            ->get(route('calendar', ['year' => 1990, 'month' => 3]))
            ->assertStatus(200);
    }

    public function test_calendar_with_invalid_month_falls_back_gracefully(): void
    {
        $user = $this->createUser(['email' => 'cal-route-invalid-month@example.test']);
        $group = $this->createGroup();
        $this->attachUserToGroup($user, $group, 'member', true);

        $this->actingAs($user)
            ->get(route('calendar', ['year' => 2025, 'month' => 13]))
            ->assertStatus(200);
    }
}
