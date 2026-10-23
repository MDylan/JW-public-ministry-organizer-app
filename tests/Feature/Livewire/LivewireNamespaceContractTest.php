<?php

namespace Tests\Feature\Livewire;

use Livewire\Component;
use Livewire\Mechanisms\ComponentRegistry;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\Feature\FeatureTestCase;

/**
 * TODO 51: the components live in App\Livewire, Livewire 3's default, and
 * config/livewire.php carries no Livewire 2 key.
 *
 * The move is the kind of change that fails quietly in the places a renderer
 * never reaches:
 *
 * - A view names a component by its dotted name, and Livewire turns that into a
 *   class under `class_namespace`. A stale namespace makes every tag an
 *   "Unable to find component" - but only on the page that renders it.
 * - vendor/composer/autoload_classmap.php is tracked (the release archive ships
 *   it) and optimized, so it lists every App class by path. A stale classmap
 *   still maps the old App\Http\Livewire names to files that no longer exist.
 * - Text and path references - a test reading a component's source by path,
 *   a docblock - do not fail at all; they just point at nothing.
 */
class LivewireNamespaceContractTest extends FeatureTestCase
{
    private const NAMESPACE = 'App\\Livewire';

    public function test_the_old_namespace_is_gone_from_the_tree(): void
    {
        $this->assertDirectoryDoesNotExist(app_path('Http/Livewire'));

        $old = 'App\\Http\\Livewire';
        $oldPath = 'app/Http/Livewire';
        $offenders = [];

        foreach (['app', 'config', 'routes', 'resources/views', 'tests'] as $root) {
            foreach ($this->files(base_path($root), ['.php']) as $path) {
                // This file names the old namespace on purpose.
                if (realpath($path) === __FILE__) {
                    continue;
                }

                $source = file_get_contents($path);

                if (str_contains($source, $old) || str_contains(str_replace('\\', '/', $source), $oldPath)) {
                    $offenders[] = $this->relative($path);
                }
            }
        }

        $this->assertSame([], $offenders, 'Still names the Livewire 2 namespace or its directory.');
    }

    public function test_the_tracked_classmap_maps_every_component_to_its_new_path(): void
    {
        $classmap = require base_path('vendor/composer/autoload_classmap.php');
        $stale = array_filter(
            $classmap,
            fn ($path, $class) => str_starts_with($class, 'App\\Http\\Livewire\\')
                || str_contains(str_replace('\\', '/', $path), '/app/Http/Livewire/'),
            ARRAY_FILTER_USE_BOTH
        );

        $this->assertSame([], array_keys($stale), 'Run composer dump-autoload: the classmap ships in the release archive.');

        $classes = $this->componentClasses();
        $this->assertCount(28, $classes);

        foreach ($classes as $class => $path) {
            $this->assertArrayHasKey($class, $classmap, "{$class} is missing from the classmap.");
            $this->assertSame(realpath($path), realpath($classmap[$class]));
        }
    }

    public function test_the_configuration_is_livewire_3_and_keeps_the_project_choices(): void
    {
        $this->assertSame(self::NAMESPACE, config('livewire.class_namespace'));
        $this->assertSame('components.admin-layout', config('livewire.layout')); // TODO 59
        $this->assertTrue(view()->exists(config('livewire.layout')), 'The full-page components render into this layout.');
        $this->assertSame('bootstrap', config('livewire.pagination_theme'));

        $source = file_get_contents(config_path('livewire.php'));

        // Livewire 2 keys that Livewire 3 no longer reads.
        foreach (['class_namespace', 'middleware_group', 'manifest_path', 'app_url', 'back_button_cache'] as $key) {
            $this->assertStringNotContainsString("'{$key}'", $source, "'{$key}' is not a Livewire 3 key the project sets.");
        }
    }

    public function test_every_component_a_view_names_resolves_to_a_class_in_the_namespace(): void
    {
        $registry = app(ComponentRegistry::class);
        $names = [];

        foreach ($this->files(resource_path('views'), ['.blade.php']) as $path) {
            $source = preg_replace('/\{\{--.*?--\}\}/s', '', file_get_contents($path));

            preg_match_all('/@livewire\(\s*([\'"])([^\'"]+)\1|<livewire:([a-zA-Z0-9._-]+)/', $source, $matches, PREG_SET_ORDER);

            foreach ($matches as $match) {
                // Group 3 only exists when the tag form matched.
                $names[$match[3] ?? $match[2]] = $this->relative($path);
            }
        }

        // Eight distinct names today; the floor only guards against the scan
        // silently finding nothing.
        $this->assertGreaterThanOrEqual(8, count($names));

        foreach ($names as $name => $view) {
            $class = ltrim($registry->getClass($name), '\\');

            $this->assertStringStartsWith(self::NAMESPACE.'\\', $class, "{$view}: {$name}");
            $this->assertTrue(class_exists($class), "{$view}: {$name} -> {$class}");
        }
    }

    public function test_every_component_class_round_trips_through_its_name(): void
    {
        $registry = app(ComponentRegistry::class);

        foreach (array_keys($this->componentClasses()) as $class) {
            $name = $registry->getName($class);

            $this->assertSame($class, ltrim($registry->getClass($name), '\\'), "{$class} -> {$name}");
        }
    }

    /**
     * Every concrete component under app/Livewire, keyed by class name.
     *
     * @return array<class-string, string>
     */
    private function componentClasses(): array
    {
        $classes = [];

        foreach ($this->files(app_path('Livewire'), ['.php']) as $path) {
            $relative = substr(str_replace('\\', '/', $path), strlen(str_replace('\\', '/', app_path('Livewire'))) + 1);
            $class = self::NAMESPACE.'\\'.str_replace('/', '\\', substr($relative, 0, -4));

            if (is_subclass_of($class, Component::class)) {
                $classes[$class] = $path;
            }
        }

        ksort($classes);

        return $classes;
    }

    /**
     * @param  list<string>  $suffixes
     * @return list<string>
     */
    private function files(string $root, array $suffixes): array
    {
        $files = [];

        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            /** @var SplFileInfo $file */
            foreach ($suffixes as $suffix) {
                if ($file->isFile() && str_ends_with($file->getFilename(), $suffix)) {
                    $files[] = $file->getPathname();
                    break;
                }
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
