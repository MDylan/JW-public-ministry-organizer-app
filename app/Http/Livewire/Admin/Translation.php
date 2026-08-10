<?php

namespace App\Http\Livewire\Admin;

use App\Http\Livewire\AppComponent;
use App\Support\Translation\LangFiles;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Throwable;

/**
 * TODO 33.3: the in-house translation editor.
 *
 * Until this change set the component was an eighteen-line shim that read the
 * settings.languages row and linked out - with two hardcoded URLs - to the Vue
 * front-end of joedixon/laravel-translation. The package is gone; this is the
 * editor.
 *
 * WHAT IT SHOWS
 *
 * The key list is the UNION of the source locale and the target locale, so a
 * key the target is missing appears with an empty box rather than not at all.
 * With this many locales and only a couple of complete ones, that is the whole
 * practical value of the screen.
 *
 * Only locales registered in settings.languages appear - see LangFiles for why,
 * and for the consequence: a directory that exists on disk but is not
 * registered is invisible here until an administrator adds it on the settings
 * screen, which now creates the directory half too.
 *
 * WHY THE STATE IS ONE PAGE WIDE
 *
 * $rows holds the CURRENT PAGE only, rebuilt from disk on every render. Livewire
 * serializes public properties into the response, and the root JSON group runs
 * to several hundred keys - carrying all of them through every request would be
 * a large payload for no benefit. The consequence is worth knowing: changing
 * page, locale, group or the filter without saving discards the edits on
 * screen, and the view says so.
 *
 * WHY EACH ROW CARRIES A PATH
 *
 * A key is addressed by its structural path, never by its dotted label. Many
 * keys in this project contain a literal dot - in the root JSON files, where
 * the keys are English sentences, roughly one in five - so the label is
 * ambiguous by construction. LangFiles carries the measurement.
 *
 * The path arrives back from the browser, so save() checks it against the file
 * before writing: an unknown path is refused unless it came from the source
 * locale, which is exactly the "fill in a missing translation" case.
 */
class Translation extends AppComponent
{
    public $targetLocale = '';

    public $sourceLocale = '';

    public $group = '';

    public $search = '';

    public $onlyMissing = false;

    /**
     * The current page of the key list. See the class docblock.
     *
     * @var array<int, array{key: string, path: array, source: string, value: string}>
     */
    public $rows = [];

    /**
     * Identifies which slice $rows currently holds.
     *
     * render() rebuilds $rows from disk only when this changes. Without it
     * every render would overwrite what the browser just sent, so an edit could
     * never survive long enough to be saved - and a render triggered by
     * something else entirely would silently discard work in progress.
     */
    public $rowsToken = '';

    protected $queryString = [
        'targetLocale' => ['except' => ''],
        'group' => ['except' => ''],
        'search' => ['except' => ''],
    ];

    /**
     * The editor's own authorization check, on top of the route's gate.
     *
     * Not redundant, and measured: a Livewire action does NOT travel over the
     * route it was rendered from - it POSTs to livewire/message, whose group is
     * only `web`. What re-applies `can:is-translator` there is
     * Livewire::getPersistentMiddleware(), a hardcoded list inside the package.
     * Removing `Illuminate\Auth\Middleware\Authorize` from that list was tried:
     * a plain `registered` user could then call save() over HTTP and rewrite a
     * language file, answering 200.
     *
     * So the whole protection rests on a framework internal that Livewire 3
     * reworks. This method makes the component refuse on its own terms, which
     * costs one gate call per action and survives that change.
     */
    private function authorizeTranslator(): void
    {
        Gate::authorize('is-translator');
    }

