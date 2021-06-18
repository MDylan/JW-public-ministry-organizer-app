<?php

namespace App\Http\Livewire\Groups;

use App\Models\Group;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class NewsList extends Component
{

    private $group;

    public function mount(Group $group) {
        $this->group = $group;
    }

    public function render()
    {        
        
        $this->group->news_log()->updateOrcreate(
            ['user_id' => Auth::id()],
            ['user_id' => Auth::id(), 'updated_at' => now()]
        );

        return view('livewire.groups.news-list', [
            'editor' => $this->group->editors()->wherePivot('user_id', Auth::id())->count(),
            'news' => $this->group->news()->with('files')->get(),
            'group' => $this->group
        ]);
    }
}
