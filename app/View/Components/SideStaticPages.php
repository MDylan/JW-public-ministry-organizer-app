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
        return view('components.side-static-pages');
    }
}
