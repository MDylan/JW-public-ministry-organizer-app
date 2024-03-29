<?php

namespace App\Http\Livewire\Groups;

use App\Classes\GroupUserMoves;
use App\Http\Livewire\AppComponent;
use App\Models\Group;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
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
        'logoutConfirmed' => 'logoutConfirmed',
        // 'deleteGroup'
    ];
    /*
    public function confirmGroupRemoval($groupId) {

        $group_check = auth()->user()->userGroupsDeletable()->where('group_id', $groupId)->first();
        if(!$group_check) {
            abort('403');
        }
        $this->groupBeeingRemoved = $groupId;

        $this->dispatchBrowserEvent('show-deletion-confirmation', [
            'title' => __('group.deletegroup'),
            'text' => __('group.areYouSureDelete', ['groupName' => $group_check->name]),
            'emit' => 'deleteGroup'
        ]);
    }

    public function deleteGroup() {
        if($this->groupBeeingRemoved !== null) {
            return redirect()->route('groups.delete', [
                'group' => $this->groupBeeingRemoved
            ]);
        }
    }
    */

    /**
     * Show modal
     */
    public function askGroupCreatorPrivilege() {
        $this->showEditModal = false;
        $this->state = [];
        $this->dispatchBrowserEvent('show-modal', [
            'id' => 'form',
        ]);
    }

    /**
     * Send request email
     */
    public function requestGroupCreatorPrivilege() {
        $this->state['phone'] = auth()->user()->phone_number;
        $validatedData = Validator::make($this->state, [
            'congregation' => 'required|min:3',
            'reason' => 'required|min:10',
            'phone' => 'required|numeric',
        ])->validate();

        Mail::send('emails.groupCreatorRequest', [
            'name' => auth()->user()->name,
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
         $this->dispatchBrowserEvent('hide-modal', [
            'id' => 'form',
            'message' => __('group.request.sent'),
            'savedMessage' => __('app.saved')
        ]);
    }

    /**
     * Accept invitation
     */
    public function accept($groupId) {
        $accept = new GroupUserMoves($groupId, Auth::id());
        $res = $accept->acceptInvitation();
        if($res) {
            $this->dispatchBrowserEvent('success', ['message' => __('group.accept_saved')]);
        } else {
            $this->dispatchBrowserEvent('error', ['message' => __('group.accept_error')]);
        }
        $this->emitTo('partials.side-menu', 'refresh');
        $this->emitTo('partials.nav-bar', 'refresh');
        $user = Auth()->user();
        $user->refresh();
    }

    /**
     * Reject invitation modal
     */
    public function rejectModal($groupId) {
        $this->groupBeeingRejected = $groupId;

        $this->dispatchBrowserEvent('show-reject-confirmation');
    }

    /**
     * Rejected invitation, delete request
     */
    public function rejectConfirmed() {
        $reject = new GroupUserMoves($this->groupBeeingRejected, Auth::id());
        $res = $reject->rejectInvitation();

        if($res) {
            $this->dispatchBrowserEvent('success', ['message' => __('group.accept_rejected')]);
        } else {
            $this->dispatchBrowserEvent('error', ['message' => __('group.accept_error')]);
        }
        $this->emitTo('partials.side-menu', 'refresh');
        $this->emitTo('partials.nav-bar', 'refresh');
    }

    /**
     * Modal before quit
     */
    public function confirmLogoutModal($groupId) {
        // $userId = Auth::id();
        // $group = Group::findOrFail($groupId);
        // $users = $group->groupAdmins()->get()->toArray();
        // $total_group = 1;
        // $admins = 0;
        // $main_admins = [];
        // foreach($users as $user) {
        //     if($user['id'] != $userId) {
        //         $admins++;
        //         $main_admins[$user['id']] = 1;
        //     }
        // }
        // //check child groups too!
        // $child_groups = $group->childGroups()->with('groupAdmins')->get()->toArray();
        // if(count($child_groups) > 0) {
        //     $total_group += count($child_groups);
        //     foreach($child_groups as $child_group) {
        //         foreach($child_group['group_admins'] as $user) {
        //             if($user['id'] != $userId) {
        //                 $admins++;
        //                 $main_admins[$user['id']]++;
        //             }
        //         }
        //     }
        // }

        if(pwbs_check_group_other_admins($groupId, auth()->id()) === false) {
            //no other admin
            $this->dispatchBrowserEvent('sweet-error', [
                'title' => __('group.logout.error'),
                'message' => __('group.logout.no_admin'),
            ]);
        } else {
            $this->groupBeeingLogout = $groupId;
            $this->dispatchBrowserEvent('show-logout-confirmation', ['groupId' => $groupId]);
        }
    }

    /**
     * Confirmed quit
     */
    public function logoutConfirmed() {

        if(pwbs_check_group_other_admins($this->groupBeeingLogout, auth()->id()) === false) {
            //no other admin
            $this->dispatchBrowserEvent('sweet-error', [
                'title' => __('group.logout.error'),
                'message' => __('group.logout.no_admin'),
            ]);
        } else {
            $logout = new GroupUserMoves($this->groupBeeingLogout, auth()->id());
            $res = $logout->detach();
            if($res) {
                $this->dispatchBrowserEvent('success', ['message' => __('group.logout.success')]);
            } else {
                $this->dispatchBrowserEvent('error', ['message' => __('group.logout.error')]);
            }
        }
    }

    public function openModal($modalId) {
        $this->dispatchBrowserEvent('show-modal', ['id' => $modalId]);
    }

    public function createGroup() {

        if (! Gate::allows('is-groupcreator')) {
            abort(403);
        }

        $validatedData = Validator::make($this->state, [
            'name' => 'required|string|max:50|min:2',
        ])->validate();

        $user = Auth()->user();
        $group = Group::create($validatedData);
        $user->userGroups()->save($group, ['group_role' => 'admin', 'accepted_at' => date('Y-m-d H:i:s')]);

        $this->dispatchBrowserEvent('hide-modal', [
            'id' => 'createGroup',
            'message' => __('group.groupCreated'),
            'savedMessage' => __('app.saved')
        ]);

        $this->state = [];
        auth()->user()->fresh();
    }

    public function render()
    {        
        $user = Auth()->user(); 
        $groups = $user->userGroups()->paginate(20);

        return view('livewire.groups.list-groups', [
            'groups' => $groups
        ]);
    }
}
