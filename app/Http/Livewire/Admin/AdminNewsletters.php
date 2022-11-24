<?php

namespace App\Http\Livewire\Admin;

use App\Models\AdminNewsletter;
use App\Models\User;
use App\Notifications\Newsletter;
use Illuminate\Support\Facades\Auth;
use Livewire\Component;

class AdminNewsletters extends Component
{
    public function render()
    {

        $newsletters = AdminNewsletter::select("*");
        if(!Auth::user()->can('is-admin')) {
            $newsletters->where('status', '1');
            $newsletters->where('date', '<=', today());
        }

        $in = [];

        if(Auth::user()->can('is-groupCreator')) {
            //create group
            $in[] = 'groupCreators';
        } 
        if(Auth::user()->can('is-groupservant')) {
            $in[] = 'groupServants';            
        }
        if(Auth::user()->can('is-groupadmin')) {
            $in[] = 'groupAdmins';            
        }
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
