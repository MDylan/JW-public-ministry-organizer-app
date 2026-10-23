<?php

namespace App\Livewire\Groups;

use App\Livewire\AppComponent;
use App\Models\Group;
use App\Models\GroupPosters;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class PosterEditModal extends AppComponent
{

    public $groupId = 0;
    private $group = [];
    public $posterId = 0;
    public $fromDate = null;
    public $openModal = false;

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
        // dd($this->state);
    }

    public function openModal($groupId, $posterId = 0) {
        $this->getGroupData($groupId);
        $this->state = [];
        $this->posterId = 0;
        $this->openModal = true;
        if($posterId != 0) {
            // dd('poster');
            $this->posterId = $posterId;
            $this->getPosterData();
        } else {
            $this->state['id'] = "rand_".rand(0, 999);
            if($this->fromDate !== null) {
                // dd('dateset');
                $this->state['show_date'] = $this->fromDate;
            }
        }
        $this->dispatch('show-modal',
            id: 'PosterEditModal',
            livewire: 'groups.poster-edit-modal',
            parameters_back: [
                'groupId' => $this->groupId,
                'fromDate' => $this->fromDate
            ]
        );
    }

    public function openModalFromDate($groupId, $date, $posterId = 0) {
        $this->dispatch('hide-modal', id: 'form');
        $this->fromDate = $date;
        $this->openModal($groupId, $posterId);
    }

    public function savePoster() {
        $this->getGroupData();

        $validatedData = Validator::make($this->state, [
            'show_date' => 'required|date_format:Y-m-d',
            'hide_date' => 'nullable|date_format:Y-m-d|after:show_date',
            'info' => 'required|string|min:3|max:5000'
        ])->validate();

        if($this->posterId) {
            $save = GroupPosters::where('id', '=', $this->posterId)
                            ->where('group_id', '=', $this->groupId)                            
                            ->first();
            $res = $save->update($validatedData);
            if($res) {
                DB::table('group_poster_reads')->where('poster_id', $this->posterId)->delete();
            }
        } else {
            $validatedData['group_id'] = $this->groupId;
            $save = GroupPosters::create($validatedData);
        }

        $this->dispatch('hide-modal',
            id: 'PosterEditModal',
            message: __('group.poster.success'),
            savedMessage: __('app.saved')
        );
        $this->dispatchToParent('refresh');
        $this->reset();
        $this->openModal = false;
    }

    /**
     * Livewire 3 has no emitUp(). This modal is nested in the calendar
     * (events.events) and on the home page (home), never both at once, so the
     * event goes to each by name; the one that is not on the page receives
     * nothing. A plain dispatch() would reach every component on the page that
     * listens for the name, nav bar and side menu included (TODO 44).
     */
    private function dispatchToParent(string $event): void
    {
        foreach (['events.events', 'home'] as $parent) {
            $this->dispatch($event)->to($parent);
        }
    }

    public function hiddenModal($parameters_back) {
        if(isset($parameters_back['fromDate'])) {
            $this->dispatch('openModal', $parameters_back['fromDate'], $parameters_back['groupId'])->to('events.modal');
        }
    }

    public function deletePosterConfirmation() {
        $this->dispatch('show-deletion-confirmation',
            title: __('group.poster.confirmDelete.question'),
            text: __('group.poster.confirmDelete.message'),
            emit: 'deletePoster'
        );
    }

    public function deletePoster() {
        $this->getGroupData();

        $del = GroupPosters::where('id', '=', $this->posterId)
                            ->where('group_id', '=', $this->groupId)
                            ->delete();
        if($del) {
            $this->dispatch('hide-modal',
                id: 'PosterEditModal',
                message: __('group.poster.confirmDelete.success'),
                savedMessage: __('app.saved')
            );
            $this->reset();
            $this->dispatchToParent('pollingOn');
        } else {
            $this->dispatch('error',
                message: __('group.poster.confirmDelete.error')
            );
        }
        $this->openModal = false;
    }

    public function updatedStateInfo() {
        $this->getGroupData();
    }

    public function render()
    {
        return view('livewire.groups.poster-edit-modal', [
            'group' => $this->group
        ]);
    }
}
