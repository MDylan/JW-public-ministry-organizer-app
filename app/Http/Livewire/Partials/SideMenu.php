<?php

namespace App\Http\Livewire\Partials;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class SideMenu extends Component
{

    public $sideMenu = [
        'invites' => 0,
        'groups' => 0
    ];

    public $request_path = "";

    protected $listeners = [
        'refresh' => 'render'
    ];

    public function mount() {
        $this->request_path = request()->path();
        // dd(request()->path());
        if (request()->is('calendar/*')) {
            $this->request_path = 'calendar';
        }
        if (request()->is('groups/*')) {
            $this->request_path = 'groups';
        }
    }

    public function render()
    {
        if(Auth::user()) {
            // $res = auth()->user()->with(['userGroupsNotAccepted', 'groupsAccepted'])->get()->toArray();
            // dd($res, auth()->user()->userGroupsNotAcceptedNumber());
            // $this->sideMenu['invites'] = count(auth()->user()->userGroupsNotAccepted);
            // $this->sideMenu['groups'] = count(auth()->user()->groupsAccepted);

            $this->sideMenu['invites'] = auth()->user()->userGroupsNotAcceptedNumber();
            $this->sideMenu['groups'] = auth()->user()->groupsAcceptedNumber();
        }

        return view('livewire.partials.side-menu');
    }
}
