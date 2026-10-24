<?php

namespace Tests\Unit;

use Livewire\Attributes\Locked;
use Livewire\Component;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionProperty;

/**
 * Roadmap TODO 70: a Livewire component's id properties are #[Locked].
 *
 * Every public property a component does not lock is the client's to
 * rewrite on the next request, and the route middleware does not run on
 * component actions. TODO 70 found that a dozen components trusted such ids
 * - a group, an event, a news item, the user being removed - and acted on
 * whatever the client put there. This holds every component in app/Livewire
 * to the rule: a public property named like an id (`id`, `*Id`, `*_id`,
 * `*Ids`, `*_ids`, `*Beeing*`) is locked, or listed below with the reason it
 * may not be - which is always that a form input is bound to it, and the
 * value is authorized where it is used.
 */
class LivewireLockedPropertiesTest extends TestCase
{
    private const UNLOCKED_BY_DESIGN = [
        // The calendar's group select (wire:model); changeGroup() accepts
        // only one of the user's accepted groups, and the day modal it is
        // passed to checks the membership itself.
        'App\Livewire\Events\Events::$form_groupId',
        // The parent-group select of the member list (wire:model.live);
        // linkToGroup() requires an accepted admin of BOTH groups.
        'App\Livewire\Groups\ListUsers::$new_parent_group_id',
    ];

    public function test_every_id_like_public_property_is_locked(): void
    {
        $unlocked = [];
        $checked = 0;

        foreach ($this->components() as $class) {
            foreach ((new ReflectionClass($class))->getProperties(ReflectionProperty::IS_PUBLIC) as $property) {
                if ($property->isStatic() || str_starts_with($property->getDeclaringClass()->getName(), 'Livewire\\')) {
                    continue;
                }
                if (! preg_match('/^id$|Id$|_id$|Ids$|_ids$|Beeing/', $property->getName())) {
                    continue;
                }

                $checked++;
                $name = $class.'::$'.$property->getName();
                if ($property->getAttributes(Locked::class) === [] && ! in_array($name, self::UNLOCKED_BY_DESIGN, true)) {
                    $unlocked[] = $name;
                }
            }
        }

        $this->assertGreaterThan(20, $checked, 'The components were not read.');
        $this->assertSame([], $unlocked, 'Lock these with #[Locked], or list them with the reason they cannot be.');
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
        $root = dirname(__DIR__, 2).'/app/Livewire';
        $classes = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() !== 'php' || str_contains($file->getPathname(), DIRECTORY_SEPARATOR.'Concerns'.DIRECTORY_SEPARATOR)) {
                continue;
            }
            $relative = substr($file->getPathname(), strlen($root) + 1, -4);
            $class = 'App\\Livewire\\'.str_replace(['/', '\\'], '\\', $relative);
            if (class_exists($class) && is_subclass_of($class, Component::class) && ! (new ReflectionClass($class))->isAbstract()) {
                $classes[] = $class;
            }
        }

        $this->assertGreaterThan(25, count($classes));

        return $classes;
    }
}
