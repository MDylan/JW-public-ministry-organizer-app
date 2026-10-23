<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Livewire\WithPagination;

class AppComponent extends Component {

    use WithPagination;

    public $state = [];
    public $showEditModal = false;

    /**
     * The pagination views, Bootstrap for every subclass.
     *
     * Livewire 3 still honours a `$paginationTheme` property, but it moved
     * these two methods off the WithPagination trait and onto its pagination
     * hook, which calls them on the component when they exist. Declaring them
     * keeps the theme an explicit, callable contract (PaginationBehaviorTest).
     * The markup behind `livewire::bootstrap` is the project's own Bootstrap 4
     * override in resources/views/vendor/livewire, not the package's (TODO 48).
     */
    public function paginationView()
    {
        return 'livewire::bootstrap';
    }

    public function paginationSimpleView()
    {
        return 'livewire::simple-bootstrap';
    }
}
