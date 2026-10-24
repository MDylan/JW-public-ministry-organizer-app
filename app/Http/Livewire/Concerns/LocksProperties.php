<?php

namespace App\Http\Livewire\Concerns;

/**
 * Server-owned public properties the client may not write (roadmap TODO 70.1).
 *
 * Livewire 3 has #[Locked]; Livewire 2 has nothing of the kind - every
 * public property is the client's to rewrite through a syncInput update, and
 * TODO 70 measured what that allowed: a group, an event, a news item or a
 * removed user's id set by the client, and the action carried out on it. It
 * also defeats a per-request role check, since a syncInput in the same
 * request lands after hydrate().
 *
 * Livewire 2 calls updating<TraitName>() before every client update
 * (SupportComponentTraits), so a component lists its server-owned properties
 * in $lockedProperties and an update to any of them - or to a key inside one
 * - is refused with 403 before it is applied.
 */
trait LocksProperties
{
    public function updatingLocksProperties($name, $value): void
    {
        $root = explode('.', (string) $name, 2)[0];

        if (in_array($root, $this->lockedProperties ?? [], true)) {
            abort(403, __('This action is unauthorized.'));
        }
    }
}
