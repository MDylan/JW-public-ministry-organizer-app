<?php

namespace App\Livewire\Groups;

use App\Classes\GroupUserMoves;
use App\Livewire\AppComponent;
use App\Livewire\Concerns\AuthorizesGroupAccess;
use App\Models\Group;
use App\Models\GroupUser;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use App\Models\User;
use App\Notifications\FinishRegistration;
use App\Notifications\GroupParentGroupAttachedNotification;
use App\Notifications\GroupParentGroupDetachedNotification;
use App\Notifications\GroupUserAddedNotification;
use App\Notifications\LoginData;
use App\Notifications\UserProfileChangedNotification;
use App\Notifications\UserProfileRenewalAdminNotification;
use App\Notifications\UserProfileRenewalNotification;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Attributes\Locked;

/**
 * A group's member list and its editor actions.
 *
 * Roadmap TODO 70: the group and the ids the actions work on are #[Locked];
 * the caller's role is an ACCEPTED membership's (a pending invitation used
 * to count, and a pending admin passed every admin check); a roler cannot
 * remove an admin, which they already could not demote; removal is only
 * of someone in the group; and a pending invitee's profile is neither
 * shown to nor editable by the group's editors - inviting an e-mail
 * address used to reveal and overwrite that account's name and phone.
 */
class ListUsers extends AppComponent
{
    use AuthorizesGroupAccess;

    #[Locked]
    public $groupId;
    public $state;
    #[Locked]
    public $selected_user;
    public $searchTerm = null;
    public $filter = [];
    //for search with an url string
    protected $queryString = [
        'searchTerm' => ['except' => ''],
    ];
    private $group = null;
    private $role = null;

    static $group_roles = [
        'member', 
        'helper',
        'roler',
        'admin',
    ];

    #[Locked]
    public $userIdBeeingRemoved = null;
    #[Locked]
    public $detachId = null;
    public $new_users = "";
    public $email_language = "";

    public $new_parent_group_id = 0;

    protected $listeners = [         
        'openModal', 
        'createUser',
        'editUser',         
        'deleteUser',
        'detachChildGroup',
        'detachParentGroup',
        'setCopyInfo',
        'openUserRenewalModal'
    ];

    private $copy_datas = [];
    public $copy_fields = [];

    public function mount($group) {
        $this->groupId = $group;
        $this->email_language = config('settings_default_language');
    }

    public function openModal($modalId = 'UserModal') {
        $this->dispatch('show-modal', id: $modalId);
    }

    /**
     * Save user's info
     */
    public function createUser() {   

        $this->getGroupInfo();
        if($this->isNotHelper()) {
            abort(403);
        }

        //we cannot add new user, if this is a child group
        // The bare return used to be SILENT: no error message, no modal
        // feedback was produced, so the administrator was left believing
        // the invite went out. The restriction itself is correct - publishers
        // get into a child group through the parent group -, only the silence isn't.
        if($this->group->parent_group_id) {
            $this->addError('new_users', __('group.user.add.error_this_is_child'));
            return;
        }

        $email_array = preg_split("/\R/", trim($this->new_users)); 
        $email = [];
        if(count($email_array)) {
            foreach($email_array as $mail) {
                $tmail = trim($mail);
                if(strlen($tmail) == 0) continue;
                $email['email'][] = $tmail;
            }
        }

        $v = Validator::make($email, [
            'email.*' => 'required|email:filter',
        ]); 

        $v->after(function ($validator) {            
            $langs = config('available_languages');
            if(!isset($langs[$this->email_language])) {
                $validator->errors()->add(
                    'email_language', __('group.user.add.email_language_error')
                );
            }
        });
        
        $validatedData = $v->validate();

        if(count($validatedData['email'] ?? [])) {
            $current_users = $this->group->groupUsers()->pluck('users.id')->toArray();

            foreach($validatedData['email'] as $mail) {
                $us = User::where('email',  $mail)->firstOr(function () use ($mail) {
                    $password = Str::random(10);
                    $u = User::create([
                        'email' => $mail,
                        'password' => bcrypt($password),
                        'language' => $this->email_language
                    ]);
                    // Seven days. This was addMinutes(7 * 24 * 60 * 60) -
                    // seconds counted as minutes, about 420 days (TODO 70).
                    $url = URL::temporarySignedRoute(
                        'finish_registration', now()->addDays(7), [
                            'id' => $u->id
                        ]
                    );
                    $u->notify(
                        new FinishRegistration([
                            'groupAdmin' => auth()->user()->name, 
                            'userMail' => $mail,
                            'url' => $url
                        ])
                    );
                    return $u;
                });
                //skip user, if he is already in the group
                if(in_array($us->id, $current_users)) continue;

                $add = new GroupUserMoves($this->group->id, $us->id);
                $add->attach();
            }

            $this->dispatch('hide-modal',
                id: 'UserAddModal',
                message: __('group.user.add.success', ['number' => count($validatedData['email'])]),
                savedMessage: __('app.saved')
            );
        }
        $this->new_users = "";   

    }

