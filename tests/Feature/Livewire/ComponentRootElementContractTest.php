<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Events\Modal;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * TODO 47.2: a component's root element carries no wire:key.
 *
 * Found by the browser walk (TODO 47.1). Three views put a conditional poll
 * on their root together with a key:
 *
 *     <div @if($polling) wire:poll.30000ms wire:key="modal-polling" @endif>
 *
 * Under Livewire 2 the key change made morphdom re-initialise the poll.
 * Livewire 3 morphs the root like any other element, so a changed key makes it
 * REPLACE the root - and the new root belongs to no component, so the morph
 * throws "Could not find Livewire component in DOM tree" half-way through the
 * update. Opening the day modal did it three times, on the home page and on the
 * calendar. Nothing in PHPUnit renders a second time in a browser, so this is
 * pinned on the source.
 *
 * The root is identified by wire:id, which Livewire puts there itself; a key on
 * it can only ever disagree with that.
 */
class ComponentRootElementContractTest extends TestCase
{
    public function test_no_component_view_puts_a_wire_key_on_its_root(): void
    {
        $views = $this->componentViews();

        // 32 today; the floor guards against the scan finding nothing.
        $this->assertGreaterThanOrEqual(30, count($views));

        $offenders = [];

        foreach ($views as $name => $path) {
            $root = $this->rootTag(file_get_contents($path));

            $this->assertNotNull($root, "{$name} has no root element.");

            if (str_contains($root, 'wire:key')) {
                $offenders[] = $name.': '.$root;
            }
        }

        $this->assertSame([], $offenders, 'Livewire 3 replaces a root whose key changes; the root is keyed by wire:id already.');
    }

    public function test_the_day_modal_polls_from_its_own_keyed_element(): void
    {
        // $polling starts false here, and Livewire 3 registers wire:poll only
        // when an element initialises, so a poll attribute that appears on the
        // root later would never start. A fresh keyed element does start, and
        // stops once removed. Home and the calendar start with polling on, so
        // their root poll is registered at load and merely pauses while off.
        $this->assertFalse((new Modal())->polling);

        $source = $this->withoutBladeComments(file_get_contents(resource_path('views/livewire/events/modal.blade.php')));

        $this->assertStringNotContainsString('wire:poll', $this->rootTag($source));
        $this->assertMatchesRegularExpression(
            '/@if\(\$polling\)\s*<div wire:poll\.30000ms wire:key="modal-polling"><\/div>\s*@endif/',
            $source
        );
    }

    /**
     * Every view a component renders, by its dotted name: the `view('livewire.…')`
     * calls in app/Livewire.
     *
     * @return array<string, string>
     */
    private function componentViews(): array
    {
        $views = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(app_path('Livewire'), RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            /** @var SplFileInfo $file */
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            preg_match_all('/view\(\s*[\'"](livewire\.[a-z0-9._-]+)[\'"]/', file_get_contents($file->getPathname()), $matches);

            foreach ($matches[1] as $name) {
                $views[$name] = view()->getFinder()->find($name);
            }
        }

        ksort($views);

        return $views;
    }

    /**
     * The opening tag of the first HTML element, Blade comments removed.
     */
    private function rootTag(string $source): ?string
    {
        $source = $this->withoutBladeComments($source);

        return preg_match('/<[a-zA-Z][^>]*>/', $source, $match) ? $match[0] : null;
    }

    private function withoutBladeComments(string $source): string
    {
        return preg_replace('/\{\{--.*?--\}\}/s', '', $source);
    }
}
