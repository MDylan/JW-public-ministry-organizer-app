<?php

namespace App\Http\Livewire\Events;

use App\Http\Livewire\AppComponent;
use App\Models\Event;
use App\Support\Retention\RetentionWindow;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class LastEvents extends AppComponent
{

    private $eventForm;
    public $months = [];
    public $year = 0;
    public $month = 0;
    public $current_month = 0;
    public $reports = [];
    private $eventFormDisabled = false;
    public $eventId;

    public function mount() {
        if(!isset($this->state['month'])) {
            $this->state['month'] = date("Y-m-")."01";
        }
        $this->year = date("Y");
        $this->month = date("m");
        $this->getMonthListFromDate($this->earliestSelectableMonth());
    }

    /**
     * A hónaplista kezdete: a regisztráció és a retenciós padló közül a
     * KÉSŐBBI, hónap elejére kerekítve.
     *
     * Itt az eventsFloor() a helyes padló, NEM a displayFloor(): ez a nézet
     * csak eseményt kérdez, day_stats-ot nem. A displayFloor() 12 hónapos
     * csoportadat-beállítás mellett későbbi lenne a 13 hónapos
     * eseményablaknál, tehát egy hónapnyi létező, szerkeszthető eseményt
     * rejtene el.
     *
     * A felső korlát a mai hónap: enélkül egy jövőbe csúszott padló üres
     * CarbonPeriod-ot adna, és a választó eltűnne.
     */
    private function earliestSelectableMonth(): Carbon
    {
        $start = Carbon::parse(Auth()->user()->created_at)->startOfMonth();
        $floor = RetentionWindow::eventsFloor();

        if ($floor !== null && $floor->startOfMonth()->greaterThan($start)) {
            $start = $floor->startOfMonth();
        }

        $currentMonth = Carbon::today()->startOfMonth();

        return $start->greaterThan($currentMonth) ? $currentMonth : $start;
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

    public function searchForId($id, $array) {
        foreach ($array as $key => $val) {
            if ($val['id'] === $id) {
                return $key;
            }
        }
        return false;
    }

    public function getEventInfo($loadForm = false) {
        $event = Auth::user()->events()
                        ->where('id', $this->eventId)
                        ->where('status', 1)
                        ->with(['serviceReports', 'groups.literatures'])
                        ->has('groups')
                        ->first();
        if(count($event->groups->literatures) == 0) {
            //there are no literature ability
            $this->dispatchBrowserEvent('sweet-error', [
                'title' => __('app.error'),
                'message' => __('event.service.error'),
            ]);
            return false;
        } else {
            $groups = Auth()->user()->userGroups->toArray();
            $this->eventFormDisabled = false;
            if($this->searchForId($event->groups->id, $groups) === false) {
                $this->eventFormDisabled = true;
            }

            $this->eventForm = $event;
            if($loadForm) {
                if(count($event->serviceReports)) {
                    foreach($event->serviceReports as $report)  {
                        $this->reports[$report->group_literature_id] = $report;
                    }
                } else {
                    $this->reports = [];
                }
            }
            return true;
        }
    }

    public function editReports($eventId) {
        $this->showEditModal = false;
        $this->eventId = $eventId;

        if($this->getEventInfo(true)) {
            $this->dispatchBrowserEvent('show-modal', [
                'id' => 'ReportForm',
            ]); 
        }
    }

    public function saveReport() {
        $this->getEventInfo();
        if($this->eventFormDisabled) return;
        
        if(count($this->reports)) {
            $validatedData = Validator::make($this->reports, [
                '*.placements' => 'digits_between:0,2',
                '*.videos' => 'digits_between:0,2',
                '*.return_visits' => 'digits_between:0,2',
                '*.bible_studies' => 'digits_between:0,2',
            ])->validate();
            foreach($validatedData as $literatureId => $report) {
                foreach($report as $k => $v) {
                    $report[$k] = intval($v);
                }
                $this->eventForm->serviceReports()->updateOrCreate(
                    ['group_literature_id' => $literatureId],
                    $report
                );
            }
            $this->reports = [];

            $this->dispatchBrowserEvent('hide-modal', [
                'id' => 'ReportForm',
                'message' => __('event.service.success')
            ]); 
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

        $events = Event::where('user_id', Auth()->user()->id ) 
                    ->whereBetween('day', [$this->first_day, $this->last_day])
                    ->where('status', 1)
                    ->orderBy('start', 'desc')
                    ->with(['serviceReports', 'groups.literatures'])
                    ->has('groups')
                    ->get();     

        return view('livewire.events.last-events', [
            'events' => $events,
            'eventForm' => $this->eventForm,
            'eventFormDisabled' => $this->eventFormDisabled,
        ]);
    }
}