    /**
     * Load user data into edit modal
     */
    public function editUser($UserId) {
        $this->getGroupInfo();
        if($this->isNotHelper()) {
            abort(403);
        }

        $user = $this->group->groupUsers()->where('user_id', '=', $UserId)->first();
        if($user === null) abort(403);
        $profile = self::profileIsTheGroups($user);
        $this->selected_user = [
            'id' => $user->id,
            'profile' => $profile,
            'user' => [
                'name' => $profile ? $user->name : null,
                'phone_number' => $profile ? $user->phone_number : null,
                'congregation' => $profile ? $user->congregation : null,
            ],
            'email' => $user->email,
        ];
        $this->state = [
            'group_role' => $user->pivot->group_role,
            'note' => $user->pivot->note,
            'hidden' => $user->pivot->hidden,
            'user' => [
                'name' => $profile ? $user->name : null,
                'phone_number' => $profile ? $user->phone_number : null,
                'congregation' => $profile ? $user->congregation : null,
                'email_verified_at' => $user->email_verified_at,
            ],
            'finish_guest_registration' => 0,
            'message_use' => $user->pivot->message_use,
            'message_send_priority' => $user->pivot->message_send_priority,
        ];

        $this->dispatch('show-modal', id: 'UserModal');
    }

    public function updateUser() {

        $this->getGroupInfo();
        if($this->isNotHelper()) {
            abort(403);
        }       
        $admins = $this->group->groupAdmins()->get()->count();
        $current_user = $this->group->groupUsers()->where('user_id', Auth::id())->first();
        $selected_user = $this->group->groupUsers()->where('user_id', $this->selected_user['id'])->first();
        if($selected_user === null) abort(403);

        if(!in_array($this->state['group_role'], $this->maxRoles())) {
            //if user have lower right, he dont change privilage
            $this->state['group_role'] = $selected_user->pivot->group_role;
        }

        // The view offers it only in a top-level group; so does the server.
        if($selected_user->email_verified_at === null && ! $this->group->parent_group_id) {
            $finish_guest = [0,1];
        } else {
            $finish_guest = [0];
        }

        $v = Validator::make($this->state, [
            'hidden' => 'required|boolean',
            'note' => 'nullable|string|max:50', 
            'group_role' =>  [
                Rule::In(self::$group_roles),
            ],
            'finish_guest_registration' => [
                Rule::In($finish_guest),
            ],
            'message_use' => 'sometimes|numeric|in:0,1,2',
            'message_send_priority' => 'sometimes|boolean',
        ]);
        $v->after(function ($validator) use ($admins, $current_user, $selected_user) {
            //if modified user rule
            if($selected_user->pivot->group_role != $this->state['group_role']) {
                // if ($admins <= 1 && $selected_user->pivot->group_role == 'admin') {
                if(pwbs_check_group_other_admins($this->group->id, $this->selected_user['id']) === false) {
                    $validator->errors()->add(
                        'users', __('group.error_no_admin_user')
                    );
                }
                if($current_user->pivot->group_role != 'admin' &&  $selected_user->pivot->group_role == 'admin') {
                    $validator->errors()->add(
                        'users', __('group.error_no_right_to_remove_admin')
                    );
                }
                if($current_user->pivot->group_role != 'admin' && $this->state['group_role'] == 'admin') {
                    $validator->errors()->add(
                        'users', __('group.error_no_right')
                    );
                }
            }
        });
        
        $validatedData = $v->validate();

        // Validation of the profile fields used to run AFTER the pivot save, with a
        // separate Validator::make() and no transaction. An invalid name therefore
        // left behind a partially saved record: the note and the role were
        // already in the database, but the profile wasn't, and the user
        // only saw an error message. All validation was therefore moved to
        // BEFORE the first write, and whatever remains runs in a single transaction.
        // Only the profile of someone who is in the group (or whose account
        // this group's invitation created) is the group's to edit.
        $profileChanged = self::profileIsTheGroups($selected_user) && (
               $selected_user->name !== ($this->state['user']['name'] ?? null)
            || $selected_user->phone_number !== ($this->state['user']['phone_number'] ?? null)
            || $selected_user->congregation !== ($this->state['user']['congregation'] ?? null));

        $userValidatedData = null;
        if($profileChanged) {
            $userValidatedData = Validator::make($this->state['user'], [
                'name' => 'required|string|max:50|min:2',
                'phone_number' => 'nullable|numeric',
                'congregation' => 'nullable|string|max:50|min:2',
            ])->validate();
        }

        // finish_guest_registration is NOT a column of the group_user table: editUser()
        // puts it into the form state, validation lets it through, and up to now the
        // full $validatedData was going to syncWithoutDetaching(). This only didn't
        // blow up because the GroupUser custom Pivot class has a $fillable
        // list, so Laravel's updateExistingPivotUsingCustomClass() path
        // silently dropped the unknown key via fill() - a framework-version-dependent
        // path that would fail with an unknown column error on a raw update/insert
        // fallback. The key is therefore stripped out here, and the control value is kept separately.
        $finishGuestRegistration = (int) ($validatedData['finish_guest_registration'] ?? 0);
        unset($validatedData['finish_guest_registration']);

        $user_sync[$this->selected_user['id']] = $validatedData;

        $notifications = [];

        DB::transaction(function () use (
            $user_sync,
            $profileChanged,
            $userValidatedData,
            $finishGuestRegistration,
            $selected_user,
            &$notifications
        ) {
            $this->group->groupUsersAll()->syncWithoutDetaching($user_sync);

            if($profileChanged) {
                $user = User::find($this->selected_user['id']);
                $user->update($userValidatedData);

                $data = [
                    'old' => [
                        'name' => $selected_user->name,
                        'phone_number' => $selected_user->phone_number,
                        'congregation' => $selected_user->congregation
                    ],
                    'new' => [
                        'name' => $userValidatedData['name'],
                        'phone_number' => $userValidatedData['phone_number'],
                        'congregation' => $userValidatedData['congregation']
                    ],
                    'userName' => auth()->user()->name
                ];

                $notifications[] = [$user, new UserProfileChangedNotification($data)];
            }

            if($finishGuestRegistration === 1 && $selected_user->role === 'registered') {
                $password = Str::random(10);
                $selected_user->fill([
                    'password' => Hash::make($password),
                    'role' => 'activated',
                    'email_verified_at' => now()
                ])->save();
                $accept = new GroupUserMoves($this->groupId, $selected_user->id);
                $accept->acceptInvitation();

                $data = [
                    'groupAdmin' => auth()->user()->name,
                    'userMail' => $selected_user->email,
                    'userPassword' => $password
                ];

                $notifications[] = [$selected_user, new LoginData($data)];
            }
        });

        // Notifications go out AFTER the COMMIT. A job placed on a queue inside a
        // transaction could start before the associated rows become visible,
        // and a rollback would then send out a password that isn't saved anywhere.
        foreach($notifications as [$notifiable, $notification]) {
            $notifiable->notify($notification);
        }

        $this->dispatch('hide-modal',
            id: 'UserModal',
            message: __('group.user.saved').' ('.$selected_user->name.')',
            savedMessage: __('app.saved')
        );
    }

