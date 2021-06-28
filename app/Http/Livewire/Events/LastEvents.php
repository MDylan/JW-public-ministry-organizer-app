<?php

namespace App\Http\Livewire\Events;

use App\Http\Livewire\AppComponent;
use App\Models\Event;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class LastEvents extends AppComponent
{

    public $eventForm;
    public $months = [];
    public $year = 0;
    public $month = 0;
    public $current_month = 0;
    public $reports = [];

    public function mount() {
        if(!isset($this->state['month'])) {
            $this->state['month'] = date("Y-m-")."01";
        }
        $this->year = date("Y");
        $this->month = date("m");
        $this->getMonthListFromDate(Carbon::parse(Auth()->user()->created_at));
    }

    public function getMonthListFromDate(Carbon $start)
    {
        foreach (CarbonPeriod::create($start, '1 month', Carbon::today()) as $month) {
            $this->months[$month->format('Y-m-01')] = $month->format('Y')." ".__($month->format('F'));
        }        
    }

    public function setMonth() {
        if(isset($this->months[$this->state['month']])) {
            $month = strtoTime($this->state['month']);
            $this->year = date("Y", $month);
            $this->month = date("m", $month);
        }
    }

    public function editReports($eventId) {
        $this->showEditModal = false;

        $event = Auth::user()->events()
                        ->where('id', $eventId)
                        ->whereNotNull('accepted_at')
                        ->with(['serviceReports', 'groups.literatures'])
                        ->has('groups')
                        ->first();
        if(count($event->groups->literatures) == 0) {
            //there are no literature ability
            $this->dispatchBrowserEvent('sweet-error', [
                'title' => __('app.error'),
                'message' => __('event.service.error'),
            ]);
        } else {
            // dd($event->toArray());
            $this->eventForm = $event;
            if(count($event->serviceReports)) {
                foreach($event->serviceReports as $report)  {
                    $this->reports[$report->group_literature_id] = $report;
                }
            } else {
                $this->reports = [];
            }
            $this->dispatchBrowserEvent('show-form');
        }
    }

    public function saveReport() {
        if(count($this->reports)) {
            // dd($this->reports);
            $validatedData = Validator::make($this->reports, [
                '*.placements' => 'digits_between:0,2',
                '*.videos' => 'digits_between:0,2',
                '*.return_visits' => 'digits_between:0,2',
                '*.bible_studies' => 'digits_between:0,2',
                // '*.note' => 'sometimes|string|max:50',
            ])->validate();
            // dd($validatedData);
            foreach($validatedData as $literatureId => $report) {
                $this->eventForm->serviceReports()->updateOrCreate(
                    ['group_literature_id' => $literatureId],
                    $report
                );
            }
            $this->reports = [];
            $this->dispatchBrowserEvent('hide-form', ['message' => __('event.service.success')]);
        }
    }

    public function render()
    {

        $firstDayOfMonth = mktime(0,0,0,$this->month,1, $this->year);
        $this->current_month = date('F', $firstDayOfMonth);
        $this->first_day = date("Y-m-d", $firstDayOfMonth);
        $this->last_day = date("Y-m-t", $firstDayOfMonth);

        if(strtotime($this->last_day) > time()) {
            $this->last_day = date("Y-m-d");
        }

        $events = Event::where('user_id', Auth()->user()->id)
                    ->whereBetween('day', [$this->first_day, $this->last_day])
                    ->whereNotNull('accepted_at')
                    ->orderBy('start', 'desc')
                    ->with(['serviceReports', 'groups.literatures'])
                    ->has('groups')
                    ->get();
                    // ->toArray();

        // dd($events->toArray());

        return view('livewire.events.last-events', [
            'events' => $events
        ]);
    }
}
