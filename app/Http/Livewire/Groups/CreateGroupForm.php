<?php

namespace App\Http\Livewire\Groups;

use App\Http\Livewire\AppComponent;
use App\Models\User;
use App\Models\Group;
use App\Models\GroupDay;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class CreateGroupForm extends AppComponent
{

    /**
     * Alapértelmezett adatok
     */
    public $state = [
        'max_extend_days' => 60,
        'min_publishers' => 2,
        'max_publishers' => 2,
        'min_time' => 60,
        'max_time' => 240,
    ];

    /**
     * Elmenti a felhasználó adatait
     */
    public function createGroup() {
        
        // dd($this->state);

        $validatedData = Validator::make($this->state, [
            'name' => 'required|string|max:50|min:2',
            'max_extend_days' => 'required|numeric|digits_between:1,365',
            'min_publishers' => 'required|numeric|digits_between:1,365|lte:max_publishers',
            'max_publishers' => 'required|numeric|digits_between:1,365|gte:min_publishers',
            'min_time' => 'required|numeric|in:30,60,120|lte:max_time',
            'max_time' => 'required|numeric|in:60,120,180,240,320|gte:min_time',
            'days.*.start_time' => 'required|date_format:H:i|before:days.*.end_time',
            'days.*.end_time' => 'required|date_format:H:i|after:days.*.start_time',
            'days.*.day_number' => 'required',
        ])->validate();
        // dd($validatedData['days']);

        $user = User::find(Auth::id());
        $group = Group::create($validatedData);
        // dd('itt');
        foreach($validatedData['days'] as $d => $day) {
            // dd($day);
            if(!$day['day_number']) continue;
            $day = new GroupDay([
                'day_number' => $day['day_number'],
                'start_time' => $day['start_time'],
                'end_time' => $day['end_time']
            ]);
            $group->days()->save($day);    
        }

        $user->userGroups()->save($group, ['group_role' => 'admin']);

        $this->dispatchBrowserEvent('alert', ['message' => __('group.groupCreated')]);

    }

    public function render()
    {
        return view('livewire.groups.create-group-form');
    }
}