    public function confirmUserRemoval($userId) {
        
        $this->getGroupInfo();
        if($this->isNotHelper()) {
            abort(403);
        }
        $selected_user = $this->group->groupUsers()->where('user_id', $userId)->first();
        if($selected_user === null) abort(403);
        if($this->mayNotRemove($selected_user)) {
            $this->dispatch('sweet-error',
                title: __('group.logout.error'),
                message: __('group.error_no_right_to_remove_admin'),
            );
            return;
        }
        $this->userIdBeeingRemoved = $selected_user->id;


        if(pwbs_check_group_other_admins($this->group->id, $userId) === false) {
            //no other admin
            $this->dispatch('sweet-error',
                title: __('group.logout.error'),
                message: __('group.logout.no_admin'),
            );
            return;
        } 

        $this->dispatch('show-deletion-confirmation',
            title: __('group.user.confirmDelete.question', ['name' => $selected_user->name]),
            text: __('group.user.confirmDelete.message'),
            emit: 'deleteUser'
        );
    }

    public function deleteUser() {
        $this->getGroupInfo();
        if($this->isNotHelper()) {
            abort(403);
        }

        if($this->group->parent_group_id > 0) {
            $this->dispatch('error',
                message: __('group.user.confirmDelete.error_this_is_child')
            );
            return;
        }

        // Re-checked here: this is a listener of its own, and the role may
        // have changed since the confirmation.
        $selected_user = $this->group->groupUsers()->where('user_id', $this->userIdBeeingRemoved)->first();
        if($selected_user === null) abort(403);
        if($this->mayNotRemove($selected_user)) {
            $this->dispatch('sweet-error',
                title: __('group.logout.error'),
                message: __('group.error_no_right_to_remove_admin'),
            );
            return;
        }

        if(pwbs_check_group_other_admins($this->group->id, $this->userIdBeeingRemoved) === false) {
            //no other admin
            $this->dispatch('sweet-error',
                title: __('group.logout.error'),
                message: __('group.logout.no_admin'),
            );
            return;
        }

        $detach = new GroupUserMoves($this->group->id, $this->userIdBeeingRemoved);
        $detach->detach();

        $this->dispatch('success',
            message: __('group.user.confirmDelete.success')
        );

        $this->userIdBeeingRemoved = null;
    }

