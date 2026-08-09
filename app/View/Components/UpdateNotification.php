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
     * Három állapot van, nem kettő:
     *
     *   - nincs újabb verzió                     => semmi
     *   - van, és az aktuális major ágon van     => a szokásos kártya, gombbal
     *   - van, de magasabb majorra vinne         => figyelmeztető kártya, gomb
     *                                               NÉLKÜL, kézi frissítéssel
     *
     * A harmadik ágat az App\Support\Updates\UpdateBranch dönti el, ugyanaz,
     * amit az /updater.update kapuja (EnsureUpdateWithinBranch) is használ -
     * a felület és a tényleges tiltás nem sodródhat el egymástól.
     *
     * @return \Illuminate\Contracts\View\View|\Illuminate\Support\HtmlString|string
     */
    public function render()
    {        
        $update = new \MDylan\LaraUpdater\LaraUpdaterController;
        $version = $update->check();
        //TODO: cache törlés utána. Talán ajax kérés kéne a frissítéshez? Lásd view fájlokat!
        
        if(!$version) {
            return <<<'blade'
            blade;
        }

        $description = $update->getDescription();

        if(! UpdateBranch::allows($version)) {
            // Az online felhasználók száma itt szándékosan nem szerepel: azt a
            // karbantartás módba kapcsolás miatt mutatjuk, ami ezen az ágon
            // nem fog megtörténni.
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
