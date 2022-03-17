<?php

namespace App\Http\Livewire\Events;

use App\Classes\GenerateSlots;
use App\Http\Livewire\AppComponent;
// use App\Models\DayStat;
// use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use DateTime;
// use App\Models\User;
use App\Models\Group;
// use App\Models\Event;
use App\Models\GroupDate;
use App\Models\GroupDayDisabledSlots;
use Carbon\Carbon;
use Carbon\CarbonInterval;

// use App\Models\GroupUser;
// use App\Notifications\GroupUserAddedNotification;
// use Barryvdh\Debugbar;

class Modal extends AppComponent
{

    // public $group;
    private $service_days = [];
    private $group_data = [];
    private $day_data = [];
    private $day_events = [];

    public $form_groupId = 0;
    // public $original_day_data = [];
    public $listeners = [
        'openModal', 
        'refresh', 
        'cancelEdit',
        'setGroup' => 'mount',
        'hiddenModal'
    ];
    public $active_tab = '';
    public $date = null;
    // public $event_edit = [];
    // public $all_select = [];
    // private $weekdays = [
    //     1 => 'monday',
    //     2 => 'tuesday',
    //     3 => 'wednesday',
    //     4 => 'thursday',
    //     5 => 'friday',
    //     6 => 'saturday',
    //     0 => 'sunday'
    // ];
    private $day_stat = [];
    public $error = false;
    public $current_available = false;
    private $role = "";
    private $date_data = [];
    public $polling = false;
    private $editor = false;
    public $show_content = false;

    public function mount($groupId = 0) {
        // $this->date = null;
        // if($groupId > 0) {
        //     $check = auth()->user()->userGroups()->whereId($groupId);
        //     if(!$check) {
        //         $this->error = __('event.error.invalid_group');
        //     } else {
        //         $this->form_groupId = $groupId;
        //         $this->day_data =  [
        //             'date' => 0,
        //             'dateFormat' => 0,
        //             'table' => [],
        //             'selects' => [
        //                 'start' => [],
        //                 'end' => [],
        //             ],
        //         ];

                
        //         // dd($this->role);
        //     }
        // }
    }

    // public function getRole() {
    //     $info = GroupUser::where('user_id', '=', Auth::id())
    //         ->where('group_id', '=', $this->form_groupId)
    //         ->select('group_role')
    //         ->first()->toArray();
    //     $this->role = $info['group_role'];
    // }

    //dont delete, it's a listener
    public function refresh() {
        // dd('refresh');
        if($this->error !== false) return;
        $this->active_tab = '';
        $this->polling = true;

        // $this->getInfo();

        // $stat = DayStat::where([
        //     'group_id' => $this->form_groupId,
        //     'day' => $this->date
        // ]);
        // $stat->delete();

        // DayStat::insert(
        //     $this->day_stat
        // );

        // $this->emitUp('refresh');
        // $this->emitTo('events.event-edit', 'createForm');
    }

    // public function setGroup($groupId) {
    //     $check = auth()->user()->userGroups()->whereId($groupId);            
    //     if(!$check) {
    //         abort('403');
    //     }
    //     $this->form_groupId = $groupId;
    // }

