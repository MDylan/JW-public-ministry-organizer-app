<?php

namespace App\View\Components;

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
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {        
        $update = new \pcinaglia\laraupdater\LaraUpdaterController;
        // $version = Cache::remember('update_check', (5 * 60 * 60), function () use ($update) {
        //     return $update->check();
        // });
        $version = $update->check();
        //TODO: cache törlés utána. Talán ajax kérés kéne a frissítéshez? Lásd view fájlokat!
        
        if($version) {
            $description = $update->getDescription();
            return view('components.update-notification', [
                'version' => $version,
                'description' => $description
            ]);
        } else {
            return <<<'blade'
            blade;
        }
    }
}
