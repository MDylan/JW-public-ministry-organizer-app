<?php

namespace App\Http\Livewire\Events;

use App\Classes\GenerateSlots;
use App\Classes\GenerateStat;
use App\Helpers\GroupDateHelper;
use App\Http\Livewire\AppComponent;
use App\Models\Event;
use Illuminate\Support\Facades\Auth;
use DateTime;
use App\Models\Group;
use App\Models\GroupDate;
use App\Models\GroupDayDisabledSlots;
use App\Models\GroupUser;
use Carbon\Carbon;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\DB;

class Modal extends AppComponent
{
    private $service_days = [];
    private $group_data = [];
    private $day_data = [];
    private $day_events = [];

    public $form_groupId = 0;
    public $listeners = [
        'openModal', 
        'refresh', 
        'cancelEdit',
        'setGroup' => 'mount',
        'hiddenModal',
        'rejectBulkFinal',
        'togglePosterRead'
    ];
    public $active_tab = '';
    public $date = null;
    private $day_stat = [];
    public $error = false;
    public $current_available = false;
    private $role = "";
    private $date_data = [];
    public $polling = false;
    private $editor = false;
    public $show_content = false;
    public $bulk_function = false;
    public $bulk_ids = null;
    public $refreshUp = false;

    public function mount($groupId = 0) {
    }

    //dont delete, it's a listener
    public function refresh() {
        if($this->error !== false) return;
        $this->active_tab = '';
        $this->polling = true;
        if($this->refreshUp !== false)
            $this->emitTo($this->refreshUp, 'refresh');
    }

    public function getRole() {
        $info = GroupUser::where('user_id', '=', Auth::id())
            ->where('group_id', '=', $this->form_groupId)
            ->select('group_role')
            ->first()->toArray();
        return $info['group_role'];
    }

