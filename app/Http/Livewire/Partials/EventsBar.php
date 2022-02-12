<?php

namespace App\Http\Livewire\Partials;

use App\Http\Livewire\Events\Events;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;
use Spatie\CalendarLinks\Link;
use DateTime;

class EventsBar extends Component
{

    public $listeners = [
        'refresh' => 'render'
    ];

    public $links = [];

    public function generateCalendarLinks($events) {
        foreach($events as $event) {
            
            if(isset($event['groups']['name'])) 
                $name = $event['groups']['name'];
            else $name = '';
            $link = Link::create(
                $name,
                DateTime::createFromFormat('U', $event['start']),
                DateTime::createFromFormat('U', $event['end'])
            );
            $this->links[$event['id']]['calendar_google'] = $link->google();
            $this->links[$event['id']]['calendar_ics'] = $link->ics();
        }
    }

    public function render()
    {
        $events = Auth()->user()->feature_events->toArray();
        $this->generateCalendarLinks($events);

        return view('livewire.partials.events-bar', [
            'events' => $events
        ]);
    }
}
