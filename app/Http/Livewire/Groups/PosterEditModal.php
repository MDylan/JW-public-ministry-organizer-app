<?php

namespace App\Http\Livewire\Groups;

use App\Http\Livewire\AppComponent;
use App\Models\Group;
use App\Models\GroupPosters;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;

class PosterEditModal extends AppComponent
{

    public $groupId = 0;
    private $group = [];
    public $posterId = 0;

    protected $listeners = [         
        'openModal',
        'deletePoster'
    ];

    public function getGroupData($groupId = false) {
        if(!$groupId) {
            $groupId = $this->groupId;
        } 
        $group = Group::findorFail($groupId);
        if($group->editors()->wherePivot('user_id', Auth::id())->count() == 0) {
            abort('403');
        }
        $this->groupId = $group->id;
        $this->group = $group;
    }

    public function getPosterData() {
        $this->state = GroupPosters::where('id', '=', $this->posterId)
                            ->where('group_id', '=', $this->groupId)
                            ->first()
                            ->toArray();
    }

    public function openModal($groupId, $posterId = 0) {
        $this->getGroupData($groupId);
        $this->state = [];
        $this->posterId = 0;
        if($posterId != 0) {
            // dd('poster');
            $this->posterId = $posterId;
            $this->getPosterData();
        }
        $this->dispatchBrowserEvent('show-modal', ['id' => 'PosterEditModal']);
        
    }

    public function savePoster() {
        $this->getGroupData();

        $validatedData = Validator::make($this->state, [
            'show_date' => 'required|date_format:Y-m-d',
            'hide_date' => 'nullable|date_format:Y-m-d|after:show_date',
            'info' => 'required|string|min:3|max:600'
        ])->validate();

        if($this->posterId) {
            $save = GroupPosters::where('id', '=', $this->posterId)
                            ->where('group_id', '=', $this->groupId);
            $save->update($validatedData);
        } else {
            $validatedData['group_id'] = $this->groupId;
            $save = GroupPosters::create($validatedData);
        }

        $this->dispatchBrowserEvent('hide-modal', [
            'id' => 'PosterEditModal',
            'message' => __('group.poster.success'),
            'savedMessage' => __('app.saved')
        ]);

        $this->reset();
        $this->emitUp('refresh');
        
    }

    public function deletePosterConfirmation() {
        // 

        $this->dispatchBrowserEvent('show-deletion-confirmation', [
            'title' => __('group.poster.confirmDelete.question'),
            'text' => __('group.poster.confirmDelete.message'),
            'emit' => 'deletePoster'
        ]);
    }

    public function deletePoster() {
        $this->getGroupData();

        $del = GroupPosters::where('id', '=', $this->posterId)
                            ->where('group_id', '=', $this->groupId)
                            ->delete();
        if($del) {
            $this->dispatchBrowserEvent('hide-modal', [
                'id' => 'PosterEditModal',
                'message' => __('group.poster.confirmDelete.success'),
                'savedMessage' => __('app.saved')
            ]);
            $this->reset();
            $this->emitUp('refresh');
        } else {
            $this->dispatchBrowserEvent('error', [
                'message' => __('group.poster.confirmDelete.error')
            ]);
        }
    }

    public function render()
    {
        return view('livewire.groups.poster-edit-modal');
    }
}
