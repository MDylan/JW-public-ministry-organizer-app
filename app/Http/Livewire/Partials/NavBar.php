<?php

namespace App\Http\Livewire\Partials;

use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class NavBar extends Component
{

    public $notifications = [];
    public $total_notifications = 0;


    protected $listeners = [
        'refresh' => 'refresh'
    ];

    //ne töröld ki, szükséges függvény
    public function refresh() {  }

    public function render()
    {
        $this->total_notifications = 0;
        $this->notifications = [];
        
        if(Auth::user()) {
            if(count(auth()->user()->userGroupsNotAccepted)) {
                $this->notifications['groups'] = [
                    'route' => route('groups'),
                    'icon' => 'fa-users',
                    'message' => __('app.top_notifies.groups', ['number' => count(auth()->user()->userGroupsNotAccepted)])
                ];
                $this->total_notifications += count(auth()->user()->userGroupsNotAccepted);
            }
        }
        // $this->refresh();
        return view('livewire.partials.nav-bar');
    }
}
