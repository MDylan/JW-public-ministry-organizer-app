<?php

namespace App\Http\Livewire\Admin;

use App\Models\AdminNewsletter;
use App\Models\AdminNewsletterRead;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class AdminNewsletters extends Component
{

    public $listeners = [
        'setAsRead'
    ];

    public function setAsRead($newsletter_id) {
        AdminNewsletterRead::updateOrCreate([
            'user_id' => auth()->id(),
            'admin_newsletter_id' => $newsletter_id
        ],
        [
            'user_id' => auth()->id(),
            'admin_newsletter_id' => $newsletter_id
        ]);
        $this->emitTo('partials.nav-bar', 'refresh');
        $this->emitTo('partials.side-menu', 'refresh');
    }

    public function render()
    {

        $newsletters = AdminNewsletter::with('user_read');
        if(!Auth::user()->can('is-admin')) {
            $newsletters->where('status', '1');
            $newsletters->where('date', '<=', today());
        }

        $in = pwbs_get_newsletter_roles();
        if(count($in) > 0) 
            $newsletters->whereIn('send_to', $in);

        $newsletters->orderByDesc('date');
        $newsletters = $newsletters->get();

        return view('livewire.admin.admin-newsletters', [
            'editor' => auth()->user()->hasRole('mainAdmin'),
            'newsletters' => $newsletters
        ]);
    }
}