    public function updatedSearchTerm() {
        $this->resetPage();
    }

    public function filterMyself() {
        $this->filter['myself'] = !($this->filter['myself'] ?? false);
        if(($this->filter['myself'] ?? null) == true) {
            $this->filter['signs'] = [];
        }
        $this->filter['online'] = false;
        $this->filter['inactive'] = false;
        $this->resetPage();
    }

    public function filterIcon($icon) {
        $this->getGroupInfo();
        if(($this->filter['myself'] ?? null) == true) {
            $this->filter['myself'] = false;
        }
        $group_signs = $this->group->signs;
        if(($group_signs[$icon]['checked'] ?? null) == true) {
            $this->filter['signs'][$icon] = !($this->filter['signs'][$icon] ?? false);
        }
        $this->resetPage();
    }

    public function filterOff() {
        $this->filter = [];
        $this->resetPage();
    }

    public function filterOnline() {
        $this->filter['online'] = !($this->filter['online'] ?? false);
        if(($this->filter['myself'] ?? null) == true) {
            $this->filter['myself'] = false;
        }
        $this->filter['inactive'] = false;
        // The other filters (updatedSearchTerm, filterMyself, filterIcon,
        // filterOff) all reset the paginator cursor; these two didn't. Standing on
        // page 3, the online filter therefore returned an empty list even though
        // there were results - the paginator asked for page 3 of the narrowed set.
        $this->resetPage();
    }

    public function filterInactive() {
        $this->filter['inactive'] = !($this->filter['inactive'] ?? false);
        if(($this->filter['myself'] ?? null) == true) {
            $this->filter['myself'] = false;
        }
        $this->filter['online'] = false;
        $this->resetPage();
    }

    public function clearSearch() {
        $this->searchTerm = null;
    }

    public function openUserRenewalModal($UserId) {
        $this->getGroupInfo();
        if($this->isNotHelper()) {
            abort(403);
        }

        $user = $this->group->groupUsers()->where('user_id', '=', $UserId)->first();
        $this->selected_user = [
            'id' => $user->id,
            'user' => [
                'name' => $user->name,
                'last_activity' => $user->last_activity,
                'last_date' => \Carbon\Carbon::parse($user->last_activity)->addMonths(config('gdpr.settings.ttl') ?? 6)
            ]
        ];

        $this->dispatch('show-modal', id: 'userRenewalModal');
    }

    public function userRenewal() {
        $this->getGroupInfo();
        if($this->isNotHelper()) {
            abort(403);
        }

        $error = false;
        $userExists = $this->group->groupUsers()->where('user_id', '=', $this->selected_user['id'])->first();
        if($userExists) {
            if(\Carbon\Carbon::parse($userExists->last_activity)->lt(now()->subMonths(config('gdpr.settings.ttl') ?? 6)->addDays(14))) {
                //update user last_activity to now
                $user = User::find($this->selected_user['id']);
                $user->update(['last_activity' => now()]);

                $data = [
                    'userName' => $userExists->name,
                    'adminName' => auth()->user()->name
                ];

                $user->notify(
                    new UserProfileRenewalNotification($data)
                );

                Notification::send($this->group->editors, new UserProfileRenewalAdminNotification($data));

                $this->dispatch('hide-modal',
                    id: 'userRenewalModal',
                    message: __('group.user.saved').' ('.$user->name.')',
                    savedMessage: __('app.saved')
                );
            } else {
                $error = true;
            }
        } else $error = true;

        if($error) {
            $this->dispatch('error',
                message: __('user.inactiveModal.error')
            );
        }
    }

    public function user_admin_groups() {
        $user_admin_groups = User::find(Auth::id())
        ->userGroupsDeletable()
        ->whereNull('groups.parent_group_id')
        ->get(['groups.id', 'groups.name', 'groups.parent_group_id']);
        return $user_admin_groups;
    }

