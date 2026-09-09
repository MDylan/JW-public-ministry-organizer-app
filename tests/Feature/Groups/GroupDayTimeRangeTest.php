<?php

namespace Tests\Feature\Groups;

use App\Http\Livewire\Groups\UpdateGroupForm;
use App\Models\Group;
use App\Models\GroupDay;
use App\Models\GroupFutureChange;
use App\Models\User;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 42: the service-day time range, through the real edit form.
 *
 * TimeCheckTest addresses the rule in isolation. This file answers the
 * question that one cannot: what does the form actually let a user submit?
 *
 * THREE MEASURED FACTS ABOUT THIS FORM, all found while writing this file.
 * The first is what the tests below assert; the other two are recorded as
 * TODO 42.1 rather than fixed here.
 *
 * 1. THE COMPONENT CLAMPS, IT DOES NOT REJECT. render() (UpdateGroupForm.php
 *    :559-580) regenerates the start and end option lists from the day's
 *    CURRENT counterpart on every request, and rewrites any value that is no
 *    longer in its list to the first (or last) option that is. Livewire runs
 *    render() after every property update, so a reversed range cannot be
 *    assembled through the component at all - the second field is corrected
 *    before anything is submitted. TimeCheck's rejection branch is therefore
 *    a server-side backstop against a payload the UI does not produce, not
 *    the guard the user meets. It also means the ORDER of the two updates
 *    matters, which is why the helper below sets end_time first: with the
 *    stored 08:00-16:00 template, setting start_time to 18:00 first clamps it
 *    straight back to 00:00.
 *
 * 2. UpdateGroupForm:249-251 declares days.*.start_time, days.*.end_time and
 *    days.*.day_number on the FIRST validator, and those three rules compile
 *    to nothing. mount() sets $this->state = $group->toArray() (:88) before
 *    it first touches $group->days (:96), Group declares no $with, and
 *    toArray() serialises only loaded relations - so $this->state has no
 *    `days` key, and Laravel expands a wildcard rule whose root is absent
 *    into zero rules. Anyone who "repairs" those dead rules will break the
 *    midnight case below, because before_or_equal reads 00:00 as the start of
 *    the day; the two have to be settled together.
 *
 * 3. The second validator's error keys are `3.start_time`, NOT
 *    `days.3.start_time`, because it is handed $this->days as its whole data
 *    set. The message block at update-group-form.blade.php:522-529 uses the
 *    bare key and does render; the is-invalid class at :494 and :512 looks
 *    for the prefixed key and never fires, and the @error at :518 is passed
 *    uninterpolated Blade as a PHP argument.
 */
class GroupDayTimeRangeTest extends FeatureTestCase
{
    private const STORED_START = '08:00';
    private const STORED_END = '16:00';

    private Group $group;
    private User $editor;
    private int $dayNumber = 3;

    protected function setUp(): void
    {
        parent::setUp();

        $this->group = $this->createGroup([
            'name' => 'Idősáv Csoport',
            'min_time' => 60,
            'max_time' => 240,
            'min_publishers' => 1,
            'max_publishers' => 3,
            'need_approval' => 0,
        ]);

        $this->editor = $this->createUser([
            'email' => 'daytime-editor@example.test',
            'name' => 'Szerkesztő Sára',
        ]);
        $this->attachUserToGroup($this->editor, $this->group, 'roler');

        GroupDay::factory()->create([
            'group_id' => $this->group->id,
            'day_number' => $this->dayNumber,
            'start_time' => self::STORED_START,
            'end_time' => self::STORED_END,
        ]);
    }

    /**
     * The group's settings unchanged - besides the day template nothing here
     * wants to move, these fields only exist to satisfy the first validator.
     */
    private function formState(): array
    {
        return [
            'name' => 'Idősáv Csoport',
            'replyTo' => 'noreply@example.test',
            'max_extend_days' => 30,
            'need_approval' => 0,
            'min_publishers' => 1,
            'max_publishers' => 3,
            'min_time' => 60,
            'max_time' => 240,
            'showPhone' => 1,
            'messages_on' => 1,
            'messages_write' => 1,
            'messages_priority' => 1,
            'weather_enabled' => 0,
        ];
    }

    private function form(): \Livewire\Testing\TestableLivewire
    {
        $component = Livewire::actingAs($this->editor)
            ->test(UpdateGroupForm::class, ['group' => $this->group])
            ->set('change_date', today()->toDateString());

        foreach ($this->formState() as $field => $value) {
            $component->set('state.'.$field, $value);
        }

        return $component;
    }

    /**
     * END FIRST, THEN START - see fact 1 in the class docblock. Widening the
     * end of the day first is what makes the later start reachable, and it is
     * the order the browser produces too, because each change regenerates the
     * other select.
     */
    private function setDay(\Livewire\Testing\TestableLivewire $component, string $start, string $end): \Livewire\Testing\TestableLivewire
    {
        return $component
            ->set('days.'.$this->dayNumber.'.end_time', $end)
            ->set('days.'.$this->dayNumber.'.start_time', $start);
    }

    private function storedDay(): GroupDay
    {
        return GroupDay::where('group_id', $this->group->id)
            ->where('day_number', $this->dayNumber)
            ->firstOrFail();
    }

    /**
     * The whole reason TimeCheck exists rather than before_or_equal, proven
     * through the form rather than through a validator built by hand: an
     * 18:00-00:00 template is saved, not rejected.
     */
    public function test_a_service_day_may_end_at_midnight(): void
    {
        Notification::fake();

        $component = $this->form();
        $this->setDay($component, '18:00', '00:00')
            ->call('updateGroup')
            ->assertHasNoErrors();

        $day = $this->storedDay();

        $this->assertSame('18:00', $day->start_time);
        $this->assertSame('00:00', $day->end_time);
    }

