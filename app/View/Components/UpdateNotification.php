<?php

namespace App\View\Components;

use App\Support\Updates\UpdateBranch;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\Component;

class UpdateNotification extends Component
{
    /**
     * Check if update exists or not
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Get the view / contents that represent the component.
     *
     * There are three states, not two:
     *
     *   - no newer version                        => nothing
     *   - there is one, and it's on the current major branch => the usual card, with a button
     *   - there is one, but it would move to a higher major   => warning card, button
     *                                                 OMITTED, with manual update
     *
     * The third branch is decided by App\Support\Updates\UpdateBranch, the same
     * class used by the /updater.update gate (EnsureUpdateWithinBranch) -
     * the UI and the actual restriction must not drift apart from each other.
     *
     * @return \Illuminate\Contracts\View\View|\Illuminate\Support\HtmlString|string
     */
    public function render()
    {        
        $update = new \MDylan\LaraUpdater\LaraUpdaterController;
        $version = $update->check();
        //TODO: clear cache afterwards. Maybe an ajax request is needed for the update? See the view files!
        
        if(!$version) {
            return <<<'blade'
            blade;
        }

        $description = $update->getDescription();

        if(! UpdateBranch::allows($version)) {
            // The number of online users is deliberately absent here: we show it
            // because of switching into maintenance mode, which is not going to
            // happen on this branch.
            return view('components.update-notification-manual', [
                'version' => $version,
                'description' => $description,
            ]);
        }

        return view('components.update-notification', [
            'version' => $version,
            'description' => $description,
            'online' => \App\Models\User::whereBetween('last_activity', [now()->subMinute(2), now()])->get()->count(),
        ]);
    }
}