    /**
     * The counterpart of user_admin_groups(): is the user a group admin in the given
     * group WHERE that group is itself a child group.
     *
     * This is what separates the two error causes in linkToGroup(): "you aren't a
     * group admin in it" versus "the group is already linked elsewhere". On its own,
     * user_admin_groups() can't distinguish the two, because it already
     * filters out child groups in the query.
     */
    private function isAdminOfChildGroup($groupId) {
        if(!$groupId) return false;

        return User::find(Auth::id())
            ->userGroupsDeletable()
            ->whereNotNull('groups.parent_group_id')
            ->where('groups.id', $groupId)
            ->exists();
    }

    public function linkToGroup() {
        $group = Group::findorFail($this->groupId);
        if(! $this->isAcceptedAdminOf($group->id)) {
            abort(403);
        }
        $this->resetErrorBag();
        $this->resetValidation();
        $this->setCopyDataList();
        
        //don't choose any group...
        if($this->new_parent_group_id == 0) {
            $this->addError('parent_group_id', __('group.link.error_no_selection'));
        }

        //this is the same group...
        if($this->new_parent_group_id == $this->groupId) {
            $this->addError('parent_group_id', __('group.link.error_same_group'));
        }

        // There used to be TWO checks here in a row, and both measured the
        // same thing: user_admin_groups() already queries with
        // whereNull('parent_group_id'), so the returned collection can't contain a
        // child group anyway. The second condition (->whereNull('parent_group_id') on
        // the same collection) therefore always held together with the first, and the
        // user got two error messages at once - including the
        // misleading "you aren't a group admin in it", even though they might well
        // be, and it's just that the group is already linked under a different one.
        //
        // Now only a single reason appears, and it's the real one.
        $groups = $this->user_admin_groups();
        if($groups->where('id', $this->new_parent_group_id)->count() == 0) {
            if($this->isAdminOfChildGroup($this->new_parent_group_id)) {
                $this->addError('parent_group_id', __('group.link.error_this_is_child'));
            } else {
                $this->addError('parent_group_id', __('group.link.error_not_in_group'));
            }
        } 

        if($group->childGroups()->count() > 0) {
            $this->addError('parent_group_id', __('group.link.error_this_is_parent'));
        }

        $errors = count($this->getErrorBag()->all());
        if($errors === 0) {
            $copy_from_parent = [];
            foreach($this->copy_datas as $field => $tr) {
                if(isset($this->copy_fields[$field])) {
                    $copy_from_parent[$field] = $this->copy_fields[$field];
                }
            }
            $up = $group->update([
                'parent_group_id' => $this->new_parent_group_id,
                'copy_from_parent' => $copy_from_parent
            ]);
            if($up) {
                $new_group = Group::findorFail($this->new_parent_group_id);
                $group->signs = $new_group->signs;
                $group->save();
                //send notifications to admins
                $admins = $group->groupAdmins;
                $data = [
                    'groupName' => $new_group->name,
                    'childGroupName' => $group->name,
                    'userName' => auth()->user()->name
                ];
                Notification::send($admins, new GroupParentGroupAttachedNotification($data));
                
                $current_users = $group->groupUsers()->get()->toArray();
                $current_list = [];
                foreach($current_users as $user) {
                    if($user['pivot']['accepted_at'] !== null && $user['pivot']['deleted_at'] === null) {
                        $current_list[$user['id']] = true;
                    }
                }

                $new_users = $new_group->groupUsers()->get()->toArray();
                $user_sync = [];
                foreach($new_users as $new_user) {
                    $user_sync[$new_user['id']] = [
                        'note' => strip_tags(trim($new_user['pivot']['note'] ?? '')),
                        'hidden' => $new_user['pivot']['hidden'] == 1 ? 1 : 0,
                        'deleted_at' => null, //because maybe we try to reattach logged out user
                        //automatically accept invitation if user is already member of the parent group
                        // 'accepted_at' => $new_user['pivot']['accepted_at'] ? date("Y-m-d H:i:s") : null
                    ];
                    if(!isset($current_list[$new_user['id']])) {
                        $user_sync[$new_user['id']]['accepted_at'] = $new_user['pivot']['accepted_at'] ? date("Y-m-d H:i:s") : null;
                    }

                    if($copy_from_parent['signs'] == true) {
                        $user_sync[$new_user['id']]['signs'] = $new_user['pivot']['signs'];
                    }
                }
                $res = $group->groupUsersAll()->sync($user_sync);
                $data = [
                    'groupAdmin' => auth()->user()->name, 
                    'groupName' => $group->name
                ];
                // dd($res, $current_users, $current_list);
                //new users notification
                if(isset($res['attached'])) {
                    $attached_users = User::whereIn('id', $res['attached'])
                        ->whereNotNull('email_verified_at')
                        ->get();

                    Notification::send($attached_users, new GroupUserAddedNotification($data));          
                }
                if(isset($res['updated'])) {
                    $updated_users = User::whereIn('id', $res['updated'])
                        ->whereNotNull('email_verified_at')
                        ->get();
                    Notification::send($updated_users, new GroupUserAddedNotification($data));            
                }
                if(isset($res['detached'])) {
                    foreach($res['detached'] as $user) {
                        $m = new GroupUserMoves($group->id, $user);
                        $m->detach();
                    }                
                }
                $this->dispatch('hide-modal',
                    id: 'LinkToModal',
                    message: __('group.link.success'),
                    savedMessage: __('app.saved')
                );
            } else {
                $this->dispatch('error',
                    message: __('group.link.error')
                );
            }            
        }
    }

