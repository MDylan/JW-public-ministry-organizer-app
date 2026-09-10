# `livewire:upgrade` on this application - what it did and what it did not

Measured on 2026-09-10 against `livewire/livewire v3.8.8`, the release `^3.0`
resolved to. This file is the evidence behind TODO 43's inventory in
`upgrade-roadmap.md`; the roadmap carries the conclusions, this one carries the
measurements.

It lives under `upgrade-notes/`, which `release/build-update.php` lists in
`EXCLUDE_PREFIXES`, so nothing here ships to a deployed host.

## The command

`livewire:upgrade` is registered at `SupportConsoleCommands.php:30` and its
signature is `livewire:upgrade {--run-only=}`.

`handle()` filters the pipeline with
`str($step)->afterLast('\\')->kebab()->is($this->option('run-only'))`, so a step
is addressed by the kebab-case of its class basename, and `Str::is()` wildcards
work. That option is the only reason this item could respect the decisions the
roadmap reserves for TODO 45 and TODO 51.

**The command has a shelf life.** It is a version 3 command: the Livewire 4
build in this machine's Composer cache carries `ConvertCommand.php` in its place
and no `UpgradeCommand.php` at all. Anyone repeating this measurement on a later
line should check that it is still there before planning around it.

### Never run the whole pipeline with `--no-interaction`

`UpgradeStep::interactiveReplacement()` prompts with
`$console->confirm('Would you like to apply these changes?', true)` - default
**true** - and `ChangeDefaultNamespace` / `ChangeDefaultLayoutView` prompt with
`'migrate'` as the default. Non-interactively the tool therefore says yes to
everything, including moving `app/Http/Livewire` to `app/Livewire` and rewriting
`config/livewire.php`'s `layout` key to `components.layouts.app` **without
moving the layout file**, which breaks all 19 route-mounted components.
Addressing one step at a time with `--run-only` makes `--no-interaction` safe,
because the default answer is then the intended one.

## The twenty steps

`Commands/Upgrade/` holds 21 files: the `UpgradeStep` base class and 20 steps,
19 of which are in the pipeline unconditionally (`ThirdPartyUpgradeNotice`
appears only when a package registers its own step).

| # | `--run-only` name | verdict here | count |
|---|---|---|---|
| 1 | `upgrade-introduction` | prose only | - |
| 2 | `change-default-namespace` | **REFUSED** - TODO 51's decision | 28 classes |
| 3 | `change-default-layout-view` | **REFUSED** - would break 19 route mounts | 1 config key |
| 4 | `add-live-modifier-to-wire-model-directives` | applied | **35** |
| 5 | `remove-defer-modifier-from-wire-model-directives` | applied | **88** |
| 6 | `change-lazy-to-blur-modifier-on-wire-model-directives` | applied | **8** |
| 7 | `add-live-modifier-to-entangle-directives` | no-op | 0 |
| 8 | `remove-defer-modifier-from-entangle-directives` | no-op | 0 |
| 9 | `remove-prevent-modifier-from-wire-submit-directive` | applied | **19** |
| 10 | `remove-prefetch-modifier-from-wire-click-directive` | no-op | 0 |
| 11 | `change-wire-load-directive-to-wire-init` | applied | **1** |
| 12 | `republish-navigation` | not applicable - no `wire:navigate` here | 0 |
| 13 | `change-test-assertion-methods` | deferred to TODO 43.1 | 8 of 74 |
| 14 | `change-forget-computed-to-unset` | no-op | 0 |
| 15 | `replace-temporary-uploaded-file-namespace` | deferred to TODO 49 | 1 |
| 16 | `replace-emit-with-dispatch` | deferred to TODO 44 / 47 | see below |
| 17 | `upgrade-config-instructions` | prose only - TODO 51 acts on it | - |
| 18 | `upgrade-alpine-instructions` | prose only | - |
| 19 | `clear-view-cache` | housekeeping | - |

