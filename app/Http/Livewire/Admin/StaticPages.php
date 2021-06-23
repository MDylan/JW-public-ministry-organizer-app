<?php

namespace App\Http\Livewire\Admin;

use App\Models\StaticPage;
use Livewire\Component;

class StaticPages extends Component
{
    public function render()
    {

        $pages = StaticPage::all();

        return view('livewire.admin.static-pages', [
            'pages' => $pages
        ]);
    }
}