    public function confirmChildDetach($child_group_id) {
        $group = Group::findorFail($this->groupId);
        if(! $this->isAcceptedAdminOf($group->id)) {
            abort(403);
        }

        $selected_group = $group->childGroups()->where('id', $child_group_id)->first();
        if($selected_group === null) abort(403);

        $this->detachId = $selected_group->id;

        $this->dispatch('show-deletion-confirmation',
            title: __('group.link.parent.detach.question', ['groupName' => $selected_group->name]),
            text: __('group.link.parent.detach.message'),
            emit: 'detachChildGroup'
        );
    }

    public function detachChildGroup() {
        $group = Group::findorFail($this->groupId);
        if(! $this->isAcceptedAdminOf($group->id)) {
            abort(403);
        }

        // ->first() can also be null (unknown or already-detached detachId).
        // The previous `!$selected_group->id` was calling a property on NULL in that
        // case, so a fatal error came instead of a 403 - the same class of bug that
        // TODO 11.1 fixed for detachParentGroup().
        $selected_group = $group->childGroups()->where('id', $this->detachId)->first();
        if($selected_group === null) abort(403);

        // The notification data is assembled BEFORE the update(), because afterward the
        // relationship no longer exists.
        $data = [
            'groupName' => $group->name,
            'childGroupName' => $selected_group->name,
            'userName' => auth()->user()->name
        ];

        // We save through the model, not with a bulk update(). The previous
        // ->childGroups()->where(...)->update() bypassed the Eloquent
        // events, so the GroupObserver audit entry was missing - whereas the SAME
        // relationship, detached from the parent side, was logged.
        $selected_group->update([
            'parent_group_id' => null,
            'copy_from_parent' => null
        ]);

        $this->detachId = null;

        // detachParentGroup() notifies the child group's administrators when
        // breaking this same relationship from the other side. NOTHING was going out
        // from here until now, so the affected parties got notified or not
        // depending on which screen someone used to make the change.
        Notification::send(
            $selected_group->groupAdmins,
            new GroupParentGroupDetachedNotification($data)
        );

        $this->dispatch('hide-modal',
            id: 'ChildGroupsModal',
            message: __('group.link.parent.detach.success'),
            savedMessage: __('app.saved')
        );
    }

    public function confirmParentDetach($parent_group_id) {
        $group = Group::findorFail($this->groupId);
        if(! $this->isAcceptedAdminOf($group->id)) {
            abort(403);
        }
        if($group->parent_group_id != $parent_group_id)
            abort(403);

        $this->detachId = $group->parent_group_id;

        $this->dispatch('show-deletion-confirmation',
            title: __('group.link.child.detach.question'),
            text: __('group.link.child.detach.message'),
            emit: 'detachParentGroup'
        );
    }

    public function detachParentGroup() {
        $group = Group::findorFail($this->groupId);

        if(! $this->isAcceptedAdminOf($group->id)) {
            abort(403);
        }

        if($group->parent_group_id != $this->detachId)
            abort(403);

        // The notification data is built AFTER the authorization guards: the
        // auth()->user()->name call used to sit further up here, so a fatal error came
        // instead of a 403 when there was no logged-in user.
        //
        // But it still has to be assembled BEFORE the update(): once parent_group_id
        // is nulled out, the parentGroup relation would no longer return the
        // abandoned parent group's name.
        $parent_group = $group->parentGroup;
        $parent_group_name = ($parent_group !== null) ? $parent_group->name : null;
        $data = [
            'groupName' => $parent_group_name,
            'childGroupName' => $group->name,
            'userName' => auth()->user()->name
        ];

        $res = $group->update([
            'parent_group_id' => null,
            'copy_from_parent' => null
        ]);
        if($res) {
            $this->detachId = null;

            //send notifications to admins
            $admins = $group->groupAdmins;
            
            Notification::send($admins, new GroupParentGroupDetachedNotification($data));

            $this->dispatch('hide-modal',
                id: 'ParentGroupModal',
                message: __('group.link.child.detach.success'),
                savedMessage: __('app.saved')
            );
        } else {
            $this->dispatch('error',
                message: __('group.link.child.detach.error')
            );
        }
    }

