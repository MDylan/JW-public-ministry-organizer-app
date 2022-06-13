<?php

namespace App\Http\Livewire\Groups;

use App\Helpers\GroupDateHelper;
use App\Jobs\CalculateDateProcess;
use App\Models\Group;
use App\Models\GroupDate;
use App\Models\GroupDayDisabledSlots;
use Carbon\Carbon;
use DateTime;
use Exception;
use Illuminate\Support\Facades\Validator;
use Livewire\Component;

class SpecialDateModal extends Component
{

    public $groupId = 0;
    private $group = [];
    public $date = null;
    public $fromDate = null;
    public $state = [
        'date_status' => 2,
        'date_min_time' => 30,
        'date_start' => '00:00',
        'date_end' => '00:00',
        'note' => '',
    ];
    public $disabled_slots = [];
    private $disabled_selects = [];

    protected $listeners = [         
        'openModal',
        'deleteDate',
        'setGroup' => 'mount',
    ];

    public function mount($groupId) {
        $this->groupId = $groupId;
    }

    public function getGroupData($groupId = false) {
        if(!$groupId) {
            $groupId = $this->groupId;
        } 
        $group = Group::findorFail($groupId);
        if($group->editors()->wherePivot('user_id', auth()->user()->id)->count() == 0) {
            abort('403');
        }
        $this->groupId = $group->id;
        $this->group = $group;
    }

    public function getDateData() {
        $info = GroupDate::where('date', '=', $this->date)
            ->where('group_id', '=', $this->groupId)
            ->whereIn('date_status', [0,2])
            ->first();
        if($info !== null) {
            $this->state = $info->toArray();
            $this->state['date_start'] = Carbon::parse($this->state['date_start'])->format("H:i");
            $this->state['date_end'] = Carbon::parse($this->state['date_end'])->format("H:i");
        }
    }

    public function openModal($date = false) {
        $this->getGroupData();
        if($date) {
            $this->date = $date;
            $this->getDateData();
        }
        $this->dispatchBrowserEvent('show-modal', [
            'id' => 'SpecialDateModal',
            'livewire' => 'groups.special-date-modal',
        ]);
    }

    public function saveDate() {
        $this->getGroupData();

        $start = $this->state['date']." ".$this->state['date_start'];
        $end_date = $this->state['date'];
        if($this->state['date_end'] == "00:00") {
            $end_date = Carbon::parse($this->state['date'])->addDay()->format("Y-m-d");
        }
        $end = $end_date." ".$this->state['date_end'];

        if(count($this->state['disabled_slots'] ?? [])) {
            foreach($this->state['disabled_slots'] as $k => $v) {
                if(!$v) unset($this->state['disabled_slots'][$k]);
            }
        }

        $data = [
            'date' => $this->state['date'],
            'date_start' => $start,
            'date_end' => $end,
            'date_status' => $this->state['date_status'] ?? 2,
            'note' => $this->state['note'],
            'date_min_publishers' => $this->state['date_min_publishers'] ?? 2,
            'date_max_publishers' => $this->state['date_max_publishers'] ?? 2,
            'date_min_time' => $this->state['date_min_time'] ?? 30,
            'date_max_time' => $this->state['date_max_time'] ?? 60,
            'disabled_slots' => $this->state['disabled_slots'] ?? []
        ];

        $validatedData = Validator::make($data, [
            'date' => 'required|date_format:Y-m-d|after_or_equal:today',
            'date_start' => 'required_if:date_status,2|date_format:Y-m-d H:i|before:date_end',
            'date_end' => 'required_if:date_status,2|date_format:Y-m-d H:i|after:date_start',
            'date_status' => 'required|numeric|in:0,2',
            'note' => 'required|string|min:3|max:255',
            'date_min_publishers' => 'required_if:date_status,2|numeric|digits_between:1,12|lte:date_max_publishers',
            'date_max_publishers' => 'required_if:date_status,2|numeric|digits_between:1,12|gte:date_min_publishers',
            'date_min_time' =>  'required_if:date_status,2|numeric|in:30,60,120|lte:date_max_time',
            'date_max_time' => 'required_if:date_status,2|numeric|in:60,120,180,240,320,360,420,480|gte:date_min_time',
            'disabled_slots' => 'sometimes|array'
        ])->validate();

        // dd($validatedData);

        try {
            $r = GroupDate::updateOrCreate(
                [
                    'group_id' => $this->groupId,
                    'date' => $this->state['date']
                ], 
                $validatedData
            );
            // dd($r->id);
            CalculateDateProcess::dispatch($this->groupId, $this->state['date'], auth()->user()->id);

            $this->dispatchBrowserEvent('hide-modal', [
                'id' => 'SpecialDateModal',
                'message' => __('group.special_dates.saved'),
                'savedMessage' => __('app.saved')
            ]);
            $this->emitUp('pollingOn');
            $this->resetExcept('groupId');
        } catch(Exception $e) {
            $this->state['error'] = $e->getMessage();
        }        
    }

    public function deleteDateConfirmation() {
        $this->dispatchBrowserEvent('show-deletion-confirmation', [
            'title' => __('group.special_dates.confirmDelete.question'),
            'text' => __('group.special_dates.confirmDelete.message'),
            'emit' => 'deleteDate'
        ]);
    }

