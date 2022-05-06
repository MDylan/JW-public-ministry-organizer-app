<?php

namespace App\View\Components;

use Illuminate\View\Component;

class Modal extends Component
{

    public $modalId;
    public $modalSize;

    /**
     * Create a new component instance.
     *
     * @return void
     */
    public function __construct($modalId, $modalSize = "") //
    {
        // dd('comp');
        $this->modalId = $modalId;
        $this->modalSize = $modalSize;
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