    public function mount(): void
    {
        $this->authorizeTranslator();

        $files = $this->files();
        $locales = $files->locales();

        $default = (string) config('app.locale');

        $this->sourceLocale = array_key_exists($default, $locales)
            ? $default
            : (string) array_key_first($locales);

        if (! array_key_exists($this->targetLocale, $locales)) {
            $this->targetLocale = $this->sourceLocale;
        }

        $groups = $files->groups($this->targetLocale);

        if (! in_array($this->group, $groups, true)) {
            $this->group = $groups[0] ?? LangFiles::JSON_GROUP;
        }
    }

    private function files(): LangFiles
    {
        return app(LangFiles::class);
    }

    // =========================================================================
    // Selectors
    // =========================================================================

    public function updatedTargetLocale(): void
    {
        $groups = $this->files()->groups($this->targetLocale);

        if (! in_array($this->group, $groups, true)) {
            $this->group = $groups[0] ?? LangFiles::JSON_GROUP;
        }

        $this->resetPage();
    }

    public function updatedGroup(): void
    {
        $this->resetPage();
    }

    public function updatedSourceLocale(): void
    {
        $this->resetPage();
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedOnlyMissing(): void
    {
        $this->resetPage();
    }

    public function clearSearch(): void
    {
        $this->search = '';
        $this->resetPage();
    }

    // =========================================================================
    // Saving
    // =========================================================================

    public function save(int $index): void
    {
        $this->authorizeTranslator();

        $row = $this->rows[$index] ?? null;

        if (! is_array($row) || ! isset($row['path']) || ! is_array($row['path'])) {
            $this->dispatchBrowserEvent('error', ['message' => __('translation.save_failed')]);

            return;
        }

        try {
            $this->writeRow($row);
        } catch (Throwable $e) {
            $this->dispatchBrowserEvent('error', ['message' => __('translation.save_failed')]);

            return;
        }

        $this->dispatchBrowserEvent('success', ['message' => __('translation.saved')]);
    }

    public function saveAll(): void
    {
        $this->authorizeTranslator();

        $saved = 0;

        foreach ($this->rows as $row) {
            if (! is_array($row) || ! isset($row['path']) || ! is_array($row['path'])) {
                continue;
            }

            try {
                $saved += $this->writeRow($row) ? 1 : 0;
            } catch (Throwable $e) {
                $this->dispatchBrowserEvent('error', ['message' => __('translation.save_failed')]);

                return;
            }
        }

        $this->dispatchBrowserEvent('success', [
            'message' => trans_choice('translation.saved_count', $saved, ['count' => $saved]),
        ]);
    }

    /**
     * @param  array{path: array, value?: string}  $row
     * @return bool whether the file was actually rewritten
     */
    private function writeRow(array $row): bool
    {
        $files = $this->files();
        $path = array_values($row['path']);

        // The path came back from the browser. It is legitimate when it already
        // exists in the target, or when it exists in the source and the target
        // is missing it - which is the reason the empty boxes are on screen.
        $known = $files->has($this->targetLocale, $this->group, $path)
            || $files->has($this->sourceLocale, $this->group, $path);

        if (! $known) {
            throw new \RuntimeException('Unknown translation path.');
        }

        $value = (string) ($row['value'] ?? '');

        return $files->write($this->targetLocale, $this->group, $path, $value);
    }

    public function addKey(): void
    {
        $this->authorizeTranslator();

        $validated = Validator::make($this->state, [
            'newKey' => 'required|string|max:255',
            'newValue' => 'nullable|string',
        ])->validate();

        try {
            $this->files()->addKey(
                $this->targetLocale,
                $this->group,
                $validated['newKey'],
                (string) ($validated['newValue'] ?? '')
            );
        } catch (Throwable $e) {
            $this->dispatchBrowserEvent('error', ['message' => __('translation.key_exists')]);

            return;
        }

        $this->state['newKey'] = '';
        $this->state['newValue'] = '';

        // The list gained a row, so it has to come from disk again.
        $this->rowsToken = '';

        $this->dispatchBrowserEvent('success', ['message' => __('translation.key_added')]);
    }

    // =========================================================================
    // Rendering
    // =========================================================================

    public function render()
    {
        $files = $this->files();
        $locales = $files->locales();

        // A locale can disappear from the registry between two requests.
        if (! array_key_exists($this->targetLocale, $locales)) {
            $this->targetLocale = (string) array_key_first($locales);
        }

        if (! array_key_exists($this->sourceLocale, $locales)) {
            $this->sourceLocale = $this->targetLocale;
        }

        $groups = $files->groups($this->targetLocale);

        if (! in_array($this->group, $groups, true)) {
            $this->group = $groups[0] ?? LangFiles::JSON_GROUP;
        }

        $all = $this->entries($files);
        $entries = $this->filter($all);

        $perPage = 25;
        $page = max(1, (int) $this->page);
        $slice = array_slice($entries, ($page - 1) * $perPage, $perPage);

        $token = md5(serialize([
            $this->sourceLocale,
            $this->targetLocale,
            $this->group,
            $this->search,
            $this->onlyMissing,
            $page,
        ]));

        if ($this->rowsToken !== $token) {
            $this->rows = $slice;
            $this->rowsToken = $token;
        }

        $paginator = new LengthAwarePaginator(
            $slice,
            count($entries),
            $perPage,
            $page,
            ['path' => request()->url(), 'query' => request()->query()]
        );

        return view('livewire.admin.translation', [
            'locales' => $locales,
            'groups' => $groups,
            'paginator' => $paginator,
            'missingCount' => $this->missingCount($all),
        ]);
    }

    /**
     * The union of the source and target keys, source order first.
     *
     * @return array<int, array{key: string, path: array, source: string, value: string}>
     */
    private function entries(LangFiles $files): array
    {
        $target = [];
        foreach ($files->read($this->targetLocale, $this->group) as $entry) {
            $target[$this->pathKey($entry['path'])] = $entry;
        }

        $source = [];
        if ($this->sourceLocale !== $this->targetLocale) {
            $source = $files->read($this->sourceLocale, $this->group);
        }

        $entries = [];
        $seen = [];

        foreach ($source as $entry) {
            $id = $this->pathKey($entry['path']);
            $seen[$id] = true;

            $entries[] = [
                'key' => $entry['key'],
                'path' => $entry['path'],
                'source' => $entry['value'],
                'value' => $target[$id]['value'] ?? '',
            ];
        }

        foreach ($target as $id => $entry) {
            if (isset($seen[$id])) {
                continue;
            }

            $entries[] = [
                'key' => $entry['key'],
                'path' => $entry['path'],
                'source' => '',
                'value' => $entry['value'],
            ];
        }

        return $entries;
    }

    /**
     * @param  array<int, array{key: string, source: string, value: string}>  $entries
     */
    private function filter(array $entries): array
    {
        $needle = trim((string) $this->search);

        return array_values(array_filter($entries, function (array $entry) use ($needle) {
            if ($this->onlyMissing && $entry['value'] !== '') {
                return false;
            }

            if ($needle === '') {
                return true;
            }

            return Str::contains(Str::lower($entry['key'].' '.$entry['source'].' '.$entry['value']), Str::lower($needle));
        }));
    }

    /**
     * How many keys of this group the target locale has no value for. This is
     * the number a translator actually works from.
     *
     * @param  array<int, array{value: string}>  $entries  the unfiltered union
     */
    private function missingCount(array $entries): int
    {
        if ($this->sourceLocale === $this->targetLocale) {
            return 0;
        }

        $missing = 0;

        foreach ($entries as $entry) {
            if ($entry['value'] === '') {
                $missing++;
            }
        }

        return $missing;
    }

    /**
     * An unambiguous identifier for a structural path. A dotted label is not
     * one - "token.title" can be a literal key or a nested pair.
     *
     * @param  array<int, string|int>  $path
     */
    private function pathKey(array $path): string
    {
        return implode("\x00", array_map('strval', $path));
    }
}