    /**
     * A narrower change through the same path, so the midnight case above is
     * not the only evidence that this form saves day times at all.
     */
    public function test_an_ordinary_range_is_saved(): void
    {
        Notification::fake();

        $component = $this->form();
        $this->setDay($component, '10:00', '12:00')
            ->call('updateGroup')
            ->assertHasNoErrors();

        $day = $this->storedDay();

        $this->assertSame('10:00', $day->start_time);
        $this->assertSame('12:00', $day->end_time);
    }

    /**
     * Fact 1, as an assertion. A user cannot build a reversed range: the
     * start is corrected to the first still-valid option before the form is
     * ever submitted, so TimeCheck never sees it. If someone later removes
     * the clamp from render(), this fails and points at TimeCheck as the only
     * remaining guard - which is exactly the conversation TODO 42.1 wants.
     *
     * BOTH FIELDS MOVE, and the second one is a consequence of the first.
     * render() rebuilds the end options from the start value it was HANDED
     * (18:00), producing 18:00...00:00, and the stored 16:00 is not in that
     * list either - so the end is pulled to the last option while the start
     * is pulled to the first. The pair that survives is 00:00-00:00, the
     * "no service" template. That is a rough edge worth knowing about, and
     * it is TODO 42.1's business, not this item's; pinning it here means a
     * future change to the clamp cannot move it unnoticed.
     */
    public function test_the_component_corrects_a_reversed_range_instead_of_submitting_it(): void
    {
        Notification::fake();

        $component = $this->form()
            ->set('days.'.$this->dayNumber.'.start_time', '18:00');

        $days = $component->get('days');

        $this->assertNotSame(
            '18:00',
            $days[$this->dayNumber]['start_time'],
            'render() should have pulled the start back inside the day.'
        );
        $this->assertSame('00:00', $days[$this->dayNumber]['start_time']);
        $this->assertSame('00:00', $days[$this->dayNumber]['end_time']);

        $component->call('updateGroup')->assertHasNoErrors();
    }

    /**
     * THE ONE PATH ON WHICH UpdateGroupForm:249-251 WAS NOT DEAD.
     *
     * mount():158-163 replaces $this->state with
     * updateGroupFutureChanges::getState(), and that class loads the group as
     * Group::where(...)->with(['days'])->first() (:28) before calling
     * toArray() (:30) - so with ?show_future=1 the state HAS a `days` key, the
     * wildcard expands, and `before_or_equal:days.*.end_time` runs for real
     * (Validator::validateAttribute() substitutes the concrete index into the
     * dependent parameter). GroupDay's H:i accessors make date_format pass
     * first, so the comparison is reached rather than short-circuited. It then
     * rejects the 18:00-00:00 template that TimeCheck exists to allow, because
     * before_or_equal reads 00:00 as the START of the day.
     *
     * HOW REACHABLE, measured rather than assumed, because the answer is
     * narrower than it first looks. The save block is inside
     * `@if (!isset($future_changes))` (update-group-form.blade.php:613), so a
     * group with a pending change renders NO submit button - ?show_future=1 on
     * its own is a read-only screen. The second entry point is
     * ?remove_future_changes=1 (mount():171-175), which deletes the row and
     * calls updateGroup() from inside mount(); removeFutureChanges():452
     * redirects there WITHOUT show_future, and on that URL the state carries
     * no `days` key, so the rules stay dead. Only the two parameters TOGETHER
     * make them run, and nothing in the application renders that link - it has
     * to be composed by hand from the redirect URL. That is why this defect
     * survived: it is real, it is not theoretical, and it is behind a URL no
     * click produces.
     *
     * The error key was `days.0.start_time` - the RELATION INDEX, not the day
     * number - so the view's prefixed is-invalid check would have lit up
     * Sunday for a Wednesday row.
     *
     * Recorded, not asserted: on this path $this->state['days'] holds the
     * STORED days while $this->days holds the pending ones
     * (updateGroupFutureChanges:69), so the rules measured the wrong array
     * even when they passed.
     */
    public function test_the_pending_changes_screen_can_save_a_midnight_template(): void
    {
        Notification::fake();

        // The STORED template is the midnight one, because the dead rules
        // validate $this->state['days'] - the stored days - and that is what
        // has to trip them.
        $stored = $this->storedDay();
        $stored->update(['start_time' => '18:00', 'end_time' => '00:00']);

        // The PENDING template differs from it, so the assertions below can
        // tell "the save ran" apart from "the save was rejected and the stored
        // row simply never moved". updateGroup() writes $this->days, which is
        // the pending array (updateGroupFutureChanges:69).
        GroupFutureChange::create([
            'group_id' => $this->group->id,
            'change_date' => today()->addDay()->toDateString(),
            'group' => [],
            'days' => [
                $this->dayNumber => [
                    'day_number' => (string) $this->dayNumber,
                    'start_time' => '19:00',
                    'end_time' => '00:00',
                ],
            ],
            'disabled_slots' => [],
            'user_id' => $this->editor->id,
        ]);

        // updateGroup() runs from inside mount() on this URL, so a rejection
        // surfaces out of the mount itself rather than as a component error
        // bag - which is why this test asserts on the stored row instead of
        // with assertHasNoErrors().
        Livewire::withQueryParams(['show_future' => 1, 'remove_future_changes' => 1])
            ->actingAs($this->editor)
            ->test(UpdateGroupForm::class, ['group' => $this->group]);

        $day = $this->storedDay();

        $this->assertSame('19:00', $day->start_time);
        $this->assertSame('00:00', $day->end_time);
    }
}
