<?php

namespace App\Http\Livewire\Groups;

use App\Classes\updateGroupFutureChanges;
use App\Helpers\GroupDateHelper;
use App\Http\Livewire\AppComponent;
use App\Jobs\CalculateDateProcess;
use App\Models\Group;
use App\Models\GroupDate;
use Illuminate\Support\Str;
use App\Models\GroupDay;
use App\Models\GroupDayDisabledSlots;
use App\Models\GroupFutureChange;
use App\Models\GroupLiterature;
use App\Rules\TimeCheck;
use Carbon\Carbon;
use DateTime;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Session;

class UpdateGroupForm extends AppComponent
{

    // public $users = [];
    // public $users_old = []; //original users
    public $search = "";
    public $group;
    public $days = [];
    public $days_original = [];
    public $admins = [];

    // public $dateAdd = [];
    // public $dates = [];
    // public $hidePastDates = 1;
    // public $editedDate = null;
    // public $editedDateRemove = [];

    public $literatures = [];
    public $editedLiteratureType = null;
    public $editedLiteratureId = null;
    public $editedLiteratureRemove = [];
    public $default_colors = [];
    public $parent_group = [];
    public $day_selects = [];
    public $disabled_slots = [];
    public $disabled_selects = [];
    public $change_date = null;
    public $future_changes = null;

    public $listeners = [
        'literatureDeleteConfirmed', 
        'dateDeleteConfirmed', 
        'confirmFutureChangesRemoval',
        'removeFutureChanges'
    ];

    public $defaultSigns = [
        'fa-key',
        'fa-car',
        'fa-sign-language',
        'fa-home',
        'fa-train',
        'fa-globe',
        'fa-flag',
        'fa-hat-cowboy',
        'fa-underline',
        'fa-font',
        'fa-moon',
        'fa-female',
        'fa-male'
    ];

    public $groupSigns = [];