    public function getInfo() {
        // $this->active_tab = '';
        // $this->getRole();
        if($this->error !== false) return;
        // $this->setVars();

        $groupId = $this->form_groupId;

        $check = auth()->user()->userGroups()->whereId($groupId);
        if(!$check) {
            $this->error = __('event.error.invalid_group');
            return;
        }

        $date = $this->date;

        $group = Group::with([
                        'days', 
                        'events' => function($q) use ($date) {
                            $q->select(['id', 'group_id', 'user_id', 'day', 'start', 'end', 'accepted_at', 'accepted_by', 'status', 'comment']);
                            $q->where('day', '=', $date);
                            $q->whereIn('status', [0,1]);
                        },
                        'currentUser' => function($q) {
                            $q->select('users.id');
                            $q->where('user_id', auth()->user()->id);
                            $q->take(1);
                        },
                        'current_date' => function($q) use ($date) {
                            $q->where('date', '=', $date);
                        },
                        'groupUsersAll', //we will unset this later!
                        'dates',
                        'posters' => function($q) use ($date) {
                            $q->where('show_date', '<=', $date);
                            $q->where(function ($q) use ($date) {
                                $q->where('hide_date', '>=', $date)
                                    ->orWhereNull('hide_date');
                            });
                        },
                    ])->findOrFail($groupId)->toArray();
        // dd($group);
        // dd(Carbon::parse($date)->addDay()->format("Y-m-d"));
        if(isset($group['current_date'])) {
            if($group['current_date']['date_status'] == 0) {
                $this->error = __('event.error.no_service_day')." (".$group['current_date']['note'].")";
                return;
            }
        }
        $dates = [];
        if(isset($group['dates'])) {
            foreach($group['dates'] as $d) {
                $dates[$d['date_status']][strtotime($d['date'])] = $d;
            }
        }
        $this->role = $group['current_user'][0]['pivot']['group_role'];
        $this->editor = in_array($this->role, ['admin', 'roler']) ? true : false;
        //unset this part, it's not public for livewire
        unset($group['current_user']);
        
        // dd($group);

        $this->day_data = [];
        $this->service_days = [];
        $this->day_stat = [];
        $d = new DateTime( $date );
        $dayOfWeek = $d->format("w");
        $this->day_data['date'] = $date;
        $this->day_data['dateFormat'] = $d->format(__('app.format.date').'.,')." ".__('event.weekdays_short.'.$dayOfWeek);
        
        // $days = $group->days()->get()->toArray();
        $days = $group['days'];
        $next = $prev = false;
        $days_array = [];
        if(count($days)) {
            foreach($days as $day) {
                $days_array[] = $day['day_number'];
                $this->service_days[$day['day_number']] = [
                    'start_time' => $day['start_time'],
                    'end_time' => $day['end_time'],
                ];
            }
        }
        $user_signs = [];
        // $user_phones = [];
        if(is_array($group['group_users_all'])) {
            foreach($group['group_users_all'] as $user) {
                if(isset($user['pivot']['signs'])) {
                    $user_signs[$user['id']] = $user['pivot']['signs'];
                }
                // if($user['phone_number']) {
                //     $user_phones[$user['id']] = $user['phone_number'];
                // }
            }
        }
        // dd($user_phones);
        $group['users_signs'] = $user_signs;
        // $group['users_phones'] = $user_phones;
        unset($group['group_users_all']);
        // dd($this->service_days);
        $this->group_data = $group; //->toArray();
        // dd($this->group_data);
        //calculate next end previous day
        $now = time();
        $max_time = $now + ($this->group_data['max_extend_days'] * 24 * 60 * 60);
        $next_date = $this_date = strtotime($date); // new DateTime($date);
        $next = false;
        while(!$next) {
            $next_date = $next_date + (24 * 60 * 60);
            // echo date("Y-m-d", $next_date)."<br/>";
            // $next_date->modify("+1 day");
            // $dayNum = $next_date->format("w");
            // $unixTime = $next_date->format("U");
            $dayNum = date("w", $next_date);
            $unixTime = $next_date;

            if((isset($this->service_days[$dayNum]) && !isset($dates[0][$unixTime])) || isset($dates[2][$unixTime])) {
                $next = true;
                $this->day_data['next_date'] = date("Y-m-d", $next_date); // $next_date->format("Y-m-d");
                if($unixTime > $max_time) {
                    $this->day_data['next_date'] = false;
                }
            } 
        }
        // dd('ok');
        $prev_date = $this_date; //new DateTime($date);
        $prev = false;
        while(!$prev) {
            $prev_date = $prev_date - (24 * 60 * 60);
            $dayNum = date("w", $prev_date);
            $unixTime = $prev_date;
            // $prev_date->modify("-1 day");
            // $dayNum = $prev_date->format("w");
            // $unixTime = $prev_date->format("U");

            if((isset($this->service_days[$dayNum]) && !isset($dates[0][$unixTime])) || isset($dates[2][$unixTime])) {
                $prev = true;
                $this->day_data['prev_date'] = date("Y-m-d", $prev_date); // $prev_date->format("Y-m-d");
            } 
        }

        if($this->group_data['current_date'] === null) {
            $start = strtotime($date." ".$this->service_days[$dayOfWeek]['start_time'].":00");
            $end_date = $date;
            if(strtotime($this->service_days[$dayOfWeek]['end_time']) == strtotime("00:00")) {
                $end_date = Carbon::parse($date)->addDay()->format("Y-m-d");
            }
            $max = strtotime($end_date." ".$this->service_days[$dayOfWeek]['end_time'].":00");
            GroupDate::create([
                'group_id' => $groupId,
                'date' => $date,
                'date_start' => date("Y-m-d H:i:s", $start),
                'date_end' => date("Y-m-d H:i:s", $max),
                'date_status' => 1,
                'date_min_publishers' => $this->group_data['min_publishers'],
                'date_max_publishers' => $this->group_data['max_publishers'],
                'date_min_time' => $this->group_data['min_time'],
                'date_max_time' => $this->group_data['max_time']
            ]);
            $this->date_data = [
                'min_publishers' => $this->group_data['min_publishers'],
                'max_publishers' => $this->group_data['max_publishers'],
                'min_time' => $this->group_data['min_time'],
                'max_time' => $this->group_data['max_time'],
                'peak' => 0
            ];

        } else {
            // dd($this->group_data);
            $start = strtotime($this->group_data['current_date']['date_start']);
            $max = strtotime($this->group_data['current_date']['date_end']);
            $this->date_data = [
                'min_publishers' => $this->group_data['current_date']['date_min_publishers'],
                'max_publishers' => $this->group_data['current_date']['date_max_publishers'],
                'min_time' => $this->group_data['current_date']['date_min_time'],
                'max_time' => $this->group_data['current_date']['date_max_time'],
                'peak' => 0
            ];
        }


        if($now < $max) {
            $this->current_available = true;
        }
        $step = $this->date_data['min_time'] * 60;

        $day_table = [];
        $day_selects = [];
        $day_events = [];
        $past = (Carbon::parse($this->date)->isFuture() || Carbon::parse($this->date)->isToday()) ? false : true;
        $row = 1;
        $peak = 0;
        $slots_array = GenerateSlots::generate($this->date, $start, $max - $step, $step);
        // $slots_count = count($slots_array);
        foreach($slots_array as $current) {
        // for($current=$start;$current < $max;$current+=$step) {
            $key = "'".date('Hi', $current)."'";
            $day_table[$key] = [
                'ts' => $current,
                'hour' => date("H:i", $current),
                'row' => $row,
                'status' => ($current < $now || $past) ? 'full' : 'free',
                'publishers' => 0,
                'accepted' => 0,
            ];
            $this->day_stat[$key] = [
                'group_id' => $this->form_groupId,
                'day' => $this->date,
                'time_slot' => date('Y-m-d H:i', $current),
                'events' => 0
            ];
            // for ($i=1; $i <= $this->date_data['max_publishers']; $i++) { 
            for ($i=1; $i <= ($this->date_data['max_publishers'] + config('events.max_columns')); $i++) { 
                $day_table[$key]['cells'][$i] = true;
            }
            $day_selects['start'][$current] = date("H:i", $current);
            if($current != $start)
                $day_selects['end'][$current] = date("H:i", $current);

            $row++;
        }        
        // dd($day_selects, $slots_count, $row);
        $day_selects['end'][$max] = date("H:i", $max);
        // dd($day_table, $step, date("Y-m-d H:i", $start), date("Y-m-d H:i", $max), $range, $period->toArray());
        //események
        // $events = $group->day_events($date)->get()->toArray();
        $events = $group['events'];
                
        $disabled_slots = $slots = [];
        if(!$past && $group['current_date']['date_status'] == 1) {
            //get disabled time slot for this day
            $disableds = GroupDayDisabledSlots::where('group_id', '=', $this->form_groupId)
                                ->where('day_number', '=', $dayOfWeek)
                                ->orderBy('slot', 'asc')
                                ->get()
                                ->toArray();
            foreach($disableds as $sl) {
                $ts = strtotime($this->date." ".$sl['slot']);
                $slot_key = "'".date("Hi", $ts)."'";
                $disabled_slots[$slot_key] = true;
                $slots[$slot_key][] = true;
            }
        }
        // print_r($disabled_slots);
        // dd($disabled_slots);
        foreach($events as $event) {
            $steps = ceil(($event['end'] - $event['start']) / $step);
            if(!isset($day_table["'".date('Hi', $event['start'])."'"])) continue;
            $row = $day_table["'".date('Hi', $event['start'])."'"]['row'];
            $key = "'".date('Hi', $event['start'])."'";
            // $cell = 2;
            $cell = 1;
            if(isset($slots[$key])) {
                // if(count($day_table[$key]['cells']) == 0) {
                //     dd($key, $day_table[$key]);
                // }
                // $cell = count($slots[$key]) + 2;
                // if(!isset($day_table[$key]['cells'])) {
                //     $day_table[$key]['cells'][1] = true;
                //  //   echo "set ";
                // }
                // echo $key."<br/>";
                // print_r(($day_table[$key]));
                $cell = min(array_keys($day_table[$key]['cells']));
                // $cell = $day_table[$key]['publishers'] + 2;
                
                // $table[$key]['available'] = count($slots[$key]);
            }            
            
            $day_events[$key][$event['id']] = $event;
            $day_events[$key][$event['id']]['time'] = date("H:i", $event['start'])." - ".date("H:i", $event['end']);
            $day_events[$key][$event['id']]['height'] = $steps;
            $day_events[$key][$event['id']]['cell'] = $cell;
            $day_events[$key][$event['id']]['row'] = $row;
            $day_events[$key][$event['id']]['start_time'] = date("H:i", $event['start']);
            $day_events[$key][$event['id']]['end_time'] = date("H:i", $event['end']);
            $day_events[$key][$event['id']]['editable'] = $past ? 'disabled' : '';
            $day_events[$key][$event['id']]['status'] = $event['status'];
            $day_events[$key][$event['id']]['comment'] = $event['comment'];
            if(!in_array($this->role, ['admin', 'roler', 'helper']) 
                && $event['user_id'] !== Auth::id()
                ) {
                    $day_events[$key][$event['id']]['editable'] = 'disabled';
            }
            $cell_start = $event['start'];
            for($i=0;$i < $steps;$i++) {
                $slot_key = "'".date("Hi", $cell_start)."'";
                //if($i == 0)
                $slots[$slot_key][] = true;
                unset($day_table[$slot_key]['cells'][$cell]);
                $day_table[$slot_key]['publishers']++;
                if($event['status'] == 1) {
                    $day_table[$slot_key]['accepted']++;
                    $this->day_stat[$slot_key]['events']++;
                }                
                $peak = max($peak, $day_table[$slot_key]['publishers']);
                if(Auth::id() == $event['user_id'] 
                    && !in_array($this->role, ['admin', 'roler', 'helper'])
                ) {
                    $disabled_slots[$slot_key] = true;
                }
                $cell_start += $step;
            }
        }
        ksort($disabled_slots);

        // dd($day_table, $disabled_slots, $slots);
        //kiszűröm ami nem elérhető
        foreach($slots as $key => $times) {
            // dump($key, isset($disabled_slots[$key]));
            if(count($times) >= ($this->group_data['need_approval'] 
                        ? ( ($day_table[$key]['accepted'] ?? 0) >= $this->date_data['max_publishers'] 
                            ? $this->date_data['max_publishers'] 
                            : ($this->date_data['max_publishers'] + config('events.max_columns')))
                        : $this->date_data['max_publishers']) 
                    || isset($disabled_slots[$key])) {
                $day_table[$key]['status'] = 'full';
                if(isset($day_table[$key]['ts'])) {
                    $k = $day_table[$key]['ts'];
                    unset($day_selects['start'][$k]);
                    unset($day_selects['end'][$k + $step]);
                }
            } elseif($day_table[$key]['accepted'] >= $this->date_data['min_publishers']) {
                $day_table[$key]['status'] = $now > $day_table[$key]['ts'] ? 'full' : 'ready';
            } 
        }
        
        // dd($day_table, $disabled_slots);
        // dd($this->group->day_events($date));
        $this->day_data['table'] = $day_table;
        $this->day_data['selects'] = $day_selects;
        $this->date_data['peak'] = max($peak, $this->date_data['max_publishers']);
        // $this->original_day_data = $this->day_data;
        $this->day_events = $day_events;
    }