### Pipeline order is load-bearing

`AddLiveModifierToWireModelDirectives`' pattern is
`/wire:model(?!\.(?:defer|lazy|live))/`, so it must run **before**
`RemoveDeferModifierFromWireModelDirectives`. Reversed, the 88 deferred bindings
would first lose `.defer` and then be marked `.live`, and the whole form layer
would become eager. The steps above were run in pipeline order.

### The 35, and why it is not 32

`resources/views` carries 131 `wire:model` occurrences: 88 `.defer`, 8 `.lazy`,
and 35 that match the step's pattern. The roadmap says 28 plain occurrences;
there are 32 plain ones, plus three that are not plain at all and that the tool
decided on our behalf:

- `resources/views/livewire/admin/settings.blade.php:353` and `:357` were
  `wire:model.ignore`, which has never been a real modifier in either version.
  They are now `wire:model.live.ignore`. Behaviour is preserved - an unknown
  modifier is ignored either way, and both spellings resolve to a live binding -
  but the latent bug crossed the version boundary intact.
- `resources/views/livewire/admin/translation.blade.php:63` was
  `wire:model.debounce.400ms` and is now `wire:model.live.debounce.400ms`, which
  is the correct version 3 spelling.

Neither modifier is mentioned anywhere in the roadmap's Phase 6 inventory.

The two service-day bindings TODO 42.1 pinned came out right, and this was
checked rather than assumed: `update-group-form.blade.php:488`, `:499` and `:518`
are all `wire:model.live`.

## `replace-emit-with-dispatch`, measured but not applied

Its `directories` are `['app', 'tests']` for the PHP forms and `['resources']`
for the Blade one. Six patterns, and what each would do here:

| pattern | matches here | note |
|---|---|---|
| `$this->emit(...)` | **0** | this application has no `$this->emit(` at all |
| `$this->emitTo('literal', ...)` | **19 of 21** | the first argument must be a quoted lowercase literal |
| `$this->emitSelf(...)` | 0 | |
| `$this->dispatchBrowserEvent(...)` | **102** | mechanical, and wrong for 98 of them |
| `$emit(...)` on `resources` | **0** | Blade here uses `$emitSelf` / `$emitTo` / `$emitUp`, which this pattern does not match |
| `emitUp` | replaced with the literal `<removed>` | a manual-action warning, not a rewrite |

Two reasons it is not applied here:

1. **It is not behaviour-preserving for the browser events.** Version 3's
   `dispatch($event, ...$params)` takes **named** parameters where
   `dispatchBrowserEvent($event, $array)` took an array, and **98 of the 102
   calls carry a second argument**. `public/js/modal.js` reads
   `event.detail.livewire` and `event.detail.parameters_back`, so a mechanical
   rename would leave the bridge reading fields that are no longer there.
2. **The two `emitTo()` calls it cannot match are exactly the ones the roadmap
   already flagged**: `app/Http/Livewire/Events/Modal.php:60` and `:419`, which
   pass a runtime property as the target. That the tool's own regex isolates
   them is a useful confirmation of TODO 44's "special case" line, arrived at
   from the pattern rather than from the documentation.

## `change-test-assertion-methods`, measured but not applied

Its four patterns are `assertEmitted`, `assertEmittedTo`, `assertNotEmitted` and
`assertEmittedUp`. It does **not** know about `assertDispatchedBrowserEvent` or
`assertNotDispatchedBrowserEvent`, which are 66 of this suite's 74 version 2
assertions. It would therefore handle **8 of 74** and leave the test layer
half-migrated. That layer moves as one unit, in TODO 43.1.

## What no step can reach

Every directive step defaults to `$directories = ['resources/views']`
(`UpgradeStep::interactiveReplacement()`), and the emit step adds `app`, `tests`
and `resources`. **`public/js` is in none of them**, so `public/js/modal.js` -
the generic modal bridge that receives the browser events - is outside the tool
entirely. TODO 46 owns it.