    /**
     * Toggle user signs
     */
    public function toogleSign($user_id, $icon) {
        $this->getGroupInfo();
        if(Auth::id() !== $user_id && $this->isNotHelper()) {
            $this->dispatch('error',
                message: __('group.signs.error')
            );
            return false;
        }
        if(Auth::id() == $user_id && $this->isNotHelper()) {
            $group_signs = $this->group->signs;
            $change_self = (($group_signs[$icon]['change_self'] ?? null) == true);
            if(!$change_self) {
                $this->dispatch('error',
                    message: __('group.signs.error')
                );
                return false;
            }
        }
        $userData = GroupUser::where('group_id', $this->groupId)
                    ->where('user_id', $user_id);

        $signs = $userData->first('signs')->toArray();
        // dd($signs);
        $signs = $signs['signs'];
        if(isset($signs[$icon])) {
            $signs[$icon] = !$signs[$icon];
        } else {
            $signs[$icon] = true;
        }
        $userData->update(['signs' => $signs]);

        //copy to childs if accept it
        $childs = Group::where('parent_group_id', '=', $this->groupId)
                        ->get(['id', 'copy_from_parent']);
        if(count($childs)) {
            $copy_to = [];
            foreach($childs as $child) {
                if(isset($child->copy_from_parent['signs'])) {
                    if($child->copy_from_parent['signs'])
                        $copy_to[] = $child->id;
                }
            }
            if(count($copy_to)) {
                GroupUser::whereIn('group_id', $copy_to)
                    ->where('user_id', $user_id)
                    ->update(['signs' => $signs]);
            }
        }

        $this->dispatch('success',
            message: __('group.signs.success')
        );
    }

    private function getGroupInfo() {
        $this->group = Group::findOrFail($this->groupId);
        $this->getRole();
        $this->setCopyDataList();
        $copy_from_parent = $this->group->copy_from_parent;
        foreach($this->copy_datas as $field => $tr) {
            if(isset($copy_from_parent[$field])) {
                $this->copy_fields[$field] = $copy_from_parent[$field];
            } else {
                $this->copy_fields[$field] = false;
            }
        }
    }

    private function isNotEditor() {
        return !in_array($this->role, ['admin', 'roler']);
    }

    private function isNotHelper() {
        return !in_array($this->role, ['admin', 'roler']);
    }

    /**
     * Whether a member's own profile (name, phone, congregation) is this
     * group's to see and edit: once they are in the group, or while their
     * account is the unverified one the group's invitation created. A
     * pending invitation to an existing account is just an e-mail address.
     */
    private static function profileIsTheGroups(User $user): bool {
        return $user->pivot->accepted_at !== null || $user->email_verified_at === null;
    }

    /**
     * The rule updateUser() already applied to demotion: only an admin may
     * act on an admin.
     */
    private function mayNotRemove(User $user): bool {
        return $user->pivot->group_role === 'admin' && $this->role !== 'admin';
    }

    private function isAcceptedAdminOf($groupId): bool {
        return $this->acceptedGroupRole($groupId) === 'admin';
    }

    private function getRole() {
        // An ACCEPTED membership's role (TODO 70).
        $role = $this->acceptedGroupRole($this->groupId);
        $info = $role === null ? null : (object) ['group_role' => $role];

        // Without a membership row, ->toArray() used to fail on null, meaning
        // a 500 error kept the component locked. The route has the
        // groupMember middleware, but the Livewire methods that call getGroupInfo() are
        // also reachable in their own right, so this needs to be locked down here too.
        //
        // Leaving role as null isn't enough: render() doesn't check
        // authorization, it only computes $editor, so an outsider would
        // have the member list rendered for them as a non-editor.
        if($info === null) {
            abort(403);
        }

        $this->role = $info->group_role;
    }

    private function maxRoles() {
        $roles = self::$group_roles;
        $available = [];
        foreach($roles as $role) {
            $available[] = $role;
            if($role == $this->role) break;
        }
        return $available;
    }

    private function setCopyDataList() {
        $this->copy_datas = [
            'signs' => __('group.signs.title')
        ];
    }

