<?php

namespace App\Http\Livewire;

use Livewire\Component;
use Livewire\WithPagination;

class AppComponent extends Component {

    use WithPagination;

    protected $paginationTheme = 'bootstrap';
}