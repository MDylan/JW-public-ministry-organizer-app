# Custom Validation Rules

The application ships two rule objects, both under `app/Rules`, and both
implement `Illuminate\Contracts\Validation\ValidationRule` (TODO 42). Every
other rule in the tree is a framework built-in or the static
`Illuminate\Validation\Rule` builder — which, despite the name, is a
different class from the deprecated contract and is used legitimately for
`Rule::unique()` / `Rule::notIn()` in the Fortify actions and the two
`ListUsers` components.

## Why `ValidationRule` and not `Rule`

`Illuminate\Contracts\Validation\Rule` — the `passes()` / `message()` pair —
carries an `@deprecated` docblock since Laravel 10 and is on the removal
path. It raises no runtime PHP deprecation, so
`tests/Concerns/FailsOnApplicationDeprecations` cannot see it; the guard is
`tests/Unit/Rules/DeprecatedValidationContractTest`, which scans `app/` for
the contract's FQCN.

**Moving to `ValidationRule` required no change at any call site**, and that
is worth knowing before someone "fixes" a call site that looks untouched.
`ValidationRuleParser:117-118` wraps a `ValidationRule` in
`Illuminate\Validation\InvokableValidationRule`, and that wrapper itself
implements `Rule, ValidatorAwareRule`, so `Validator::validateUsingCustomRule()`
runs the path it always ran. Two consequences the tests depend on:

- The wrapper calls `setData()` on the rule it wraps when that rule is a
  `DataAwareRule`, so `TimeCheck` still receives the full data set.
- The failed-rule key comes from `$rule->invokable()`, so it stays
  `App\Rules\TimeCheck` / `App\Rules\Throttle` rather than the wrapper's
  class. `assertHasErrors(['message' => Throttle::class])` therefore works.

## `$fail()` and translation — the trap

`$fail('some.lang.key')` puts **the raw key** into the error bag.
`PotentiallyTranslatedString::__toString()` returns the string it was
constructed with unless `translate()` was called, so a rule that forgets
`->translate()` renders `group.messages.limit` to the user, with nothing
anywhere reporting a problem. Both rules call `->translate()`, and both have
a test asserting the resolved sentence rather than merely asserting that an
error exists.

`->translate()` is preferred over `trans()` / `__()` inside a rule because it
resolves through `$this->validator->getTranslator()` rather than the global
container.

## `App\Rules\Throttle`

Rate-limits a field by IP.

| | |
|---|---|
| Constructor | `new Throttle($key, $maxAttempts, $decayInMinutes)` |
| Limiter key | `$key . '|' . request()->ip()` |
| Message | `group.messages.limit` (`lang/{en,hu,de}/group.php`) |
| Call site | `app/Http/Livewire/Groups/Messages.php:56` — `new Throttle('group-message-'.$this->group_id, 3, 1)` |

**The attempt counter is incremented inside the rule, on the passing branch
only.** That is what makes it a throttle rather than a predicate — no caller
records the attempt — and the placement matters: incrementing before the
budget check would let a blocked user extend their own lockout by retrying.
`ThrottleTest::test_a_blocked_attempt_does_not_extend_the_lockout` pins it.

The key carries the group id, so each group has its own budget.

## `App\Rules\TimeCheck`

Compares one time field of an array row against its sibling in the same row,
allowing the pair to span midnight.

| | |
|---|---|
| Constructor | `new TimeCheck($otherField, $type)`, `$type` being `after_or_midnight` or `before_or_midnight` |
| Messages | `validation.after` / `validation.before` with a `:date` replacement (`lang/{de,en,fr,hu,ro,sk}/validation.php`) |
| Call site | `app/Http/Livewire/Groups/UpdateGroupForm.php`, on the day validator, which is handed `$this->days` directly and is the only guard on those times since TODO 42.1 |

**Why it exists at all, given `before_or_equal` / `after_or_equal`.** A
group's service day may end at `00:00`, meaning midnight at the *end* of the
day. Every ordinary comparison rule reads `00:00` as the start of the day and
rejects `18:00 - 00:00`, which is a legitimate template. The two
`*_or_midnight` types encode that exception and nothing else.

**Why it is data-aware rather than parameterised.** It is attached to a
wildcard attribute, so it has to find the *sibling* of the row under
validation, and the row key is only knowable from the concrete attribute the
validator hands in (`3.start_time`).

**When the counterpart is missing or is not a string, the comparison is
skipped rather than failed.** A comparison cannot decide anything without both
operands, and the counterpart carries its own `required` and
`date_format:H:i`, which report the real problem against the field that has
it. Before TODO 42 this path failed with no error key set, so `message()`
resolved `trans('validation.')` and the literal string `validation.` was
attached to the field that was fine.

## The service-day form: one guard, one key shape

Both halves of this were found while delivering TODO 42 and settled by
TODO 42.1.

### The first validator's `days.*` rules are gone

