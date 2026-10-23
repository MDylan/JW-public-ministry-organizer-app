<?php

namespace Tests\Feature\Livewire;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * TODO 47.3: no inline <script> appears inside a Blade conditional or loop in
 * component markup.
 *
 * Livewire 2 ran every <script> element a re-render ADDED to the page - its
 * node initialiser eval()-ed them once the initial render was finished.
 * Livewire 3 does not: a script that arrives with a morph sits in the DOM and
 * never runs. A script that is part of the first render still runs, because
 * the browser parses it with the page; only one that appears LATER - behind
 * an @if that turns true, or in a loop that grows - is silently skipped.
 *
 * The browser walk (TODO 47.1) found it on the poster editor, whose summernote
 * never started, and the calendar's weather widget had the same shape: its
 * scroll buttons were bound by a script inside @if(weather_enabled), which a
 * group switch renders through Livewire.
 *
 * So the rule is structural: in a component view, outside @section/@push
 * blocks, a <script> must not be nested in @if/@foreach/... at all. Code that
 * has to run when markup appears is started by a browser event the component
 * dispatches (dispatched events run after the morph).
 */
class InlineScriptContractTest extends TestCase
{
    private const OPENERS = ['if', 'unless', 'isset', 'foreach', 'forelse', 'for', 'while', 'switch', 'auth', 'guest', 'can', 'cannot', 'env', 'production'];

    public function test_no_inline_script_is_rendered_conditionally_in_component_markup(): void
    {
        $offenders = [];
        $scripts = 0;

        foreach ($this->componentViews() as $path) {
            $source = $this->markupOnly(file_get_contents($path));

            preg_match_all('/<script\b(?![^>]*\bsrc=)[^>]*>/i', $source, $matches, PREG_OFFSET_CAPTURE);

            foreach ($matches[0] as [, $offset]) {
                $scripts++;

                if ($this->depthAt($source, $offset) > 0) {
                    $offenders[] = $this->relative($path).':'.(substr_count(substr($source, 0, $offset), "\n") + 1);
                }
            }
        }

        // Three unconditional ones today (home, calendar, group statistics);
        // the floor guards against the scan finding nothing.
        $this->assertGreaterThanOrEqual(3, $scripts);
        $this->assertSame([], $offenders, 'Livewire 3 does not run a <script> that a re-render adds; start the code from a dispatched browser event instead.');
    }

    /**
     * How many Blade blocks are open at $offset.
     */
    private function depthAt(string $source, int $offset): int
    {
        $openers = implode('|', self::OPENERS);

        preg_match_all('/@(end)?('.$openers.')\b/', substr($source, 0, $offset), $matches, PREG_SET_ORDER);

        $depth = 0;
        foreach ($matches as $match) {
            $depth += $match[1] === 'end' ? -1 : 1;
        }

        return $depth;
    }

    /**
     * The view with Blade comments and @section / @push bodies blanked out line
     * for line: those land in the layout's footer and run with the page.
     */
    private function markupOnly(string $source): string
    {
        $blank = fn (array $m) => preg_replace('/[^\n]/', ' ', $m[0]);

        $source = preg_replace_callback('/\{\{--.*?--\}\}/s', $blank, $source);

        return preg_replace_callback('/@(section|push)\(.*?@(endsection|endpush|stop)\b/s', $blank, $source);
    }

    /**
     * @return list<string>
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

        return array_values($views);
    }

    private function relative(string $path): string
    {
        return str_replace('\\', '/', substr($path, strlen(base_path()) + 1));
    }
}