`config/livewire.php` is equally out of reach:
`UpgradeStep::publishConfigIfMissing()` publishes only when the file is
**absent**. Ours is present, so none of version 3's new keys arrives on its own
and `manifest_path` stays as a dead key. TODO 51.

## Suite measurements

| point | tests | assertions | errors | failures |
|---|---|---|---|---|
| before the bump | 1507 | 4200 | 0 | 0 |
| after the bump | 1507 | 3398 | 192 | 94 |
| after the five applied steps | 1507 | 3398 | 192 | 94 |

**The tool's 151 line changes moved the suite by nothing.** The tests that
assert on these directives never reach the assertion:
`LivewireComponentInteractionTest` checks 18 literal `wire:model.defer` strings
at `:177-194`, and the case dies at `:162` on a 403 where it expected a 302. The
Blade layer is downstream of the request layer, and the request layer is what
version 3 changed.

### Where the 286 come from

Grouped by the exception each one reports:

| cause | count | owner |
|---|---|---|
| `dispatchBrowserEvent` does not exist, across 12 components | ~127 | TODO 47 |
| HTTP 500 from `layouts/app.blade.php:65` (below) | 59 | TODO 50 |
| `emitUp` / `emitTo` do not exist | 21 | TODO 44 |
| `Property [$page] not found` | 17 | TODO 48 |
| `TestableLivewire` return type no longer exists | 7 | TODO 43.1 |
| `Property [$lastErrorBag]` / `[$lastRenderedDom]` not found | 4 | TODO 43.1 |
| `array_intersect_key(): Argument #1 must be of type array` | 4 | unattributed |
| route contract changed | 1 | TODO 51.1 |

Counts overlap where a single case reports more than one cause, so they do not
sum to 286 exactly.

### The single highest-leverage line in the phase

`resources/views/layouts/app.blade.php:65` reads:

```
<livewire:events.modal :groupId="0" :wire:key="events_modal">
```

`:wire:key` is a **bound** attribute, so version 3's tag compiler evaluates
`events_modal` as a PHP expression and raises `Undefined constant
"events_modal"`. That is a 500 on **every page rendered through the application
layout**, which is why the failure list contains files with no Livewire content
of their own - `SetLocaleTest`, `SetupFlowTest`, `CalendarRouteTest`,
`StaticPageAccessTest`, `AssetPipelineTest`.

**Control experiment, as run.** Changing that one attribute from `:wire:key` to
`wire:key` and re-running the suite reports **1507 tests, 3669 assertions, 193
errors, 34 failures** - 227 red instead of 286. One character is worth **59
tests and 271 assertions**. Reverted immediately; `git diff` empty. The fix
belongs to TODO 50, and this measurement is the argument for pulling that item
to the front of the phase rather than leaving it at position seven.

The other two `<livewire:...>` tags are not affected:
`livewire/events/modal.blade.php:246` and `livewire/home.blade.php:109` both bind
quoted PHP expressions. All three are still unclosed, which TODO 50 also owns.

## `vendor:publish --tag=livewire:assets` under version 3

The roadmap says the composer hook "breaks under Livewire 3". It does not. Run
against v3.8.8:

```
 INFO  Publishing [livewire:assets] assets.

  Copying directory [C:\laragon\www\kozter\vendor\livewire\livewire\dist] to
  [C:\laragon\www\kozter\public\vendor\livewire]  DONE
```

Exit code **0**. The tag is still registered at
`Mechanisms/FrontendAssets/FrontendAssets.php:40`, and `livewire:publish --assets`
calls it. The version 3 `dist/` ships six files - `livewire.esm.js`,
`livewire.esm.js.map`, `livewire.js`, `livewire.min.js`, `livewire.min.js.map`,
`manifest.json` - and **`livewire.js.map` is not among them**, so the version 2
map survived the copy as an orphan and had to be deleted by hand.