    public function getInfo() {
        if($this->error !== false) return;

        $groupId = $this->form_groupId;

        $check = auth()->user()->userGroups()->whereId($groupId);
        if(!$check) {
            $this->error = __('event.error.invalid_group');
            return;
        }

        $date = $this->date;

        $group = Group::with([
                        // 'days', 
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
                        'groupUsersAllOnly' => function($q) { //we will unset this later!
                            $q->select('users.id', 'group_user.signs', 'group_user.accepted_at');
                        }, 
                        // 'dates',
                        'posters' => function($q) use ($date) {
                            $q->where('show_date', '<=', $date);
                            $q->where(function ($q) use ($date) {
                                $q->where('hide_date', '>=', $date)
                                    ->orWhereNull('hide_date');
                            });
                        }
                    ])->findOrFail($groupId)->toArray();
        // dd($group);
        if(isset($group['current_date'])) {
            if($group['current_date']['date_status'] == 0) {
                $this->error = __('event.error.no_service_day')." (".$group['current_date']['note'].")";
                return;
            }
        }
        // $dates = [];
        // if(isset($group['dates'])) {
        //     foreach($group['dates'] as $d) {
        //         $dates[$d['date_status']][strtotime($d['date'])] = $d;
        //     }
        // }
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
        $now = time();

        $next_date = GroupDate::where('group_id', '=', $groupId)
                            ->where('date', '>', $date)
                            ->where('date_status', '>', 0)
                            ->orderBy('date', 'ASC')
                            ->first('date');
        $this->day_data['next_date'] = $next_date->date ?? false;

        $prev_date = GroupDate::where('group_id', '=', $groupId)
                            ->where('date', '<', $date)
                            ->where('date_status', '>', 0)
                            ->orderBy('date', 'DESC')
                            ->first('date');
        $this->day_data['prev_date'] = $prev_date->date ?? false;
        // dd($prev_date->date);

        // $days = $group['days'];
        $next = $prev = false;
        // $days_array = [];
        // if(count($days)) {
        //     foreach($days as $day) {
        //         $days_array[] = $day['day_number'];
        //         $this->service_days[$day['day_number']] = [
        //             'start_time' => $day['start_time'],
        //             'end_time' => $day['end_time'],
        //         ];
        //     }
        // }
        $user_signs = $users_active = [];
        if(is_array($group['group_users_all_only'])) {
            foreach($group['group_users_all_only'] as $user) {
                if(isset($user['signs'])) {
                    $user_signs[$user['id']] = json_decode($user['signs'], true);
                }
                $users_active[$user['id']] = $user['accepted_at'] ? true : false;
            }
        }
        $group['users_signs'] = $user_signs;
        $group['users_active'] = $users_active;
        unset($group['group_users_all_only']);
        $this->group_data = $group; //->toArray();

        if($this->day_data['next_date']) {
            //disable next day, if max extend days reached
            $next = Carbon::parse($this->day_data['next_date']);
            if($next->greaterThan(date("Y-m-d", ($now + ($this->group_data['max_extend_days'] * 24 * 60 * 60))))) {
                $this->day_data['next_date'] = false;
            }
        }

        // dd($this->group_data);
        //calculate next end previous day
        
        // $max_time = $now + ($this->group_data['max_extend_days'] * 24 * 60 * 60);
        // $next_date = $this_date = strtotime($date); 
        // $next = false;
        // if(count($this->service_days) > 0 || count($group['dates'] ?? []) > 1) {
        //     $counting = 0;
        //     while(!$next) {
        //         $next_date = $next_date + (24 * 60 * 60);
        //         $dayNum = date("w", $next_date);
        //         $unixTime = $next_date;

        //         if((isset($this->service_days[$dayNum]) && !isset($dates[0][$unixTime])) || isset($dates[2][$unixTime])) {
        //             $next = true;
        //             $this->day_data['next_date'] = date("Y-m-d", $next_date); 
        //             if($unixTime > $max_time) {
        //                 $this->day_data['next_date'] = false;
        //             }
        //         }
        //         $counting++;
        //         if($counting == 90) {
        //             //if not found next day, break the loop
        //             $next = true;
        //         }
        //     }        

        //     $prev_date = $this_date; 
        //     $prev = false;
        //     $counting = 0;
        //     while(!$prev) {
        //         $prev_date = $prev_date - (24 * 60 * 60);
        //         $dayNum = date("w", $prev_date);
        //         $unixTime = $prev_date;

        //         if((isset($this->service_days[$dayNum]) && !isset($dates[0][$unixTime])) || isset($dates[2][$unixTime])) {
        //             $prev = true;
        //             $this->day_data['prev_date'] = date("Y-m-d", $prev_date); 
        //         } 
        //         $counting++;
        //         if($counting == 90) {
        //             //if not found previous day, break the loop
        //             $prev = true;
        //         }
        //     }
        // }   

        $past = (Carbon::parse($this->date)->isFuture() || Carbon::parse($this->date)->isToday()) ? false : true;
        $disabled_slots = $slots = [];
        

        if($this->group_data['current_date'] === null) {
            $helper = new GroupDateHelper($this->form_groupId);
            $generate = $helper->generateDate($this->date);
            if(is_array($generate)) {
                $this->date_data = [
                    'min_publishers' => $generate['date_min_publishers'],
                    'max_publishers' => $generate['date_max_publishers'],
                    'min_time' => $generate['date_min_time'],
                    'max_time' => $generate['date_max_time'],
                    'peak' => 0
                ];
                $start = strtotime($generate['date_start']);
                $max = strtotime($generate['date_end']);
                $disabled_slots = $generate['disabled_slots'];
                $group['current_date']['disabled_slots'] = $generate['disabled_slots'];
            }
        } else {
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
        if(count($this->date_data) == 0) {
            abort('403', __('group.color_explanation.color_default'));
        }
        if($now < $max) {
            $this->current_available = true;
        }
        $step = $this->date_data['min_time'] * 60;

        $day_table = [];
        $day_selects = [];
        $day_events = [];
        
        $row = 1;
        $peak = 0;
        $slots_array = GenerateSlots::generate($this->date, $start, $max, $step);
        // dd($slots_array,$this->date, $start, $max, $step);
        foreach($slots_array as $current) {
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
            for ($i=1; $i <= ($this->date_data['max_publishers'] + config('events.max_columns')); $i++) { 
                $day_table[$key]['cells'][$i] = true;
            }
            $day_selects['start'][$current] = date("H:i", $current);
            if($current != $start)
                $day_selects['end'][$current] = date("H:i", $current);

            $row++;
        }
        $day_selects['end'][$max] = date("H:i", $max);
        //events
        $events = $group['events'];
                
        if(count(($group['current_date']['disabled_slots'] ?? []))) {
            $disabled_slots = [];
            foreach($group['current_date']['disabled_slots'] as $slot_key => $v) {
                $key = "'".str_replace(":", "", $slot_key)."'";
                $disabled_slots[$key] = true;
                $slots[$key][] = true;
            }
        }
        foreach($events as $event) {
            $steps = ceil(($event['end'] - $event['start']) / $step);
            if(!isset($day_table["'".date('Hi', $event['start'])."'"])) continue;
            $row = $day_table["'".date('Hi', $event['start'])."'"]['row'];
            $key = "'".date('Hi', $event['start'])."'";
            // $cell = 2;
            $cell = 1;
            if(isset($slots[$key])) {
                $cell = min(array_keys($day_table[$key]['cells']));
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
                if($event['status'] == 1 || ($this->bulk_ids[$event['id']] ?? false) == true) {
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
        if(is_array($disabled_slots))
            ksort($disabled_slots);

        //filter what not available
        foreach($slots as $key => $times) {
            if(count($times) >= ($this->group_data['need_approval'] 
                        ? ( ($day_table[$key]['accepted'] ?? 0) >= $this->date_data['max_publishers'] 
                            ? $this->date_data['max_publishers'] 
                            : ($this->date_data['max_publishers'] + config('events.max_columns')))
                        : $this->date_data['max_publishers']) 
                    || isset($disabled_slots[$key])) {
                if(isset ($day_table[$key])) {
                    $day_table[$key]['status'] = 'full';
                }
                if(isset($day_table[$key]['ts'])) {
                    $k = $day_table[$key]['ts'];
                    unset($day_selects['start'][$k]);
                    unset($day_selects['end'][$k + $step]);
                }
            } elseif($day_table[$key]['accepted'] >= $this->date_data['min_publishers']) {
                $day_table[$key]['status'] = $now > $day_table[$key]['ts'] ? 'full' : 'ready';
            } 
        }
        $this->day_data['table'] = $day_table;
        $this->day_data['selects'] = $day_selects;
        $this->date_data['peak'] = max($peak, $this->date_data['max_publishers']);
        $this->day_events = $day_events;
    }

    public function togglePosterRead($poster_id) {
        pwbs_poster_set_read($poster_id);
    }

    public function openModal($date, $groupId = 0, $refreshUp = false) {
        $this->reset();
        $this->date = $date;
        if($groupId > 0) {
            $this->form_groupId = $groupId;
        }
        $this->dispatchBrowserEvent('show-modal', [
            'id' => 'form',
            'livewire' => 'events.modal',
        ]); 
        
        $this->polling_check();
        $this->show_content = true;
        $this->refreshUp = $refreshUp;
    }

    public function hiddenModal() {
        $this->polling = false;
        $this->show_content = false;
        if($this->refreshUp !== false)
            $this->emitTo($this->refreshUp, 'refresh');
    }

    public function openPosterModal($posterId = 0) {
        $this->emitTo('groups.poster-edit-modal', 'openModalFromDate', $this->form_groupId, $this->date, $posterId);
    }

    public function setDate($date) {
        $this->date = $date;
        $this->active_tab = '';
        $this->polling_check();
        $this->cancelBulk();
    }

    public function setStart($time) {
        $this->active_tab = 'event';
        $this->polling = false;
        $this->emitTo('events.event-edit', 'setStart', $time);
    }

    public function editEvent_modal($id) {
        $this->polling = false;
        $this->active_tab = 'event';
        $this->emitTo('events.event-edit', 'editForm', $id);

    }


    public function cancelEdit() {
        $this->active_tab = '';
        $this->polling = true;
        $this->emitTo('events.event-edit', 'createForm');
    }

    public function polling_check() {
        $this->polling = (Carbon::parse($this->date)->isFuture() || Carbon::parse($this->date)->isToday()) ? true : false;
    }

    public function setBulk() {
        $this->bulk_function = true;
    }

    public function cancelBulk() {
        $this->bulk_function = false;
        $this->bulk_ids = null;
    }

    public function bulk($eventId) {
        if(isset($this->bulk_ids[$eventId])) {
            unset($this->bulk_ids[$eventId]);
        } else {
            $this->bulk_ids[$eventId] = $eventId;
        }
    }

    public function acceptBulk() {
        if(!is_array($this->bulk_ids)) {
            $this->cancelBulk();
            $this->dispatchBrowserEvent('error', [
                'message' => __('event.bulk.error')
            ]);
            return;
        }
        $this->acceptBulkFinal();
    }

    public function acceptBulkFinal() {        
        $role = $this->getRole();
        if(in_array($role, ['admin', 'roler']) && is_array($this->bulk_ids)) {
            foreach($this->bulk_ids as $id) {
                Event::where('id', '=', $id)
                    ->where('status', '=', '0')
                    ->first()
                    ->update([
                        'status' => 1
                    ]);
            }
            $stat = new GenerateStat();
            $stat->generate($this->form_groupId, $this->date);
            $this->dispatchBrowserEvent('success', ['message' => __('event.bulk.acccept_done')]);
        }
        $this->cancelBulk();
    }

    public function rejectBulk() {
        if(!is_array($this->bulk_ids)) {
            $this->cancelBulk();
            $this->dispatchBrowserEvent('error', [
                'message' => __('event.bulk.error')
            ]);
            return;
        }
        $this->dispatchBrowserEvent('show-deletion-confirmation', [
            'title' => __('event.bulk.confirmReject.question'),
            'text' => __('event.bulk.confirmReject.message', ['number' => count($this->bulk_ids)]),
            'emit' => 'rejectBulkFinal'
        ]);
    }

    public function rejectBulkFinal() {
        if(!is_array($this->bulk_ids)) {
            $this->cancelBulk();
            $this->dispatchBrowserEvent('error', [
                'message' => __('event.bulk.error')
            ]);
            return;
        }
        $role = $this->getRole();
        if(in_array($role, ['admin', 'roler']) && is_array($this->bulk_ids)) {
            foreach($this->bulk_ids as $id) {
                Event::where('id', '=', $id)
                    ->where('status', '=', '0')
                    ->first()
                    ->update([
                        'status' => 2
                    ]);
            }
            $stat = new GenerateStat();
            $stat->generate($this->form_groupId, $this->date);
            $this->dispatchBrowserEvent('success', ['message' => __('event.bulk.confirmReject.success')]);
        }
        $this->cancelBulk();
    }

    public function render()
    {
        if($this->date !== null && $this->form_groupId !== 0) {
            $this->getInfo();
        }

        if($this->date !== null && !$this->error) {            
            $this->error = false;
            return view('livewire.events.modal', [
                'group_data' => $this->group_data,
                // 'service_days' => $this->service_days,
                'day_events' => $this->day_events,
                'day_data' => $this->day_data,
                'editor' => $this->editor,
                'date_data' => $this->date_data
            ]);
        } 
        return view('livewire.events.modal-empty');
    }
}
