<?php

namespace App\Http\Livewire\Groups;

use App\Http\Livewire\AppComponent;
use App\Models\Group;
// use App\Models\GroupNews;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Facades\Validator;

class NewsEdit extends AppComponent
{

    public $group;
    public $new_data;

    public $listeners = [
        'deleteConfirmed'
    ];

    public function mount(Group $group, $new = false) {
        $this->group = $group;

        if($new) {
            $this->new_data = $this->group->news()->whereId($new)->firstOrFail();
            $this->state = $this->new_data->toArray();
            
            // dd($new_data);
        }
    }

    public function editNews() {
        // dd($this->state);
        if(isset($this->state['title']))
            $this->state['title'] = strip_tags($this->state['title']);

        $validatedData = Validator::make($this->state, [
            'title' => 'required|string|max:50|min:2',
            'date' => 'required|date_format:Y-m-d',
            'status' => 'numeric|in:0,1',
            'content' => 'required',
        ])->validate();

        $validatedData['user_id'] = Auth::id();

        if(isset($this->state['id'])) {
            $this->new_data->update($validatedData);
            Session::flash('message', __('news.edited')); 
        } else {
            $this->group->news()->create($validatedData);
            Session::flash('message', __('news.created')); 
        }        
        redirect()->route('groups.news', ['group' => $this->group->id]);
    }

    public function confirmNewDelete() {
        $this->dispatchBrowserEvent('show-newsDelete-confirmation');
    }

    public function deleteConfirmed() {
        if(isset($this->state['id'])) {
            $this->new_data->delete();
            Session::flash('message', __('news.confirmDelete.success')); 
        } else {
            Session::flash('message', __('news.confirmDelete.error')); 
        }
        redirect()->route('groups.news', ['group' => $this->group->id]);
    }

    public function render()
    {
        return view('livewire.groups.news-edit', [
            // 'group' => $this->group
        ]);
    }
}
