<?php

namespace App\Http\Livewire\Partials;

use App\Http\Livewire\Events\Events;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class EventsBar extends Component
{

    public $listeners = [
        'refresh' => 'render'
    ];

    public function render()
    {
        $events = Auth()->user()->feature_events->toArray();

       // dd($events);

        return view('livewire.partials.events-bar', [
            'events' => $events
        ]);
    }
}
