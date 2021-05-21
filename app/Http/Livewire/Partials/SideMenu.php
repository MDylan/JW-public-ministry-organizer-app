<?php

namespace App\Http\Livewire\Partials;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class SideMenu extends Component
{

    public $sideMenu = [
        'invites' => 0,
    ];

    public $request_path = "";

    protected $listeners = [
        'refresh' => 'refresh'
    ];

    public function mount() {
        $this->request_path = request()->path();
        // dd(request()->paramters());
        if (request()->is('calendar/*')) {
            $this->request_path = 'calendar';
        }
        if (request()->is('groups/*')) {
            $this->request_path = 'groups';
        }
    }

    //ne töröld ki, szükséges függvény
    public function refresh() { }

    public function render()
    {
        if(Auth::user()) {
            $this->sideMenu['invites'] = count(auth()->user()->userGroupsNotAccepted);
        }

        return view('livewire.partials.side-menu');
    }
}
