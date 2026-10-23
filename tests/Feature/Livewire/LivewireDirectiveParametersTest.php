<?php

namespace Tests\Feature\Livewire;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * TODO 50.1: the parameters of an `@livewire(...)` directive are an array.
 *
 * `@livewire('name', $params, key(...))` hands its second argument to the
 * child's mount. Livewire 2 tolerated a string there; Livewire 3 passes it to
 * SupportNestingComponents::setParametersToMatchingProperties(), whose
 * array_intersect_key() throws on anything that is not an array. That is how
 * `@livewire('groups.poster-edit-modal', '', ...)` in the calendar view went
 * unnoticed: it renders only for admins and rolers, so it was a 500 for
 * exactly those users and for the four tests that act as one.
 *
 * The rule is checked on the source, not on a render, so it covers the
 * directives behind role checks too. A second argument has to be an array
 * literal; a variable is refused as well, because what it holds cannot be
 * read from here. Blade comments are skipped.
 */
class LivewireDirectiveParametersTest extends TestCase
{
    public function test_every_livewire_directive_passes_its_parameters_as_an_array(): void
    {
        $checked = 0;
        $offenders = [];

        foreach ($this->bladeFiles(resource_path('views')) as $file) {
            // Blank the comments out but keep their line breaks, so the
            // reported line numbers stay those of the file.
            $source = preg_replace_callback(
                '/\{\{--.*?--\}\}/s',
                fn ($comment) => str_repeat("\n", substr_count($comment[0], "\n")),
                file_get_contents($file)
            );

            preg_match_all(
                '/@livewire\(\s*([\'"])[^\'"]+\1\s*(?:,\s*(\S))?/',
                $source,
                $matches,
                PREG_SET_ORDER | PREG_OFFSET_CAPTURE
            );

            foreach ($matches as $match) {
                if (! isset($match[2])) {
                    continue;
                }

                $checked++;

                if ($match[2][0] !== '[') {
                    $line = substr_count(substr($source, 0, $match[0][1]), "\n") + 1;
                    $offenders[] = $this->relative($file).':'.$line;
                }
            }
        }

        // events-bar, poster-edit-modal and special-date-modal pass parameters
        // today; if the scan finds fewer, it has stopped working.
        $this->assertGreaterThanOrEqual(3, $checked, 'The @livewire scan no longer finds the directives that pass parameters.');
        $this->assertSame([], $offenders, 'Pass @livewire parameters as an array literal - Livewire 3 refuses a string.');
    }

    /**
     * @return list<string>
     */
    private function bladeFiles(string $directory): array
    {
        $files = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory)) as $file) {
            /** @var SplFileInfo $file */
            if ($file->isFile() && str_ends_with($file->getFilename(), '.blade.php')) {
                $files[] = $file->getPathname();
            }
        }

        sort($files);

        return $files;
    }

    private function relative(string $path): string
    {
        return str_replace('\\', '/', substr($path, strlen(base_path()) + 1));
    }
}