    public function deleteDate() {
        $this->getGroupData();

        if(Carbon::parse($this->date)->isPast() && !Carbon::parse($this->date)->isToday()) {
            $this->dispatchBrowserEvent('error', [
                'message' => __('group.special_dates.confirmDelete.error')
            ]);
            return;
        }

        $del = GroupDate::where('date', '=', $this->date)
            ->where('group_id', '=', $this->groupId)
            ->delete();

        $helper = new GroupDateHelper($this->groupId);
        $helper->generateDate($this->date, true);
        $helper->recalculateDates();

        /*
        $days = [];
        foreach($this->group->days as $day) {
            $days[$day->day_number] = [
                'day_number' => ''.$day->day_number.'',
                'start_time' => $day->start_time,
                'end_time' => $day->end_time,
            ];
        }

        $deleteAfterCalculate = [];
        $no_need_update = false;
        //update or delete this day, based on if it's a service day or not
        $d = new DateTime($this->date);
        $dayOfWeek = $d->format("w");
        if(!isset($days[$dayOfWeek])) {
            if($this->state["date_status"] == 0) {
                $no_need_update = true;
            } else {
                //it's not a service day, delete after calculate
                $deleteAfterCalculate[$this->date] = $this->date;
            }
            $start = $this->date." ".$this->state['date_start'];
            $end = $this->date." ".$this->state['date_end'];
            $status = 0;
        } else {
            //it's a service day, we must restore original data
            $status = 1;
            $start = $this->date." ".$days[$dayOfWeek]['start_time'].":00";
            $end_date = $this->date;
            if($days[$dayOfWeek]['end_time'] == "00:00") {
                $end_date = Carbon::parse($this->date)->addDay()->format("Y-m-d");
            }
            $end = $end_date." ".$days[$dayOfWeek]['end_time'].":00";
        }

        $disabled_slots = [];
        $d_slots = GroupDayDisabledSlots::where('group_id', '=', $this->groupId)
            ->orderBy('day_number', 'asc')
            ->orderBy('slot', 'asc')
            ->get()->toArray();
        foreach($d_slots as $slot) {
            $disabled_slots[$slot['day_number']][$slot['slot']] = $slot['slot'];
        }
        if($no_need_update) {
            $del = GroupDate::where('date', '=', $this->date)
                ->where('group_id', '=', $this->groupId)
                ->delete();
        } else {
            $del = GroupDate::where('date', '=', $this->date)
                    ->where('group_id', '=', $this->groupId)
                    ->update(
                [
                    'date_start' => $start,
                    'date_end' => $end,
                    'date_status' => $status,
                    'note' => null,
                    'date_min_publishers' => $this->group->min_publishers,
                    'date_max_publishers' => $this->group->max_publishers,
                    'date_min_time' => $this->group->min_time,
                    'date_max_time' => $this->group->max_time,
                    'disabled_slots' => $disabled_slots[$dayOfWeek] ?? []
                ]
            );
        }
        */
        if($del) {
            // if(!$no_need_update) {
            //     CalculateDateProcess::dispatch($this->groupId, $this->date, auth()->user()->id, $deleteAfterCalculate);
            // }
            $this->dispatchBrowserEvent('hide-modal', [
                'id' => 'SpecialDateModal',
                'message' => __('group.special_dates.confirmDelete.success'),
                'savedMessage' => __('app.saved')
            ]);
            $this->resetExcept('groupId');
            $this->emitUp('pollingOn');
        } else {
            $this->dispatchBrowserEvent('error', [
                'message' => __('group.special_dates.confirmDelete.error')
            ]);
        }
    }

    public function generateTimeArray($end = false, $start = false, $step = 30) {
        $start = $start ? strtotime($start) : strtotime("00:00");
        $max = $end ? strtotime($end) : $start + 24 * 60 * 60;
        if($max == strtotime("00:00")) {
            $max = $start + 24 * 60 * 60;
        }
        $step = $step * 60;
        $times = [];
        $midnight = strtotime("00:00") + (24 * 60 * 60);
        for($current=$start; $current < $max; $current+=$step) {
            if($current > $midnight) break;
            $times[] = date("H:i", $current);
        }
        return $times;
    }

    public function render()
    {
        $starts = $this->generateTimeArray($this->state['date_end'], false);
        $ends = $this->generateTimeArray(false, $this->state['date_start'], $this->state['date_min_time']);

        $this->disabled_selects = [];
        $this->disabled_selects = $this->generateTimeArray(
            date("H:i", strtotime($this->state['date_end']) - ($this->state['date_min_time'] * 60)),
            date("H:i", strtotime($this->state['date_start']) + ($this->state['date_min_time'] * 60)), 
            $this->state['date_min_time']);

        return view('livewire.groups.special-date-modal', [
            'min_time_options' => [30,60,120],
            'max_time_options' => [60, 120, 180, 240, 320, 360, 420, 480],
            'disabled_selects' => $this->disabled_selects,
            'starts' => $starts,
            'ends' =>$ends,
            'group' => $this->group,
        ]);
    }
}