    public function mount(Group $group) {

        $this->state = $group->toArray();
        $this->default_colors = config('events.default_colors');
        foreach($group['colors'] as $field => $color) {
            if(empty($this->state[$field])) {
                $this->state[$field] = $color;
            }
        }
        $days = [];
        foreach($group->days as $day) {
            $days[$day->day_number] = [
                'day_number' => ''.$day->day_number.'',
                'start_time' => $day->start_time,
                'end_time' => $day->end_time,
            ];
        }
        $this->days = $this->days_original = $days;
        $literatures = $group->literatures;
        if(count($literatures)) {
            foreach($literatures as $literature) {
                $this->literatures['current'][$literature->id] = $literature->name;
            }
        }
        // $dates = $group->dates()->whereIn('date_status', [0,2])->where('date', '>=', date("Y-m-d"))->get()->toArray();
        // if(count($dates)) {
        //     foreach($dates as $date) {
        //         $date_start = new DateTime($date['date_start']);
        //         $date['date_start'] = $date_start->format("H:i");
        //         $date_end = new DateTime($date['date_end']);
        //         $date['date_end'] = $date_end->format("H:i");
        //         $date['type'] = 'current';
        //         $this->dates[$date['date'].""] = $date;
        //     }
        // }
        // if($group->groupUsers) {
        //     foreach($group->groupUsers as $user) {
        //         $slug = Str::slug($user->email, '-');
        //         $this->users[$slug] = [
        //             'email' => $user->email,
        //             'group_role' => $user->pivot->group_role,
        //             'note' => $user->pivot->note,
        //             'user_id' => $user->id,
        //             'name' => $user->name,
        //             'hidden' => $user->pivot->hidden == 1 ? true : false,
        //             'deleted_at' => null
        //         ];
        //         if($user->pivot->group_role == 'admin') {
        //             $this->admins[$slug] = true;
        //         }
        //     }
        // }

        // $this->users_old = $this->users;        
        $this->group = $group;
        $this->future_changes = $group->futureChanges;

        if($group->parent_group_id) {
            $this->parent_group = Group::findOrFail($group->parent_group_id)->toArray();
        }

        $slots = GroupDayDisabledSlots::where('group_id', '=', $this->group->id)
                            ->orderBy('day_number', 'asc')
                            ->orderBy('slot', 'asc')
                            ->get()->toArray();
        foreach($slots as $slot) {
            $this->disabled_slots[$slot['day_number']][$slot['slot']] = true; //$slot['slot'];
        }

        $this->change_date = date("Y-m-d");

        //load future changes to show, if request it
        if(request()->input('show_future') && $group->futureChanges !== null) {
            $future = new updateGroupFutureChanges();
            $future->getChanges($this->group->id);
            $this->state = $future->getState();
            $this->days = $future->getDays();
            $disabled_slots = $future->getDisabledSlots();
            foreach($disabled_slots as $slot) {
                $this->disabled_slots[$slot['day_number']][$slot['slot']] = true; //$slot['slot'];
            }
        }

        //remove future changes
        if(request()->input('remove_future_changes') && $group->futureChanges !== null) {
            $this->group->futureChanges()->delete();
            $this->updateGroup(true);
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
        // $last = $start;
        $midnight = strtotime("00:00") + (24 * 60 * 60);
        for($current=$start; $current < $max; $current+=$step) {
            if($current > $midnight) break;
            $times[] = date("H:i", $current);
            // $last = $current;
        }
        return $times;
    }

    /**
     * Save group's data
     */
    public function updateGroup($must_refresh = false) {
        $this->state['name'] = strip_tags($this->state['name']);
        $admins = 0;
        $reGenerateStat = [];

        Validator::make(['change_date' => $this->change_date], [
            'change_date' => 'required|date_format:Y-m-d|after_or_equal:'.date("Y-m-d")
        ])->validate();

        $pattern = "/^#([a-fA-F0-9]{6}|[a-fA-F0-9]{3})$/";

        $v = Validator::make($this->state, [
            'name' => 'required|string|max:50|min:2',
            'max_extend_days' => 'required|numeric|digits_between:1,365',
            'min_publishers' => 'required|numeric|digits_between:1,12|lte:max_publishers',
            'max_publishers' => 'required|numeric|digits_between:1,12|gte:min_publishers',
            'min_time' => 'required|numeric|in:30,60,120|lte:max_time',
            'max_time' => 'required|numeric|in:60,120,180,240,320,360,420,480|gte:min_time',            
            'need_approval' => 'required|numeric|in:0,1',
            'color_default' => ['sometimes', 'regex:'.$pattern],
            'color_empty' => ['sometimes', 'regex:'.$pattern],
            'color_someone' => ['sometimes', 'regex:'.$pattern],
            'color_minimum' => ['sometimes', 'regex:'.$pattern],
            'color_maximum' => ['sometimes', 'regex:'.$pattern],
            'days.*.start_time' => 'required|date_format:H:i|before_or_equal:days.*.end_time',
            'days.*.end_time' => 'required|date_format:H:i|after_or_equal:days.*.start_time',
            'days.*.day_number' => 'required',
            'signs' => 'sometimes',
            'languages' => 'sometimes',
            'replyTo' => 'nullable|email',
            'showPhone' => 'required|numeric|in:0,1'
        ]);

        $validatedData = $v->validate();

        $validatedDays = Validator::make($this->days, [
            '*.day_number' => 'required',
            '*.start_time' => ['required','date_format:H:i', new TimeCheck('end_time', 'before_or_midnight') ], //|before_or_equal:*.end_time',
            '*.end_time' => ['required', 'date_format:H:i', new TimeCheck('start_time', 'after_or_midnight')] //|after_or_equal:*.start_time',
        ])->validate();

        $change_check = [
            'max_publishers',
            'min_publishers',
            'min_time',
            'max_time',
            'need_approval'
        ];
        // $must_refresh = false;
        foreach($change_check as $field) {
            if($validatedData[$field] != $this->group->{$field}) {
                $must_refresh = true;
            }
        }

        $disabled_slots = [];
        if(count($this->disabled_slots)) {
            // dd($this->disabled_slots);
            foreach($this->disabled_slots as $day => $slots) {
                foreach($slots as $slot => $value) {
                    if(!$value) continue;
                    $disabled_slots[$day] = [
                        'day_number' => $day,
                        'slot' => $slot
                    ];
                }
            }
        }

        $this->saveFutureChanges($validatedData, $validatedDays, $disabled_slots);

        //check disabled slots
        $original_disabled_slots = [];
        $slots = GroupDayDisabledSlots::where('group_id', '=', $this->group->id)
            ->orderBy('day_number', 'asc')
            ->orderBy('slot', 'asc')
            ->get()->toArray();
        foreach($slots as $slot) {
            $original_disabled_slots[$slot['day_number']][$slot['slot']] = true;
        }
        //compare current and old slots
        $d_slots_compare = ($original_disabled_slots === $this->disabled_slots);
        if(!$d_slots_compare) {
            $must_refresh = true;
        }

        //some day updated
        $days_compare = ($this->days === $this->days_original);
        if(!$days_compare) {
            $must_refresh = true;
        }

        $new_days = [];
        if(isset($validatedDays)) {
            foreach($validatedDays as $d => $day) {
                if(!isset($day['day_number'])) {
                    continue;
                }                
                if($day['day_number'] !== false) {
                    $new_days[$day['day_number']] = [
                        'start_time' => $day['start_time'],
                        'end_time' => $day['end_time'],
                    ];
                }
            }      
        }

        $updates = [];
        $deleteAfterCalculate = [];
        $helper = new GroupDateHelper($this->group->id);

        if($must_refresh) {             
            $refresh_dates = GroupDate::where('group_id', '=', $this->group->id)
                                ->where('date', '>=', $this->change_date)
                                ->where('date_status', '=', 1)
                                ->get();
            foreach($refresh_dates as $rdate) {
                $helper->generateDate($rdate->date);
                // $d = new DateTime($rdate->date);
                // $dayOfWeek = $d->format("w");
                // if(isset($new_days[$dayOfWeek])) {
                //     $status = 1;
                //     $start = $rdate->date." ".$new_days[$dayOfWeek]['start_time'].":00";
                //     $end_date = $rdate->date;
                //     if(strtotime($new_days[$dayOfWeek]['end_time']) == strtotime("00:00")) {
                //         $end_date = Carbon::parse($rdate->date)->addDay()->format("Y-m-d");
                //     }
                //     $end = $end_date." ".$new_days[$dayOfWeek]['end_time'].":00";
                //     $updates = [
                //         'date_start' => $start,
                //         'date_end' => $end,
                //         'date_status' => $status,
                //         'note' => null,
                //         'date_min_publishers' => $this->state['min_publishers'],
                //         'date_max_publishers' => $this->state['max_publishers'],
                //         'date_min_time' => $this->state['min_time'],
                //         'date_max_time' => $this->state['max_time'],
                //         'disabled_slots' => ($this->disabled_slots[$dayOfWeek] ?? null)
                //     ];
                // } else {
                //     $updates = [
                //         'date_status' => 0
                //     ];
                //     $deleteAfterCalculate[] = $rdate->date;
                // }
                // $rdate->update(
                //     $updates
                // );
                // $reGenerateStat[$rdate->date] = $rdate->date;
            }
        }

        if(count($this->literatures)) {
            if(isset($this->literatures['new'])) {
                $save = [];
                foreach ($this->literatures['new'] as $language) {
                    $save[] = new GroupLiterature([
                        'name' => $language
                    ]);    
                }
                $this->group->literatures()->saveMany($save);
            }
            if(isset($this->literatures['current'])) {
                foreach ($this->literatures['current'] as $id => $language) {
                    $this->group->literatures()->firstWhere('id', $id)->update(['name' => $language]);                    
                }
            }
            if(isset($this->literatures['removed'])) {
                foreach ($this->literatures['removed'] as $id => $language) {
                    $this->group->literatures()->firstWhere('id', $id)->delete();
                }
            }
        }
        $helper->recalculateDates();
        
        if(Carbon::parse($this->change_date)->eq(Carbon::today())) {
            $init = new updateGroupFutureChanges();
            $init->initChanges($this->group->id);
        }
        // if(count($reGenerateStat)) {
        //     CalculateDateProcess::dispatch($this->group->id, $reGenerateStat, auth()->user()->id, $deleteAfterCalculate);
        // }
        $this->group->refresh();
        Session::flash('message', __('group.groupUpdated')); 
        redirect()->route('groups');
    }

    private function saveFutureChanges($group, $days, $disabled_slots) {
        return GroupFutureChange::create([
            'group_id' => $this->group->id,
            'change_date' => $this->change_date,
            'group' => $group,
            'disabled_slots' => $disabled_slots,
            'days' => $days,
            'user_id' => auth()->user()->id
        ]);
    }

    public function confirmFutureChangesRemoval() {
        $this->dispatchBrowserEvent('show-deletion-confirmation', [
            'title' => __('group.update.in_progress_delete_confirm.question',),
            'text' => __('group.update.in_progress_delete_confirm.message'),
            'emit' => 'removeFutureChanges'
        ]);
    }

    public function removeFutureChanges() {

        return Redirect::route('groups.edit', ['group' => $this->group->id, 'remove_future_changes' => 1]);

        // $this->group->futureChanges()->delete();
        // $this->updateGroup(true);
    }

    function hoursRange( $lower = 0, $upper = 86400, $step = 3600, $format = '' ) {
        $times = array();
    
        if ( empty( $format ) ) {
            $format = 'H:i';
        }
    
        foreach ( range( $lower, $upper, $step ) as $increment ) {
            $increment = gmdate( 'H:i', $increment );
            list( $hour, $minutes ) = explode( ':', $increment );
            $date = new DateTime( $hour . ':' . $minutes );
            $times[(string) $increment] = $date->format( $format );
        }
    
        return $times;
    }

    public function literatureAdd() {
        if(isset($this->state['literatureAdd'])) {
            if(strlen(trim($this->state['literatureAdd'])) > 2) {
                $this->literatures['new'][] = $this->state['literatureAdd'];
                $this->state['literatureAdd'] = '';
                $this->dispatchBrowserEvent('success', ['message' => __('group.literature.added')]);
            } else {
                $this->dispatchBrowserEvent('sweet-error', [
                    'title' => __('group.literature.add_error'),
                    'message' => __('group.literature.tooShort'),
                ]);
            }
        }
    }

    public function literatureRemove($type, $id) {
        if($type == "new") {
            unset($this->literatures['new'][$id]);
            $this->dispatchBrowserEvent('success', ['message' => __('group.literature.confirmDelete.success')]);
        } else {
            $this->editedLiteratureRemove['type'] = $type;
            $this->editedLiteratureRemove['id'] = $id;
            $this->dispatchBrowserEvent('show-literature-confirmation', ['lang' => $this->literatures[$type][$id]]);
        }
    }

    public function literatureEdit($type, $id) {
        if(isset($this->literatures[$type][$id])) {
            $this->editedLiteratureType = $type;
            $this->editedLiteratureId = $id;
            $this->state['editedLiterature'] = $this->literatures[$type][$id];
        }
    }

    public function literatureDeleteConfirmed() {
        $this->literatures['removed'][$this->editedLiteratureRemove['id']] = true;
        unset($this->literatures[$this->editedLiteratureRemove['type']][$this->editedLiteratureRemove['id']]);
        $this->dispatchBrowserEvent('success', ['message' => __('group.literature.confirmDelete.success')]);
    }

    public function literatureEditCancel() {
        $this->editedLiteratureType = null;
        $this->editedLiteratureId = null;
        $this->state['editedLiterature'] = '';
    }

    public function literatureEditSave() {
        if(strlen(trim($this->state['editedLiterature'])) > 2) {
            $this->literatures[$this->editedLiteratureType][$this->editedLiteratureId] = $this->state['editedLiterature'];
            $this->dispatchBrowserEvent('success', ['message' => __('group.literature.saved')]);
            $this->literatureEditCancel();
        } else {
            $this->dispatchBrowserEvent('sweet-error', [
                'title' => __('group.literature.save_error'),
                'message' => __('group.literature.tooShort'),
            ]);
        }
    }

    public function render()
    {
        $group_times = $this->generateTimeArray();
        $this->disabled_selects = [];
        foreach($this->days as $day_key => $day) {
            if(!isset($day['start_time'])) $day['start_time'] = "00:00";
            if(!isset($day['end_time'])) $day['end_time'] = "00:00";
            $this->day_selects[$day['day_number']]['start'] = $this->generateTimeArray($day['end_time'], false);
            $ends = $this->generateTimeArray(false, $day['start_time'], $this->state['min_time']);
            $this->day_selects[$day['day_number']]['end'] = $ends;
            $this->disabled_selects[$day['day_number']] = $this->generateTimeArray(
                                                            date("H:i", strtotime($day['end_time']) - ($this->state['min_time'] * 60)),
                                                            date("H:i", strtotime($day['start_time']) + ($this->state['min_time'] * 60)), 
                                                            $this->state['min_time']);
            if(!in_array($day["start_time"], $this->day_selects[$day['day_number']]['start'])) {
                $this->days[$day_key]['start_time'] = $this->day_selects[$day['day_number']]['start'][0];
            }
            if(!in_array($day["end_time"], $this->day_selects[$day['day_number']]['end'])) {
                $last_key = array_key_last($this->day_selects[$day['day_number']]['end']);
                $this->days[$day_key]['end_time'] = $this->day_selects[$day['day_number']]['end'][$last_key];
            }
            //remove old time slots if needed
            if(isset($this->disabled_slots[$day['day_number']])) {
                foreach($this->disabled_slots[$day['day_number']] as $key => $slot) {
                    if(!in_array($slot, $this->disabled_selects[$day['day_number']])) {
                        $this->disabled_slots[(int)$day['day_number']][$slot] = false;
                    }
                }
            }
        }
        
        // if(count($this->dates))
        //     ksort($this->dates);

        return view('livewire.groups.update-group-form', [
            'min_time_options' => [30,60,120],
            'max_time_options' => [60, 120, 180, 240, 320, 360, 420, 480],
            'group_days' => range(0,6,1),
            'group_times' => $group_times,
            'group_roles' => [
                'member', 
                'helper',
                'roler',
                'admin',
            ]
        ]);
    }
}
