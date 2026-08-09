<?php

namespace Tests\Feature\Livewire;

use App\Http\Livewire\Events\Events;
use App\Http\Livewire\Events\LastEvents;
use App\Http\Livewire\Groups\Statistics;
use App\Models\Group;
use App\Models\User;
use App\Support\Retention\RetentionWindow;
use Livewire\Livewire;
use Tests\Feature\FeatureTestCase;

/**
 * v1-patch E6: the views must not let a user open a period the purge has
 * already emptied.
 *
 * This is not cosmetic. Groups\Statistics builds its daily rows from
 * group_dates, so a purged period would not render as a blank table - it
 * would render a full one claiming the group served 0 hours out of N
 * available on every day. Fabricated data is worse than missing data, which
 * is why the clamp is server-side and the `min` attribute is only advisory.
 */
class RetentionFloorTest extends FeatureTestCase
{
    private Group $group;
    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group = $this->createGroup();
        $this->admin = $this->createUser(['email' => 'retention-floor-admin@example.test']);
        $this->attachUserToGroup($this->admin, $this->group, 'admin', true);
    }

    private function enableGroupDataRetention(string $months = '12'): void
    {
        config(['settings_group_data_retention' => $months]);
    }

    // --- Groups\Statistics ---

    public function test_statistics_pulls_the_start_date_up_to_the_retention_floor(): void
    {
        $this->enableGroupDataRetention();
        $floor = RetentionWindow::displayFloor();

        Livewire::actingAs($this->admin)
            ->test(Statistics::class, ['group' => $this->group->id])
            ->set('startDate', now()->subYears(3)->toDateString())
            ->set('endDate', now()->toDateString())
            ->call('applyDateRange')
            ->assertSet('startDate', $floor->toDateString())
            ->assertOk();
    }

    public function test_statistics_offers_the_retention_floor_as_the_pickers_minimum(): void
    {
        // A csoportot öregíteni kell: a padló csak akkor korlátoz, ha KÉSŐBBI,
        // mint a csoport létrehozása. Egy ma létrehozott csoportnál a
        // létrehozás dátuma a szigorúbb, és annak is kell maradnia.
        $this->group->forceFill(['created_at' => now()->subYears(4)])->saveQuietly();
        $this->enableGroupDataRetention();
        $floor = RetentionWindow::displayFloor()->toDateString();

        Livewire::actingAs($this->admin)
            ->test(Statistics::class, ['group' => $this->group->id])
            ->assertViewHas('picker', fn ($picker) => $picker['minDate'] === $floor);
    }

    public function test_statistics_keeps_the_group_creation_date_when_it_is_later_than_the_floor(): void
    {
        // Friss csoport: nincs értelme a retenciós padlóig visszaengedni,
        // amikor a csoport akkor még nem is létezett.
        $this->enableGroupDataRetention();
        $created = $this->group->created_at->format('Y-m-d');

        Livewire::actingAs($this->admin)
            ->test(Statistics::class, ['group' => $this->group->id])
            ->assertViewHas('picker', fn ($picker) => $picker['minDate'] === $created);
    }

    public function test_statistics_keeps_the_group_creation_date_as_the_minimum_while_retention_is_off(): void
    {
        config(['gdpr.enabled' => false, 'settings_group_data_retention' => '0']);
        $this->group->forceFill(['created_at' => now()->subYears(4)])->saveQuietly();
        $created = $this->group->created_at->format('Y-m-d');

        Livewire::actingAs($this->admin)
            ->test(Statistics::class, ['group' => $this->group->id])
            ->assertViewHas('picker', fn ($picker) => $picker['minDate'] === $created);
    }

    public function test_statistics_leaves_a_date_range_inside_the_window_untouched(): void
    {
        $this->enableGroupDataRetention();
        $target = now()->subMonth();

        Livewire::actingAs($this->admin)
            ->test(Statistics::class, ['group' => $this->group->id])
            ->set('startDate', $target->format('Y-m-01'))
            ->set('endDate', $target->format('Y-m-t'))
            ->call('applyDateRange')
            ->assertSet('startDate', $target->format('Y-m-01'))
            ->assertSet('endDate', $target->format('Y-m-t'));
    }

    public function test_the_statistics_view_carries_the_minimum_on_both_date_inputs(): void
    {
        // A picker.minDate-et a render() korábban is átadta, de EGYETLEN nézet
        // sem használta - a korlát így csak a szerveren létezett volna.
        $view = file_get_contents(resource_path('views/livewire/groups/statistics.blade.php'));

        $this->assertSame(
            2,
            substr_count($view, 'min="{{ $picker[\'minDate\'] }}"'),
            'A dátumválasztó mindkét mezőjének hordoznia kell a minimumot.'
        );
    }

    // --- Events\LastEvents ---

    public function test_last_events_starts_the_month_list_at_the_events_floor(): void
    {
        config(['gdpr.enabled' => true]);
        $member = $this->createUser([
            'email' => 'retention-floor-member@example.test',
            'created_at' => now()->subYears(4),
        ]);
        $this->attachUserToGroup($member, $this->group, 'member', true);

        $component = Livewire::actingAs($member)->test(LastEvents::class);

        $months = array_keys($component->get('months'));
        $expected = RetentionWindow::eventsFloor()->startOfMonth()->format('Y-m-01');

        $this->assertSame($expected, reset($months));
    }

    /**
     * A LastEvents csak eseményt kérdez, day_stats-ot nem. Egy 12 hónapos
     * csoportadat-beállítás mellett a displayFloor() KÉSŐBBI, mint a 13
     * hónapos eseményablak - azzal korlátozva egy hónapnyi létező,
     * szerkeszthető esemény tűnne el a választóból.
     */
    public function test_last_events_uses_the_events_floor_not_the_display_floor(): void
    {
        config(['gdpr.enabled' => true]);
        $this->enableGroupDataRetention('12');

        $member = $this->createUser([
            'email' => 'retention-floor-member2@example.test',
            'created_at' => now()->subYears(4),
        ]);
        $this->attachUserToGroup($member, $this->group, 'member', true);

        $component = Livewire::actingAs($member)->test(LastEvents::class);
        $months = array_keys($component->get('months'));

        $this->assertSame(
            RetentionWindow::eventsFloor()->startOfMonth()->format('Y-m-01'),
            reset($months)
        );
        $this->assertNotSame(
            RetentionWindow::displayFloor()->startOfMonth()->format('Y-m-01'),
            reset($months)
        );
    }

    public function test_last_events_keeps_the_registration_month_while_retention_is_off(): void
    {
        config(['gdpr.enabled' => false, 'settings_group_data_retention' => '0']);
        $member = $this->createUser([
            'email' => 'retention-floor-member3@example.test',
            'created_at' => now()->subMonths(3),
        ]);
        $this->attachUserToGroup($member, $this->group, 'member', true);

        $component = Livewire::actingAs($member)->test(LastEvents::class);
        $months = array_keys($component->get('months'));

        $this->assertSame(now()->subMonths(3)->format('Y-m-01'), reset($months));
    }

    // --- Events\Events ---

    public function test_the_calendar_lifts_a_month_below_the_floor_up_to_it(): void
    {
        $this->enableGroupDataRetention();
        session(['groupId' => $this->group->id]);

        $floor = RetentionWindow::displayFloor();

        Livewire::actingAs($this->admin)
            ->test(Events::class, ['year' => 2022, 'month' => 6])
            ->assertSet('year', (int) $floor->format('Y'))
            ->assertSet('month', (int) $floor->format('m'));
    }

    public function test_the_calendar_leaves_a_month_inside_the_window_alone(): void
    {
        $this->enableGroupDataRetention();
        session(['groupId' => $this->group->id]);

        $target = now()->subMonth();

        Livewire::actingAs($this->admin)
            ->test(Events::class, ['year' => (int) $target->format('Y'), 'month' => (int) $target->format('m')])
            ->assertSet('year', (int) $target->format('Y'))
            ->assertSet('month', (int) $target->format('m'));
    }

    public function test_the_calendar_hides_the_previous_link_at_the_floor(): void
    {
        $this->enableGroupDataRetention();
        session(['groupId' => $this->group->id]);

        $floor = RetentionWindow::displayFloor();

        $component = Livewire::actingAs($this->admin)->test(Events::class, [
            'year' => (int) $floor->format('Y'),
            'month' => (int) $floor->format('m'),
        ]);

        $this->assertFalse($component->get('pagination')['prev']['year']);
        $this->assertFalse($component->get('pagination')['prev']['month']);
    }
}