`UpdateGroupForm` used to declare `days.*.start_time`, `days.*.end_time` and
`days.*.day_number` on the validator that checks `$this->state`. **They were
dead on one path and actively wrong on the other**, which is why deleting
them was the fix rather than repairing them.

On the ordinary edit path they expanded to zero rules: `mount()` sets
`$this->state = $group->toArray()` before it first touches `$group->days`,
`Group` declares no `$with`, and Laravel expands a wildcard rule whose root
is absent into nothing.

With `?show_future=1` the state is replaced by
`updateGroupFutureChanges::getState()`, and that class loads the group as
`Group::where(...)->with(['days'])->first()` before calling `toArray()` — so
there the `days` key exists, the wildcard expands, and
`before_or_equal:days.*.end_time` runs for real. It then **rejected the
18:00–00:00 template**, because `before_or_equal` reads `00:00` as the start
of the day. That is precisely what `TimeCheck` exists to express.

Two details worth keeping:

- Even when they passed, they measured the wrong array: `$this->state['days']`
  holds the **stored** days while `$this->days` holds the pending ones.
- The error key was `days.0.start_time` — the relation index, not the day
  number — so a prefixed lookup in the view would have flagged Sunday for a
  Wednesday row.

Reaching them took both `?show_future=1` **and** `?remove_future_changes=1`:
the first alone renders no submit button (the save block is inside
`@if (!isset($future_changes))`), and the second alone leaves the state
without a `days` key. Nothing in the application renders that combination.

**`TimeCheck` on `$this->days` is now the only guard on the day times, on
every path.**

### The error keys are bare, and the view says so once

The day validator is handed `$this->days` as its whole data set, so its
attributes are `3.start_time`, not `days.3.start_time`. The Blade view names
that shape once, in a `@php($dayKey = $day)` at the top of the day loop, and
both `is-invalid` checks and both `@error` blocks read it.

Before TODO 42.1 the four checks disagreed: two looked for the prefixed key
and never fired, one was passed `'{{$day}}.end_time'` — `@error` compiles its
argument as raw PHP, so it searched for an attribute with that literal name —
and only a `<small class="text-danger">` block under the row used the bare
key. That block is gone; keeping it beside the fixed `@error` blocks would
render the same sentence two or three times.

`lang/{hu,en,de}/validation.php` define the attribute name under **both**
shapes, so the key choice is locale-neutral. The other locales define
neither and fall back to the humanised attribute.

## What the user actually meets: the component clamps

`UpdateGroupForm::render()` regenerates each day's start and end option lists
on every request and rewrites any value that has fallen out of its list.
Livewire runs `render()` after every property update, so **a reversed range
cannot be assembled through the form at all** — the offending field is
corrected before anything is submitted. It also makes the update *order*
significant: widening the end of the day is what makes a later start
reachable.

**Only the field the user got wrong moves.** The end list is built from the
start *as clamped*. Until TODO 42.1 it was built from the start the loop was
handed, so a mis-clicked start dragged the stored end out of range too and
what survived was `00:00–00:00`, the "no service" template — the day was
emptied, silently. Two invariants are stated in the code: neither option list
can ever be empty, and the `[0]` / last-element asymmetry is deliberate,
because both extremes widen the day as far as the counterpart allows.

**The clamp is not a complete guard, and `TimeCheck` is not merely a backstop
against a forged payload.** With `min_time = 120` and a start of `23:00` the
end option list is `['23:00']` and nothing else, so the clamp produces the
zero-length pair `23:00–23:00`, which `TimeCheck` rejects on both fields. A
group in that configuration cannot be saved at all — a separate defect,
recorded rather than fixed, and the reason the error now has to be visible on
the field.

The option lists are keyed by the day, not by the checkbox. They used to be
keyed by `$day['day_number']`, which is `false` for an unchecked day and
therefore the array key `0` — so unchecking Wednesday wrote Wednesday's lists
into Sunday's slot.

`tests/Feature/Groups/GroupDayTimeRangeTest` asserts all of it.

## Tests

| File | What it covers |
|---|---|
| `tests/Unit/Rules/TimeCheckTest.php` | The rule at `Validator::make()` level: accepted ranges including both midnight variants, rejections with their exact messages, the `failed()` key, and the skipped-comparison path. |
| `tests/Unit/Rules/ThrottleTest.php` | Budget exhaustion, the translated message, the `failed()` key, the increment placement, and per-key budgets. |
| `tests/Unit/Rules/DeprecatedValidationContractTest.php` | No class under `app/` uses `Rule`, `ImplicitRule` or `InvokableRule`. |
| `tests/Feature/Groups/GroupDayTimeRangeTest.php` | The midnight template through the real form and through `?show_future=1`; the clamp, including which field moves and which does not; the `min_time = 120` pair the clamp cannot repair, asserted down to the `is-invalid` class and the rendered sentence; and the option-list keying when a day is unchecked. |
| `tests/Feature/Livewire/GroupMessagesTest.php` | The throttle through `Groups\Messages`, including the rendered message. |
