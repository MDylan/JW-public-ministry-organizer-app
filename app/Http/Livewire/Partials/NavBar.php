<?php

namespace App\Http\Livewire\Partials;

// use App\Models\Event;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class NavBar extends Component
{

    private $notifications = [];
    private $total_notifications = 0;


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
            $groups = Auth::user()->userGroups()->with([
                // 'userGroups',
                'latest_news',
                'news_log'  => function($q) {
                    $q->where('user_id', '=', Auth::id()); 
                }
            ])->get()->toArray();
            // dd($groups);
            $notAccepted = 0;
            $groupsWithRights = [];
            $groupNames = [];
            if(count($groups)) {
                foreach($groups as $group) {
                    if($group['pivot']['accepted_at'] === null) $notAccepted++;

                    if(in_array($group['pivot']['group_role'], ['admin', 'roler']) 
                        && $group['need_approval'] == 1) {
                        $groupsWithRights[] = $group['id'];
                        $groupNames[$group['id']] = $group['name'];
                    }                    

                    $last_read = 0;
                    if(isset($group['news_log']['updated_at'])) {
                        $last_read = Carbon::parse($group['news_log']['updated_at'])->timestamp;
                    }
                    $new_news = 0;
                    if(count($group['latest_news'])) {
                        foreach($group['latest_news'] as $new) {
                            $date = Carbon::parse($new['updated_at'])->timestamp;
                            if($date > $last_read) {
                                $new_news++;
                            }
                        }
                        if($new_news > 0) {
                            $this->notifications['news'] = [
                                'route' => route('groups.news', ['group' => $group['id']]),
                                'icon' => 'fas fa-newspaper',
                                'message' => __('app.top_notifies.group_news', ['number' => $new_news]).' - '.$group['name'] 
                            ];
                            $this->total_notifications += $new_news;
                        }
                    }
                }
            }
            // dd($groups);
            if($notAccepted > 0) {
                $this->notifications['groups'] = [
                    'route' => route('groups'),
                    'icon' => 'fas fa-users',
                    'message' => __('app.top_notifies.groups', ['number' => $notAccepted])
                ];
                $this->total_notifications += $notAccepted;
            }

            if(count($groupsWithRights) > 0) {
                $notAcceptedEvents = DB::table('events')->select(
                        "group_id",
                        DB::raw("(count(id)) as total_events"),
                        DB::raw("DATE_FORMAT(day, '%Y-%m-01') as month")
                    )
                    ->whereIn('group_id', $groupsWithRights)
                    ->where('status', '=', '0')
                    ->where('day', '>=', date("Y-m-d"))
                    ->orderBy('group_id', 'asc')
                    ->orderBy('month', 'asc')
                    ->groupBy('group_id')
                    ->groupBy('month')
                    ->get();
                // dd($notAcceptedEvents->toArray());
                if(count($notAcceptedEvents)) {
                    foreach($notAcceptedEvents as $event) {
                        $date = Carbon::parse($event->month);
                        $month = $date->format("m");
                        $year = $date->format("Y");
                        $this->notifications[] = [
                            'route' => route('jumpToCalendar', [
                                'group' => $event->group_id,
                                'year' => $year, 
                                'month' => $month
                            ]),
                            'icon' => 'fa fa-balance-scale-right',
                            'message' => __('app.top_notifies.group_approvals', [
                                'groupName' => $groupNames[$event->group_id],
                                'events' => $event->total_events,
                                'year' => $year,
                                'month' => $month
                                
                            ])
                        ];
                        $this->total_notifications++;
                    }
                }
            }
        }
        return view('livewire.partials.nav-bar', [
            'notifications' => $this->notifications,
            'total_notifications' => $this->total_notifications
        ]);
    }
}
