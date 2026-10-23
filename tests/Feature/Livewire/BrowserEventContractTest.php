<?php

namespace Tests\Feature\Livewire;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\TestCase;

/**
 * TODO 47: browser events must reach JavaScript in the shape it reads.
 *
 * Livewire 3 removed dispatchBrowserEvent($name, $array). Its replacement,
 * dispatch($name, ...$params), turns its parameters into the event's
 * `detail`, so `dispatch('success', message: $m)` arrives as
 * `event.detail.message` - exactly what the receivers in
 * layouts/app.blade.php, public/js/modal.js and the component views read.
 *
 * THE TRAP. The obvious rename, `dispatch('success', ['message' => $m])`,
 * passes the array as ONE positional parameter. The browser then receives
 * `event.detail = [{message: ...}]`, every `event.detail.message` is
 * undefined, toastr prints "undefined" and modals stay shut - with no error
 * anywhere. The component tests cannot see it either: they assert event
 * names, and a name is dispatched just the same. This guard is what fails.
 *
 * HOW IT DRAWS THE LINE. Only events with a `window.addEventListener(...)`
 * receiver are checked, and the receiver list is read from the views and
 * public/js rather than written down here, so a new receiver is covered
 * without editing this file. Livewire-to-Livewire dispatches (TODO 44) may
 * pass positional parameters to listener methods and are left alone.
 *
 * WHAT IT CANNOT SEE: a dispatch whose event name is not a string literal.
 * There is none in app/ today.
 */
class BrowserEventContractTest extends TestCase
{
    public function test_dispatch_browser_event_is_gone_from_the_application(): void
    {
        $offenders = [];

        foreach ($this->phpFiles(app_path()) as $file) {
            foreach (file($file, FILE_IGNORE_NEW_LINES) as $number => $line) {
                if (str_contains($line, 'dispatchBrowserEvent')) {
                    $offenders[] = $this->relative($file).':'.($number + 1);
                }
            }
        }

        $this->assertSame([], $offenders, 'Livewire 3 has no dispatchBrowserEvent(); use dispatch() with named parameters.');
    }

    public function test_events_with_a_browser_receiver_are_dispatched_with_named_parameters(): void
    {
        $receivers = $this->browserReceivers();

        // If the receiver scan ever finds nothing, every dispatch below would
        // pass unchecked - so the scan has to prove it still works.
        foreach (['success', 'error', 'sweet-error', 'show-modal', 'hide-modal', 'show-deletion-confirmation'] as $known) {
            $this->assertContains($known, $receivers, "The receiver scan no longer finds '{$known}'.");
        }

        $checked = 0;
        $offenders = [];

        foreach ($this->phpFiles(app_path()) as $file) {
            $tokens = array_values(array_filter(
                token_get_all(file_get_contents($file)),
                fn ($t) => ! is_array($t) || ! in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)
            ));

            foreach ($tokens as $i => $token) {
                if (! $this->isDispatchCall($tokens, $i)) {
                    continue;
                }

                $name = $tokens[$i + 2];
                if (! is_array($name) || $name[0] !== T_CONSTANT_ENCAPSED_STRING
                    || ! in_array(substr($name[1], 1, -1), $receivers, true)) {
                    continue;
                }

                $checked++;

                if (! $this->onlyNamedArgumentsFollow($tokens, $i + 3)) {
                    $offenders[] = $this->relative($file).':'.$name[2].' '.$name[1];
                }
            }
        }

        $this->assertGreaterThan(0, $checked, 'No browser-event dispatch was found to check.');
        $this->assertSame(
            [],
            $offenders,
            'These browser events pass positional parameters, so JavaScript receives event.detail as an array. '
            .'Write dispatch(\'name\', key: $value) instead of dispatch(\'name\', [\'key\' => $value]).'
        );
    }

    /**
     * Event names that some page script listens for on window.
     *
     * @return list<string>
     */
    private function browserReceivers(): array
    {
        $files = array_merge(
            $this->files(resource_path('views'), '.blade.php'),
            glob(public_path('js').'/*.js') ?: []
        );

        $names = [];
        foreach ($files as $file) {
            preg_match_all('/window\.addEventListener\(\s*[\'"]([^\'"]+)[\'"]/', file_get_contents($file), $m);
            array_push($names, ...$m[1]);
        }

        return array_values(array_unique($names));
    }

    /**
     * True for `->dispatch(` - the component method, not a job's static
     * ::dispatch() or anything else that happens to share the name.
     */
    private function isDispatchCall(array $tokens, int $i): bool
    {
        return is_array($tokens[$i]) && $tokens[$i][0] === T_STRING && $tokens[$i][1] === 'dispatch'
            && is_array($tokens[$i - 1] ?? null) && $tokens[$i - 1][0] === T_OBJECT_OPERATOR
            && ($tokens[$i + 1] ?? null) === '(';
    }

    /**
     * Walks the arguments after the event name and requires each one to open
     * with `identifier:`. Starts at the token following the name.
     */
    private function onlyNamedArgumentsFollow(array $tokens, int $i): bool
    {
        $depth = 0;
        $atArgumentStart = false;

        for ($n = count($tokens); $i < $n; $i++) {
            $t = $tokens[$i];
            $s = is_array($t) ? $t[1] : $t;

            if ($depth === 0 && $s === ')') {
                return true;
            }

            if ($atArgumentStart) {
                $atArgumentStart = false;
                if (! (is_array($t) && $t[0] === T_STRING && ($tokens[$i + 1] ?? null) === ':')) {
                    return false;
                }
            }

            if (in_array($s, ['(', '[', '{'], true) || (is_array($t) && in_array($t[0], [T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES], true))) {
                $depth++;
            } elseif (in_array($s, [')', ']', '}'], true)) {
                $depth--;
            } elseif ($depth === 0 && $s === ',') {
                // a trailing comma is followed by ")", which ends the call above
                $atArgumentStart = ($tokens[$i + 1] ?? null) !== ')';
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function phpFiles(string $directory): array
    {
        return $this->files($directory, '.php');
    }

    /**
     * @return list<string>
     */
    private function files(string $directory, string $suffix): array
    {
        $found = [];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory, RecursiveDirectoryIterator::SKIP_DOTS));

        /** @var SplFileInfo $file */
        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), $suffix)) {
                $found[] = $file->getPathname();
            }
        }

        sort($found);

        return $found;
    }

    private function relative(string $path): string
    {
        return str_replace('\\', '/', substr($path, strlen(base_path()) + 1));
    }
}
