<?php

namespace App\Http\Livewire\Groups;

use App\Http\Livewire\AppComponent;
use App\Models\Group;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class ListGroups extends AppComponent
{

    public $group;
    public $groupBeeingRemoved = null;


    public function confirmGroupRemoval($groupId) {
        $this->groupBeeingRemoved = $groupId;
        $this->dispatchBrowserEvent('show-delete-modal');
    }

    //Törli a csoportot, jogosultság ellenőrzéssel együtt
    public function deleteGroup() {
        
        $user = User::findOrFail(Auth::id());
        $del = $user->userGroupsDeletable()->whereId($this->groupBeeingRemoved)->delete();
        
        if($del == 1) {
            $user->userGroupsDeletable()->detach($this->groupBeeingRemoved);
            $this->dispatchBrowserEvent('hide-delete-modal', ['message' => __('group.groupDeleted')]);
        } else {
            $this->dispatchBrowserEvent('hide-delete-modal', ['errorMessage' => __('app.notAllowed')]);
        }
    }

    /**
     * Megjeleníti a modalt, amikor a gombra kattintunk
     */
    public function askGroupCreatorPrivilege() {
        $this->showEditModal = false;
        $this->state = [];
        $this->dispatchBrowserEvent('show-form');
    }

    /**
     * Elküldi a csoport létrehozási jogosultságról az igénylést emailben
     */
    public function requestGroupCreatorPrivilege() {
        $this->state['phone'] = auth()->user()->phone;
        $validatedData = Validator::make($this->state, [
            'congregation' => 'required|min:3',
            'reason' => 'required|min:10',
            'phone' => 'required|numeric',
        ])->validate();

        Mail::send('emails.groupCreatorRequest', [
            'name' => auth()->user()->last_name.' '.auth()->user()->first_name,
            'email' => auth()->user()->email,
            'phone' => $validatedData['phone'],
            'congregation' => strip_tags($validatedData['congregation']),
            'reason' => strip_tags($validatedData['reason']),
         ],
            function ($message) {
                    $message->to(config('mail.from.address'))
                    ->replyTo(auth()->user()->email)
                    ->subject(__('group.requestMail.subject'));
         });
         $this->dispatchBrowserEvent('hide-form', ['message' => __('group.request.sent')]);
    }

    public function accept($groupId) {

        $user = User::findOrFail(Auth::id());
        if($user->userGroups()->sync([$groupId => [ 'accepted_at' => date('Y-m-d H:i:s')] ], false)) {
            $this->dispatchBrowserEvent('success', ['message' => __('group.accept_saved')]);
        } else {
            $this->dispatchBrowserEvent('error', ['message' => __('group.accept_error')]);
        }
        $this->emitTo('partials.side-menu', 'refresh');
        $this->emitTo('partials.nav-bar', 'refresh');
        $user->refresh();
    }

    public function render()
    {        
        // $groups = Group::whereHas('groupUsers', function ($query) {
        //     return $query->where('users.id', '=', Auth::id());
        // })->with('groupUsers')->paginate(20);


        // $groups = Group::with('currentList')->get();
        $user = User::findOrFail(Auth::id());
        $groups = $user->userGroups()->paginate(20);



        // foreach($user->userGroups as $u) {
        //     dd($u);
        // }

        // foreach($groups as $group) {
        //     dd($group->pivot);
        // }
        // // dd($groups);

        return view('livewire.groups.list-groups', [
            'groups' => $groups
        ]);
    }
}