    public function setCopyInfo() {
        $group = Group::findOrFail($this->groupId);
        if(! $this->isAcceptedAdminOf($group->id)) {
            abort(403);
        }
        $this->setCopyDataList();

        $copy_from_parent = [];
        foreach($this->copy_datas as $field => $tr) {
            if(isset($this->copy_fields[$field])) {
                $copy_from_parent[$field] = $this->copy_fields[$field];
            }
        }
        $group->copy_from_parent = $copy_from_parent;
        

        $parent = Group::where('id', '=', $group->parent_group_id)
                    ->first();
        if($copy_from_parent['signs'] == true) {
            $group->signs = $parent->signs;

            $parent_users = $parent->groupUsersAll;
            $p_users = [];
            foreach($parent_users as $user) {
                $p_users[$user->id] = $user->pivot->signs;
            }

            $group_users = GroupUser::where('group_id', '=', $this->groupId)
                        ->get();
            foreach($group_users as $user) {
                if(!isset( $p_users[$user->user_id])) {
                    continue;
                }
                $user->signs = $p_users[$user->user_id];
                $user->save();
            }
        }

        $group->save();

        $this->dispatch('hide-modal',
            id: 'ParentGroupModal',
            message: __('app.saved'),
            savedMessage: __('app.saved')
        );
    }

    public function render(Request $request)
    {
        $this->getGroupInfo();
        $parent_group = $this->group->parentGroup;
        $parent_group_name = ($parent_group !== null) ? $parent_group->name : null;
        $editor = !$this->isNotHelper(); 

        $users = $this->group->groupUsers()
                    ->where(function($query) {
                        if(($this->filter['online'] ?? null) == true) {
                            $query->whereBetween('users.last_activity', [now()->subMinute(5), now()]);
                        }
                        if(($this->filter['inactive'] ?? null) == true) {
                            $query->where('users.last_activity', '<=', now()->subMonths(config('gdpr.settings.ttl') ?? 6)->addDays(14));
                        }
                    })
                    ->where(function($query) use ($editor) {
                        if(!$editor) {
                            $query->whereNotNull('group_user.accepted_at');
                        }
                        if(($this->filter['myself'] ?? null) == true) {
                            $query->where('group_user.user_id', '=', auth()->user()->id);
                        }
                        if(count($this->filter['signs'] ?? []) > 0) {
                            foreach($this->filter['signs'] as $icon => $value) {
                                if(!$value) continue;
                                $field = 'signs->'.$icon;
                                $query->whereJsonContains($field, $value);
                            }
                        }
                    })->get(); 
        // What a member who is not an editor does not see is not counted or
        // searched either (TODO 70): hidden members used to be dropped only
        // by the view, so the total and the search revealed them, and the
        // search matched e-mail addresses their owners keep private.
        if(!$editor) {
            $users = collect($users)->reject(function($user) {
                return $user->pivot->hidden == 1 && $user->id !== Auth::id();
            });
        }
        if(strlen($this->searchTerm ?? '') > 0) {
            //search in user's name and email. This is because we store user's name encrypted
            $users = collect($users)->filter(function($user) use ($editor) {
                $emailVisible = $editor || ($user->show_fields['email'] ?? false) == true;
                if(
                      mb_strpos(mb_strtolower($user->name), mb_strtolower($this->searchTerm)) !== false
                   || ($emailVisible && mb_strpos(mb_strtolower($user->email), mb_strtolower($this->searchTerm)) !== false)
                ) return true;
                return false;
            });
        }
        //create pagination
        $total = count($users);
        $per_page = 10;
        // The cursor lives in $paginators under Livewire 3; the client can write it,
        // so it is clamped to a positive integer (TODO 48).
        $current_page = max(1, (int) $this->getPage());
        $itemsForCurrentPage = $users->slice(($current_page - 1) * $per_page, $per_page);
        $users = new LengthAwarePaginator($itemsForCurrentPage, $total, $per_page, $current_page, [
                'path' => $request->url(),
                'query' => $request->query(),
        ]);

        return view('livewire.groups.list-users', [
            'editor' => $editor,
            'admin' => $this->isAcceptedAdminOf($this->group->id) ? 1 : 0,
            'users' => $users,
            'group_name' => $this->group->name,
            'group_id' => $this->group->id,
            'current_parent_group_id' => $this->group->parent_group_id,
            'parent_group_name' => $parent_group_name,
            'child_groups' => $this->group->childGroups()->get(['id', 'name'])->toArray(),
            'group_roles' => self::$group_roles, //available because higher roles are available for users, check on save
            'group_signs' => $this->group->signs,
            'user_admin_groups' => $this->user_admin_groups(),
            'user_role' => $this->role,
            'copy_datas' => $this->copy_datas,
            'messages' => [
                'on' => $this->group->messages_on,
                'priority' => $this->group->messages_priority,
                'write' => $this->group->messages_write,
            ] 
        ]);
    }
}
