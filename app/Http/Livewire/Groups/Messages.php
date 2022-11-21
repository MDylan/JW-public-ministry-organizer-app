<?php

namespace App\Http\Livewire\Groups;

use App\Models\Group;
use App\Models\GroupMessage;
use App\Models\GroupUser;
use App\Models\User;
use App\Notifications\GroupPriorityMessageNotification;
use App\Rules\Throttle;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Laravolt\Avatar\Facade as Avatar;
use Livewire\Component;

class Messages extends Component
{

    public $group_id;
    public $message = '';
    public $message_priority = 0;
    public $group_write = 0;
    public $group_priority = 0;
    public $group_name = '';
    public $privilege = [
        'read' => false,
        'write' => false
    ];

    public $listeners = [
        'sendMessage',
        'deleteMessage'
    ];

    public function mount(Group $group) {
        $this->group_id = $group->id;
        $this->group_priority = $group->messages_priority;
        $this->group_write = $group->messages_write;
        $this->group_name = $group->name;
    }

    protected function rules()
    {
        return [
            'message' => [
                'required',
                'min:3',
                'max:250',
                new Throttle('group-message-'.$this->group_id, 3, 1),
            ],
            'message_priority' => 'required|in:0,1',
        ];
    }

    /**
     * Store the users messages
     */
    public function sendMessage() {

        $this->checkPrivilege();
        if(!$this->privilege['write']) {
            return back()->withErrors(['message' => __('group.messages.cant_write')])->withInput();
        }

        $validatedData = $this->validate();

        GroupMessage::create([
            'user_id' => auth()->id(),
            'group_id' => $this->group_id,
            'message' => $validatedData['message'],
            'priority' => $validatedData['message_priority']
        ]);

        if($validatedData['message_priority'] == 1 && $this->group_priority == 1) {
            $priority_users = DB::table('group_user')
                                    ->select('user_id')
                                    ->where('group_id', $this->group_id)
                                    ->where(function ($query) {
                                        $query->whereIn('group_role',['roler', 'admin'])
                                                ->orWhere('message_send_priority', 1);
                                    })
                                    ->whereNull('deleted_at')
                                    ->whereNotNull('accepted_at')
                                    ->pluck('user_id');
            $data = [
                'userName' => auth()->user()->name,
                'groupName' => $this->group_name,
                'message' => $validatedData['message']
            ];

            $users = User::whereIn('id', $priority_users)
                        ->get();

            Notification::send($users, new GroupPriorityMessageNotification($data));
        }

        $this->resetExcept('group_id', 'group_priority', 'group_write', 'group_name');
    }

    public function changePriority() {
        $this->message_priority = !$this->message_priority;
    }

    public function deleteMessage(GroupMessage $groupMessage) {
        $groupMessage->update([
            'message' => null
        ]);
    }

    public function checkPrivilege() {
        $userEvents = auth()->user()
            ->eventsOnly()
            ->where('start', '<', now()->addHours(24))
            ->where('end', '>=', now()->subHours(2))
            ->where('group_id', $this->group_id)
            ->whereIn('status', [0,1])
            // ->toSql();
            ->count();

        //if he has future events, he can read & write
        $this->privilege['read'] = $this->privilege['write'] = $userEvents > 0 ? true : false;

        //if group settings not enable write to anyone, set this disable
        if($this->group_write == 1) {
            $this->privilege['write'] = false;
        }

        $user = GroupUser::where('user_id', auth()->id())
                        ->where('group_id', $this->group_id)
                        ->whereNotNull('accepted_at')
                        ->whereNull('deleted_at')
                        ->first()->toArray();

        if($user['message_use'] == 1) {
            //he can't write any message
            $this->privilege['write'] = false;
        } elseif($user['message_use'] == 2) {
            //he can write even if he hasn't any event
            $this->privilege['read'] = $this->privilege['write'] = true;
        }

        //if he has high privilege, he can read and write
        if(auth()->user()->userGroupsEditable->contains('id', $this->group_id)) {
            $this->privilege['read'] = $this->privilege['write'] = true;
        }
    }

    public function render()
    {

        $messages = GroupMessage::where('group_id', $this->group_id)
                            ->with('user')
                            ->orderBy('created_at', 'DESC')
                            ->take(30)
                            ->get();
        $this->checkPrivilege();

        foreach($messages as $message) {
            $file = "avatar-".$message->user_id.".png";
            $path = 'avatars/';
            if(!Storage::disk('web')->exists($path.$file)) {
                $avatar = Avatar::create($message->user->name);
                $image = $avatar->getImageObject();
                Storage::disk('web')->put($path.$file, $image->stream("png"));
            }
        }

        return view('livewire.groups.messages', [
            'messages' => $messages
        ]);
    }
}
