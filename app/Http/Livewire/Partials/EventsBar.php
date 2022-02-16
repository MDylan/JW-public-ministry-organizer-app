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
            $system_calendars = config('events.calendars');
            foreach(auth()->user()->calendars as $calendar => $value) {
                if(!in_array($calendar, $system_calendars)) continue;
                $this->links[$event['id']][$calendar] = $link->$calendar();
            }
        }
    }

    public function render()
    {
        $events = Auth()->user()->feature_events->toArray();
        
        //if user use any calendars, get the links
        if(auth()->user()->calendars) {
            $this->generateCalendarLinks($events);
        }

        return view('livewire.partials.events-bar', [
            'events' => $events
        ]);
    }
}
