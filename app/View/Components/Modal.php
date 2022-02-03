<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Modal extends Component
{

    public $modalId;

    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct($modalId) //
    {
        // dd('comp');
        $this->modalId = $modalId;
    }

    /**
     * Get the view / contents that represent the component.
     *
     * @return \Illuminate\Contracts\View\View|\Closure|string
     */
    public function render()
    {
        return view('components.modal');
    }
}
