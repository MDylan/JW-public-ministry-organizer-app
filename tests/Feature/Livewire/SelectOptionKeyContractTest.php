<?php

namespace Tests\Feature\Livewire;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * TODO 47.4: a bound <select> keys the options it renders in a loop.
 *
 * Found by the browser walk (TODO 47.1) on the group form: moving Monday's
 * start to 12:00 re-rendered the end-time options, the end stayed 19:00 on the
 * server - and the select showed 23:30. Livewire 3's morph patches unkeyed
 * <option> elements in place, by position: the 24th option used to say 19:00
 * and now says 23:30, and the browser keeps the selection on that element.
 * wire:model only rewrites the select when its property changes, which here
 * it did not. Livewire 2 rewrote every bound field from the component's data
 * after each response, which hid this.
 *
 * With wire:key on each option the morph matches options by value, so the
 * selected one stays selected. The rule covers every bound select whose
 * options come from @foreach - for a fixed list the key is harmless, and a
 * list that becomes dynamic later is covered already.
 */
class SelectOptionKeyContractTest extends TestCase
{
    public function test_every_looped_option_of_a_bound_select_has_a_wire_key(): void
    {
        $selects = 0;
        $offenders = [];

        foreach ($this->views() as $path) {
            $source = preg_replace('/\{\{--.*?--\}\}/s', '', file_get_contents($path));

            // Plain string scanning: some opening tags in these views are not
            // well-formed HTML (an extra quote), and Blade expressions contain '>'.
            foreach (array_slice(explode('<select', $source), 1) as $chunk) {
                $select = strstr($chunk, '</select>', true);
                if ($select === false) {
                    continue;
                }

                $opening = strstr($select, '<option', true) ?: $select;
                if (! str_contains($opening, 'wire:model') || ! str_contains($select, '@foreach')) {
                    continue;
                }

                $selects++;

                preg_match_all('/@foreach.*?@endforeach/s', $select, $loops);
                foreach ($loops[0] as $loop) {
                    preg_match_all('/<option\b[^\n]*/', $loop, $options);
                    foreach ($options[0] as $option) {
                        if (! str_contains($option, 'wire:key=')) {
                            $offenders[] = $this->relative($path).': '.trim($option);
                        }
                    }
                }
            }
        }

        // 28 today; the floor guards against the scan finding nothing.
        $this->assertGreaterThanOrEqual(28, $selects);
        $this->assertSame([], $offenders, 'Livewire 3 patches unkeyed options in place, so the select can show a value the component does not hold.');
    }

    /**
     * @return list<string>
     */
    private function views(): array
    {
        $views = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views/livewire'), RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            /** @var SplFileInfo $file */
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $views[] = $file->getPathname();
            }
        }

        sort($views);

        return $views;
    }

    private function relative(string $path): string
    {
        return str_replace('\\', '/', substr($path, strlen(base_path()) + 1));
    }
}
