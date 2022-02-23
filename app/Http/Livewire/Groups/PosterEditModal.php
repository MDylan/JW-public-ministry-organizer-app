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
    public $fromDate = null;

    protected $listeners = [         
        'openModal',
        'openModalFromDate',
        'deletePoster',
        'hiddenModal'
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
        } else {
            if($this->fromDate !== null) {
                // dd('dateset');
                $this->state['show_date'] = $this->fromDate;
            }
        }
        $this->dispatchBrowserEvent('show-modal', [
            'id' => 'PosterEditModal',
            'livewire' => 'groups.poster-edit-modal',
            'parameters_back' => [
                'groupId' => $this->groupId,
                'fromDate' => $this->fromDate
            ]
        ]);
    }

    public function openModalFromDate($groupId, $date, $posterId = 0) {
        $this->dispatchBrowserEvent('hide-modal', ['id' => 'form']);
        $this->fromDate = $date;
        $this->openModal($groupId, $posterId);
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
        $this->emitUp('refresh');
        $this->reset();
    }

    public function hiddenModal($parameters_back) {
        if(isset($parameters_back['fromDate'])) {
            $this->emitTo('events.modal', 'openModal', $parameters_back['fromDate'], $parameters_back['groupId']);
        }
    }

    public function deletePosterConfirmation() {
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
            $this->emitUp('pollingOn');
        } else {
            $this->dispatchBrowserEvent('error', [
                'message' => __('group.poster.confirmDelete.error')
            ]);
        }
    }

    public function render()
    {
        return view('livewire.groups.poster-edit-modal', [
            'group' => $this->group
        ]);
    }
}