    public function openModal($date, $groupId = 0) {
        $this->reset();
        $this->date = $date;
        if($groupId > 0) {
            $this->form_groupId = $groupId;
        }
        // $this->getInfo();
        // $this->dispatchBrowserEvent('show-form');
        $this->dispatchBrowserEvent('show-modal', [
            'id' => 'form',
            'livewire' => 'events.modal',
        ]); 
        
        $this->polling_check();
        $this->show_content = true;
    }

    public function hiddenModal() {
        $this->polling = false;
        $this->show_content = false;
    }

    public function openPosterModal($posterId = 0) {
        $this->emitTo('groups.poster-edit-modal', 'openModalFromDate', $this->form_groupId, $this->date, $posterId);
    }

    public function setDate($date) {
        $this->date = $date;
        $this->active_tab = '';
        $this->polling_check();
        
        // Debugbar::addMessage('setDate lefutott', 'mylabel');
        // $this->getInfo();
    }

    public function setStart($time) {
        // dd('most');
        $this->active_tab = 'event';
        $this->polling = false;
        $this->emitTo('events.event-edit', 'setStart', $time);
        // $this->state['start'] = $time;
        // $this->change_end();
    }

    public function editEvent_modal($id) {
        $this->polling = false;
        $this->active_tab = 'event';
        $this->emitTo('events.event-edit', 'editForm', $id);

    }


    public function cancelEdit() {
        $this->active_tab = '';
        $this->polling = true;
        // $this->event_edit = null;
        $this->emitTo('events.event-edit', 'createForm');
    }

    public function polling_check() {
        $this->polling = (Carbon::parse($this->date)->isFuture() || Carbon::parse($this->date)->isToday()) ? true : false;
        // dd($this->polling);
    }

    public function render()
    {
        if($this->date !== null && $this->form_groupId !== 0) {
            $this->getInfo();
        }

        if($this->date !== null && !$this->error) {            
            // dd($this->group_data);
            $this->error = false;
            // dd($this->day_events);
            // $date = Carbon::parse($this->day_data['date']);

            return view('livewire.events.modal', [
                'group_data' => $this->group_data,
                'service_days' => $this->service_days,
                'day_events' => $this->day_events,
                'day_data' => $this->day_data,
                'editor' => $this->editor,
                'date_data' => $this->date_data
            ]);
        } 
        
        return view('livewire.events.modal-empty');
    }
}
