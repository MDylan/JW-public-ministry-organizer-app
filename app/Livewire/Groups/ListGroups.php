<?php

namespace App\Livewire\Groups;

use App\Classes\GroupUserMoves;
use App\Livewire\AppComponent;
use App\Models\Group;
use App\Rules\Throttle;
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

    // No 'logoutConfirmed' listener since roadmap TODO 70: leaving a group
    // is the groups.logout route, behind password.confirm and its own
    // last-admin check. The component method skipped the confirmation and
    // was reachable by any client, while the page itself never called it.
    protected $listeners = [
        'rejectConfirmed' => 'rejectConfirmed',
        // 'deleteGroup'
    ];
    /*
    public function confirmGroupRemoval($groupId) {

        $group_check = auth()->user()->userGroupsDeletable()->where('group_id', $groupId)->first();
        if(!$group_check) {
            abort('403');
        }
        $this->groupBeeingRemoved = $groupId;

        $this->dispatch('show-deletion-confirmation',
            title: __('group.deletegroup'),
            text: __('group.areYouSureDelete', ['groupName' => $group_check->name]),
            emit: 'deleteGroup'
        );
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
        $this->dispatch('show-modal',
            id: 'form',
        );
    }

    /**
     * Send request email
     */
    public function requestGroupCreatorPrivilege() {
        $this->state['phone'] = auth()->user()->phone_number;
        $validatedData = Validator::make($this->state, [
            'congregation' => 'required|min:3',
            'reason' => [
                'required',
                'min:10',
                // Every request is an e-mail to the site's admin; nothing
                // limited how many a user could send (roadmap TODO 70).
                new Throttle('group-creator-request-'.auth()->id(), 3, 60),
            ],
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
         $this->dispatch('hide-modal',
            id: 'form',
            message: __('group.request.sent'),
            savedMessage: __('app.saved')
        );
    }

    /**
     * Accept invitation
     */
    public function accept($groupId) {
        $accept = new GroupUserMoves($groupId, Auth::id());
        $res = $accept->acceptInvitation();
        if($res) {
            $this->dispatch('success', message: __('group.accept_saved'));
        } else {
            $this->dispatch('error', message: __('group.accept_error'));
        }
        $this->dispatch('refresh')->to('partials.side-menu');
        $this->dispatch('refresh')->to('partials.nav-bar');
        $user = Auth()->user();
        $user->refresh();
    }

    /**
     * Reject invitation modal
     */
    public function rejectModal($groupId) {
        $this->groupBeeingRejected = $groupId;

        $this->dispatch('show-reject-confirmation');
    }

    /**
     * Rejected invitation, delete request
     */
    public function rejectConfirmed() {
        $reject = new GroupUserMoves($this->groupBeeingRejected, Auth::id());
        $res = $reject->rejectInvitation();

        if($res) {
            $this->dispatch('success', message: __('group.accept_rejected'));
        } else {
            $this->dispatch('error', message: __('group.accept_error'));
        }
        $this->dispatch('refresh')->to('partials.side-menu');
        $this->dispatch('refresh')->to('partials.nav-bar');
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
            $this->dispatch('sweet-error',
                title: __('group.logout.error'),
                message: __('group.logout.no_admin'),
            );
        } else {
            $this->groupBeeingLogout = $groupId;
            $this->dispatch('show-logout-confirmation', groupId: $groupId);
        }
    }

    public function openModal($modalId) {
        $this->dispatch('show-modal', id: $modalId);
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

        $this->dispatch('hide-modal',
            id: 'createGroup',
            message: __('group.groupCreated'),
            savedMessage: __('app.saved')
        );

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
