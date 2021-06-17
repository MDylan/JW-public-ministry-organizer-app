<?php

namespace App\Http\Livewire\Partials;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class NavBar extends Component
{

    public $notifications = [];
    public $total_notifications = 0;


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
            if(count(auth()->user()->userGroupsNotAccepted)) {
                $this->notifications['groups'] = [
                    'route' => route('groups'),
                    'icon' => 'fas fa-users',
                    'message' => __('app.top_notifies.groups', ['number' => count(auth()->user()->userGroupsNotAccepted)])
                ];
                $this->total_notifications += count(auth()->user()->userGroupsNotAccepted);
            }
            $groups = auth()->user()->groupsAccepted()
                        ->with([
                            'latest_news',
                            'news_log'  => function($q) {
                                $q->where('user_id', '=', Auth::id()); 
                            }
                        ])->get()->toArray();
            foreach($groups as $group) {
                $last_read = 0;
                if(isset($group['news_log'][0]['updated_at'])) {
                    $last_read = Carbon::parse($group['news_log'][0]['updated_at'])->timestamp;
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
        //    dd($groups);
        }
        // $this->refresh();
        return view('livewire.partials.nav-bar');
    }
}
