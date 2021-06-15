<?php

namespace App\Http\Livewire\Groups;

use App\Http\Livewire\AppComponent;
use App\Models\Group;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;

class ListGroups extends AppComponent
{

    public $group;
    public $groupBeeingRemoved = null;
    public $groupBeeingRejected = null;
    public $groupBeeingLogout = null;

    protected $listeners = [
        'rejectConfirmed' => 'rejectConfirmed',
        'logoutConfirmed' => 'logoutConfirmed'
    ];

    public function confirmGroupRemoval($groupId) {
        $this->groupBeeingRemoved = $groupId;
        $this->dispatchBrowserEvent('show-delete-modal');
    }

    //Törli a csoportot, jogosultság ellenőrzéssel együtt
    public function deleteGroup() {        
        $user = Auth()->user(); //  User::findOrFail(Auth::id());
        $del = $user->userGroupsDeletable()->where('group_id', $this->groupBeeingRemoved)->delete();
        
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

    /**
     * Meghívás elfogadása
     */
    public function accept($groupId) {
        $user = Auth()->user(); // User::findOrFail(Auth::id());
        if($user->userGroups()->sync([$groupId => [ 'accepted_at' => date('Y-m-d H:i:s')] ], false)) {
            $this->dispatchBrowserEvent('success', ['message' => __('group.accept_saved')]);
        } else {
            $this->dispatchBrowserEvent('error', ['message' => __('group.accept_error')]);
        }
        $this->emitTo('partials.side-menu', 'refresh');
        $this->emitTo('partials.nav-bar', 'refresh');
        
        $user->refresh();
    }

    /**
     * Meghívás elutasítása, modal hívás
     */
    public function rejectModal($groupId) {
        // dd($groupId);
        $this->groupBeeingRejected = $groupId;

        $this->dispatchBrowserEvent('show-reject-confirmation');
    }

    /**
     * Elutasította a meghívást, törlöm a kérést.
     */
    public function rejectConfirmed() {
        $group = Group::findOrFail($this->groupBeeingRejected);
        $res = $group->groupUsers()->detach(Auth::id());
        if($res) {
            $this->dispatchBrowserEvent('success', ['message' => __('group.accept_rejected')]);
        } else {
            $this->dispatchBrowserEvent('error', ['message' => __('group.accept_error')]);
        }
        $this->emitTo('partials.side-menu', 'refresh');
        $this->emitTo('partials.nav-bar', 'refresh');
    }

    /**
     * Kilépés előtti modal
     */
    public function confirmLogoutModal($groupId) {
        $userId = Auth::id();
        $group = Group::findOrFail($groupId);
        $users = $group->groupUsers()->get()->toArray();
        $admins = 0;
        foreach($users as $user) {
            $pivot = $user['pivot'];
            if($pivot['group_role'] == 'admin' && $user['id'] != $userId) {
                $admins++;
            }
        }
        if($admins == 0) {
            //nincs más admin, nem léphet ki
            $this->dispatchBrowserEvent('sweet-error', [
                'title' => __('group.logout.error'),
                'message' => __('group.logout.no_admin'),
            ]);
        } else {
            $this->groupBeeingLogout = $groupId;
            $this->dispatchBrowserEvent('show-logout-confirmation');
        }
    }

    /**
     * Megerősítette a kilépését
     */
    public function logoutConfirmed() {
        $group = Group::findOrFail($this->groupBeeingLogout);
        auth()->user()->feature_events()
                    ->where('group_id', $this->groupBeeingLogout)
                    ->delete();
        $res = $group->groupUsers()->detach(Auth::id());
        if($res) {
            $this->dispatchBrowserEvent('success', ['message' => __('group.logout.success')]);
        } else {
            $this->dispatchBrowserEvent('error', ['message' => __('group.logout.error')]);
        }
    }

    public function render()
    {        
        $user = Auth()->user(); // User::findOrFail(Auth::id());
        $groups = $user->userGroups()->paginate(20);

        return view('livewire.groups.list-groups', [
            'groups' => $groups
        ]);
    }
}
