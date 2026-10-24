<?php

namespace Tests\Unit;

use App\Http\Livewire\Concerns\LocksProperties;
use Livewire\Component;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * Roadmap TODO 70.1: a Livewire component's id properties are locked.
 *
 * Every public property is the client's to rewrite on the next request, and
 * the route middleware does not run on component actions. TODO 70 found that
 * a dozen components trusted such ids - a group, an event, a news item, the
 * user being removed - and acted on whatever the client put there; the v1
 * line has the same components. Livewire 2 has no #[Locked], so components
 * use LocksProperties and list the server-owned ones in $lockedProperties.
 *
 * This holds every component in app/Http/Livewire to the rule: a public
 * property named like an id (`id`, `*Id`, `*_id`, `*Ids`, `*_ids`,
 * `*Beeing*`) is in that list, or listed below with the reason it may not
 * be - always that a form input is bound to it, and the value is authorized
 * where it is used.
 */
class LivewireLockedPropertiesTest extends TestCase
{
    private const UNLOCKED_BY_DESIGN = [
        // The calendar's group select (wire:model); changeGroup() accepts
        // only one of the user's accepted groups, and the day modal it is
        // passed to checks the membership itself.
        'App\Http\Livewire\Events\Events::$form_groupId',
        // The parent-group select of the member list (wire:model);
        // linkToGroup() requires an accepted admin of BOTH groups.
        'App\Http\Livewire\Groups\ListUsers::$new_parent_group_id',
    ];

    public function test_every_id_like_public_property_is_locked(): void
    {
        $unlocked = [];
        $checked = 0;

        foreach ($this->components() as $class) {
            $reflection = new ReflectionClass($class);
            $locked = in_array(LocksProperties::class, class_uses_recursive($class), true)
                ? ($reflection->getDefaultProperties()['lockedProperties'] ?? [])
                : [];

            foreach ($reflection->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                if ($property->isStatic() || str_starts_with($property->getDeclaringClass()->getName(), 'Livewire\\')) {
                    continue;
                }
                if (! preg_match('/^id$|Id$|_id$|Ids$|_ids$|Beeing/', $property->getName())) {
                    continue;
                }

                $checked++;
                $name = $class.'::$'.$property->getName();
                if (! in_array($property->getName(), $locked, true) && ! in_array($name, self::UNLOCKED_BY_DESIGN, true)) {
                    $unlocked[] = $name;
                }
            }
        }

        $this->assertGreaterThan(20, $checked, 'The components were not read.');
        $this->assertSame([], $unlocked, 'Add these to the component\'s $lockedProperties (LocksProperties), or list them with the reason they cannot be.');
    }

    public function test_the_exceptions_still_exist(): void
    {
        foreach (self::UNLOCKED_BY_DESIGN as $name) {
            [$class, $property] = explode('::$', $name);
            $this->assertTrue(property_exists($class, $property), "{$name} is gone; drop it from the list.");
        }
    }

    /** @return list<class-string<Component>> */
    private function components(): array
    {
        $root = dirname(__DIR__, 2).'/app/Http/Livewire';
        $classes = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() !== 'php' || str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'Concerns'.DIRECTORY_SEPARATOR)) {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($root) + 1, -4);
            $class = 'App\\Http\\Livewire\\'.str_replace(['/', '\\'], '\\', $relative);
            if (class_exists($class) && is_subclass_of($class, Component::class) && ! (new ReflectionClass($class))->isAbstract()) {
                $classes[] = $class;
            }
        }

        $this->assertGreaterThan(25, count($classes));

        return $classes;
    }
}
