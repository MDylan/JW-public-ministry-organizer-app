<?php

namespace Tests\Feature\Livewire;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * TODO 45 / 45.1: the wire:model bindings after Livewire 3's inversion.
 *
 * Livewire 2's plain `wire:model` was live; Livewire 3's is deferred until
 * the next action. The upgrade tool marked every formerly plain binding
 * `.live`, which preserves behaviour. Reviewed one by one, 23 of those 35
 * really need it - a view branch on the value, a render() that derives
 * option lists from it, or an updated*() hook that resets a filter. The
 * other 12 stay `.live` too, by decision: that is exactly the old behaviour.
 * What this guards is the direction that breaks silently: a needed binding
 * turned deferred renders a form that looks fine and stops reacting.
 *
 * Modifiers are restricted to what Livewire 3 understands (checked in
 * dist/livewire.esm.js, the wire:model directive): `.live`, `.change`
 * (commits on the change event - v2's `.lazy` exactly), `.debounce.Nms`.
 * Anything else is handed to Alpine's x-model and ignored, which is how
 * `wire:model.ignore` survived two versions as a no-op. `.blur` is refused
 * as well: on a select it sends nothing until focus leaves.
 */
class WireModelBindingContractTest extends TestCase
{
    /**
     * view (under resources/views/livewire) => [bound expression => why it must be live]
     */
    private const MUST_BE_LIVE = [
        'admin/settings.blade.php' => [
            'state.env.MAIL_MAILER' => '@if shows the SMTP fields',
        ],
        'admin/static-page-edit.blade.php' => [
            'state.position' => '@if shows the icon field',
            'state.icon' => 'live icon preview',
        ],
        'admin/translation.blade.php' => [
            'sourceLocale' => 'updatedSourceLocale() resets the page',
            'targetLocale' => 'updatedTargetLocale() resets group and page',
            'group' => 'updatedGroup() resets the page',
            'search' => 'updatedSearch(), wire:loading on search',
            'onlyMissing' => 'updatedOnlyMissing() resets the page',
        ],
        'admin/users/list-users.blade.php' => [
            'searchTerm' => 'updatedSearchTerm() resets the page',
        ],
        'groups/list-users.blade.php' => [
            'searchTerm' => 'updatedSearchTerm() resets the page',
        ],
        'groups/special-date-modal.blade.php' => [
            'state.date_status' => 'disables the other fields',
            'state.date_min_time' => 'render() rebuilds the time lists',
            'state.date_start' => 'render() rebuilds the time lists',
            'state.date_end' => 'render() rebuilds the time lists',
        ],
        'groups/statistics.blade.php' => [
            'filter_sub_group' => 'render() filters on it, no submit',
            'filter_all_event' => 'render() filters on it, no submit',
        ],
        'groups/update-group-form.blade.php' => [
            'state.min_time' => 'render() rebuilds the day lists',
            'state.signs.{{$sign}}.checked' => '@if shows the sign fields',
            'state.weather_enabled' => '@if shows city and country',
            'days.{{$day}}.day_number' => 'render() clamps the day (TODO 42.1)',
            'days.{{$day}}.start_time' => 'render() clamps the day (TODO 42.1)',
            'days.{{$day}}.end_time' => 'render() clamps the day (TODO 42.1)',
            'state.messages_on' => '@if shows the message settings',
        ],
    ];

    public function test_the_bindings_the_ui_reacts_to_stay_live(): void
    {
        $missing = [];
        $checked = 0;

        foreach (self::MUST_BE_LIVE as $view => $bindings) {
            $source = file_get_contents(resource_path('views/livewire/'.$view));

            foreach ($bindings as $expression => $why) {
                $checked++;
                $pattern = '/wire:model\.live(?:\.[\w]+)*="'.preg_quote($expression, '/').'"/';

                if (! preg_match($pattern, $source)) {
                    $missing[] = "{$view} {$expression} ({$why})";
                }
            }
        }

        $this->assertSame(23, $checked);
        $this->assertSame([], $missing, 'These bindings drive what the view shows; deferred, the form stops reacting.');
    }

    public function test_only_modifiers_livewire_3_understands_are_used(): void
    {
        $offenders = [];
        $seen = 0;

        foreach ($this->views() as $file => $source) {
            preg_match_all('/wire:model((?:\.[\w-]+)*)=/', $source, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);

            foreach ($matches as $match) {
                $seen++;
                $modifiers = array_filter(explode('.', $match[1][0]));

                foreach ($modifiers as $modifier) {
                    if (! in_array($modifier, ['live', 'change', 'debounce'], true) && ! preg_match('/^\d+ms$/', $modifier)) {
                        $offenders[] = $file.':'.(substr_count(substr($source, 0, $match[0][1]), "\n") + 1).' '.$match[0][0];
                        break;
                    }
                }
            }

            foreach (['wire:submit.prevent', 'wire:model.defer', 'wire:model.lazy'] as $removed) {
                if (str_contains($source, $removed)) {
                    $offenders[] = $file.' '.$removed;
                }
            }
        }

        // 35 live, 8 change and 88 plain bindings at TODO 45; far fewer means
        // the scan has stopped seeing them.
        $this->assertGreaterThan(100, $seen);
        $this->assertSame([], $offenders, 'Livewire 3 ignores this modifier, or removed it.');
    }

    /**
     * @return array<string, string> path from the project root => source,
     *                               Blade comments blanked line for line
     */
    private function views(): array
    {
        $views = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views'))) as $file) {
            /** @var SplFileInfo $file */
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $views[str_replace('\\', '/', substr($file->getPathname(), strlen(base_path()) + 1))] = preg_replace_callback(
                    '/\{\{--.*?--\}\}/s',
                    fn ($comment) => str_repeat("\n", substr_count($comment[0], "\n")),
                    file_get_contents($file->getPathname())
                );
            }
        }

        ksort($views);

        return $views;
    }
}
