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
| Call site | `app/Http/Livewire/Groups/UpdateGroupForm.php:270-271`, on the **second** validator, which validates `$this->days` directly |

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

## Two traps in the service-day form

Both found while delivering TODO 42, both **inactive defects rather than
behaviour this item changed**, and both owned by roadmap TODO 42.1.

1. **`UpdateGroupForm:249-251` declares three `days.*` rules that compile to
   nothing.** `mount()` sets `$this->state = $group->toArray()` (`:88`) before
   it first touches `$group->days` (`:96`), `Group` declares no `$with`, and
   `toArray()` serialises only loaded relations — so `$this->state` has no
   `days` key, and Laravel expands a wildcard rule whose root is absent into
   zero rules. `TimeCheck`, on the second validator, is the only live guard on
   these times. **Repairing those dead rules would break the midnight
   template**, because `before_or_equal` reads `00:00` as the start of the
   day; the two have to be settled together.

2. **Half of the day-time error keys do not match the view.** The second
   validator is handed `$this->days` as its whole data set, so its keys are
   `3.start_time`, not `days.3.start_time`. The message block at
   `resources/views/livewire/groups/update-group-form.blade.php:522-529` uses
   the bare key and renders correctly; the `is-invalid` class at `:494` and
   `:512` looks for the prefixed key and never fires, and the `@error` at
   `:518` is passed uninterpolated Blade as a PHP argument.

## What the user actually meets: the component clamps

`UpdateGroupForm::render()` (`:559-580`) regenerates each day's start and end
option lists from the counterpart on every request, and rewrites any value
that has fallen out of its list. Livewire runs `render()` after every property
update, so **a reversed range cannot be assembled through the form at all** —
the offending field is corrected before anything is submitted.

`TimeCheck`'s rejection branch is therefore a server-side backstop against a
forged payload, not the guard an ordinary user meets. It also makes the update
*order* significant: widening the end of the day is what makes a later start
reachable. `tests/Feature/Groups/GroupDayTimeRangeTest` asserts both halves.

## Tests

| File | What it covers |
|---|---|
| `tests/Unit/Rules/TimeCheckTest.php` | The rule at `Validator::make()` level: accepted ranges including both midnight variants, rejections with their exact messages, the `failed()` key, and the skipped-comparison path. |
| `tests/Unit/Rules/ThrottleTest.php` | Budget exhaustion, the translated message, the `failed()` key, the increment placement, and per-key budgets. |
| `tests/Unit/Rules/DeprecatedValidationContractTest.php` | No class under `app/` uses `Rule`, `ImplicitRule` or `InvokableRule`. |
| `tests/Feature/Groups/GroupDayTimeRangeTest.php` | The midnight template through the real form, and the clamp described above. |
| `tests/Feature/Livewire/GroupMessagesTest.php` | The throttle through `Groups\Messages`, including the rendered message. |
