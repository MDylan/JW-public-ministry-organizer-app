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
 * The service-day time range, through the real edit form. Started by TODO 42
 * and finished by TODO 42.1, which closed the three findings below.
 *
 * TimeCheckTest addresses the rule in isolation. This file answers the
 * question that one cannot: what does the form actually let a user submit?
 *
 * 1. THE COMPONENT CLAMPS, IT DOES NOT REJECT. render() regenerates the start
 *    and end option lists on every request and rewrites any value that has
 *    fallen out of its list. Livewire runs render() after every property
 *    update, so a reversed range cannot be assembled through the component at
 *    all - the offending field is corrected before anything is submitted. It
 *    also means the ORDER of the two updates matters, which is why the helper
 *    below sets end_time first: on the stored 08:00-16:00 template, setting
 *    start_time to 18:00 first clamps it straight back to 00:00.
 *
 *    TimeCheck is NOT merely a backstop against a forged payload, and this
 *    file used to say it was. The clamp itself produces a pair the validator
 *    rejects whenever min_time is large enough - see
 *    test_a_template_the_clamp_cannot_repair_is_reported_on_the_field.
 *
 * 2. THE FIRST VALIDATOR'S days.* RULES WERE NOT DEAD EVERYWHERE, which is
 *    the opposite of what this docblock claimed before TODO 42.1. On the
 *    ordinary edit path they expanded to zero rules, because mount() fills
 *    $state from a Group whose `days` relation is not loaded. But
 *    ?show_future=1 replaces $state with updateGroupFutureChanges::getState(),
 *    which loads the relation - and there `before_or_equal` rejected the
 *    midnight template outright. The rules are gone; TimeCheck is the single
 *    guard on both paths. See
 *    test_the_pending_changes_screen_can_save_a_midnight_template.
 *
 * 3. THE ERROR KEYS ARE BARE - `3.start_time`, not `days.3.start_time` -
 *    because the day validator is handed $this->days as its whole data set.
 *    The view now reads that one shape through a single `$dayKey` variable,
 *    so both selects turn red and each carries its own message. Before
 *    TODO 42.1 two of the four checks looked for the prefixed key and one was
 *    passed uninterpolated Blade as a PHP argument.
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
     * remaining guard.
     *
     * ONLY THE FIELD THE USER GOT WRONG MOVES, and that is what TODO 42.1
     * changed here. render() used to build the end option list from the start
     * value it was HANDED (18:00), producing 18:00...00:00, so the stored
     * 16:00 fell out of that list too and the end was pulled to its last
     * option while the start was pulled to its first - the pair that survived
     * was 00:00-00:00, the "no service" template. A mis-clicked start emptied
     * the whole day, silently. The end list is now built from the start AS
     * CLAMPED (00:00), which contains 16:00, so the end stays put.
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
        $this->assertSame(
            self::STORED_END,
            $days[$this->dayNumber]['end_time'],
            'The end was not the field at fault and must not have been dragged along.'
        );

        $component->call('updateGroup')->assertHasNoErrors();
    }

    /**
     * The mirror of the case above, which had no coverage at all before
     * TODO 42.1: an end BEFORE the stored start. The start list is bounded by
     * the end (06:00), so the stored 08:00 falls out of it and is pulled to
     * 00:00; the end list is then rebuilt from that 00:00 and contains 06:00,
     * so the end the user actually chose survives.
     */
    public function test_an_end_before_the_start_moves_only_the_start(): void
    {
        Notification::fake();

        $component = $this->form()
            ->set('days.'.$this->dayNumber.'.end_time', '06:00');

        $days = $component->get('days');

        $this->assertSame('00:00', $days[$this->dayNumber]['start_time']);
        $this->assertSame('06:00', $days[$this->dayNumber]['end_time']);

        $component->call('updateGroup')->assertHasNoErrors();
    }

    /**
     * The "no service" template is load-bearing - TimeCheck.php:109-111 lets
     * it through as a non-range, and updateGroupFutureChanges::initChanges()
     * reads a false day_number as a deletion - but nothing pinned that the
     * clamp leaves it alone. It has to survive a render untouched, or every
     * disabled day would drift on its own.
     */
    public function test_the_no_service_template_survives_a_render(): void
    {
        GroupDay::where('group_id', $this->group->id)
            ->where('day_number', $this->dayNumber)
            ->update(['start_time' => '00:00', 'end_time' => '00:00']);

        $days = $this->form()->get('days');

        $this->assertSame('00:00', $days[$this->dayNumber]['start_time']);
        $this->assertSame('00:00', $days[$this->dayNumber]['end_time']);
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

    /**
     * THE CLAMP ITSELF PRODUCES A PAIR THE VALIDATOR REJECTS, so this test
     * needs no forged payload and no bypass - it reaches TimeCheck through
     * the ordinary form.
     *
     * With min_time = 120 (legal: UpdateGroupForm:239 allows in:30,60,90,120)
     * and a start of 23:00, generateTimeArray(false,'23:00',120) returns
     * ['23:00'] and nothing else: the first iteration pushes 23:00, the next
     * $current is 01:00 the following day, which is > $midnight, and :189
     * breaks. render() therefore clamps the end to the only option there is,
     * producing the zero-length pair 23:00-23:00 - which TimeCheck rejects on
     * BOTH fields, because $time === $other and neither is midnight, so no
     * branch returns early (TimeCheck.php:109-135).
     *
     * That is a defect in its own right, recorded rather than fixed here: a
     * group configured with min_time = 120 and a day ending at midnight
     * cannot be saved at all. What TODO 42.1 fixes is that the user can now
     * SEE which field is being complained about.
     *
     * The three assertions after the save are the item: the error keys are
     * bare (`3.start_time`), the is-invalid class actually reaches the markup
     * - before TODO 42.1 the class was keyed off `days.3.start_time`, which
     * the bag never carries - and the sentence resolves rather than rendering
     * a raw lang key. The suite runs with APP_LANG=hu (.env.testing), so the
     * quoted sentences are the Hungarian ones; `szolgálat kezdete` comes from
     * lang/hu/validation.php:140, which maps the bare `*.start_time`.
     */
    public function test_a_template_the_clamp_cannot_repair_is_reported_on_the_field(): void
    {
        Notification::fake();

        $component = $this->form()->set('state.min_time', 120);
        $this->setDay($component, '23:00', '00:00');

        // The clamp produced the zero-length pair. Asserted, not assumed:
        // this is the mechanism the rest of the test depends on.
        $days = $component->get('days');
        $this->assertSame('23:00', $days[$this->dayNumber]['start_time']);
        $this->assertSame('23:00', $days[$this->dayNumber]['end_time']);

        $component->call('updateGroup')
            ->assertHasErrors([
                $this->dayNumber.'.start_time',
                $this->dayNumber.'.end_time',
            ])
            ->assertSee('is-invalid')
            ->assertSee('A(z) szolgálat kezdete 23:00 előtti dátum kell, hogy legyen!')
            ->assertSee('A(z) szolgálat vége 23:00 utáni dátum kell, hogy legyen!');

        // Blocked, not half-applied.
        $day = $this->storedDay();
        $this->assertSame(self::STORED_START, $day->start_time);
        $this->assertSame(self::STORED_END, $day->end_time);
    }

    /**
     * UNCHECKING A DAY USED TO OVERWRITE SUNDAY'S OPTION LISTS.
     *
     * render() keyed day_selects and disabled_selects by $day['day_number'],
     * and an unchecked day carries day_number === false (that is what
     * updateGroup():332 tests for). PHP casts false to the array key 0 - so
     * the moment a user unchecked Wednesday, Wednesday's option lists were
     * written into Sunday's slot, and Wednesday's own entry was left behind
     * from the previous render, because day_selects was the one array render()
     * never reset.
     *
     * Keying by $day_key is correct without exception: mount():97 keys
     * $this->days by day number, and the view binds days.{{$day}}.*, so
     * $day_key === $day always.
     *
     * THE RESET AND THE KEYING HAD TO SHIP TOGETHER. Adding the missing
     * day_selects reset on its own would have been a regression: with the old
     * keying, Wednesday's entry would then be absent rather than stale, and
     * `@if(isset($day_selects[$day]))` would drop its two selects out of the
     * page entirely.
     */
    public function test_unchecking_a_day_leaves_the_other_days_option_lists_alone(): void
    {
        GroupDay::factory()->create([
            'group_id' => $this->group->id,
            'day_number' => 0,
            'start_time' => '09:00',
            'end_time' => '11:00',
        ]);

        $component = $this->form()
            ->set('days.'.$this->dayNumber.'.day_number', false);

        $daySelects = $component->get('day_selects');

        // Sunday's end options start at Sunday's own start time. Wednesday's
        // would start at 08:00, which is how the overwrite showed itself.
        $this->assertSame('09:00', $daySelects[0]['end'][0]);
        $this->assertSame('10:30', end($daySelects[0]['start']));

        // The unchecked day keeps its own entry, so its selects stay on the
        // page instead of vanishing or freezing on a previous render.
        $this->assertArrayHasKey($this->dayNumber, $daySelects);
        $this->assertSame('08:00', $daySelects[$this->dayNumber]['end'][0]);

        $this->assertArrayHasKey($this->dayNumber, $component->get('disabled_selects'));
    }
}
