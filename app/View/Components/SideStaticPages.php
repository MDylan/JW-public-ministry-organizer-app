<?php

namespace App\View\Components;

use App\Models\StaticPage;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\Component;

class SideStaticPages extends Component
{
    /**
     * Create a new component instance.
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
        if(Auth::id()) {
            //logged in menus
            $status = [0,1,3];
        } else {
            $status = [1,2];
        }
        $menus = StaticPage::where('position', '=', 'left')->whereIn('status', $status);

        // dd($menus->get()->toArray());

        return view('components.side-static-pages', [
            'left_menus' => $menus->get()
        ]);
    }
}
