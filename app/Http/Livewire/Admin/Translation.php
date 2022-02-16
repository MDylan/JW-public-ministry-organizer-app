<?php

namespace App\Http\Livewire\Admin;

use App\Http\Livewire\AppComponent;
use App\Models\Settings;

class Translation extends AppComponent
{
    public function render()
    {
        $settings = [];
        $settings = Settings::where('name', '=', 'languages')->pluck('value', 'name')->toArray();
        // dd($settings);
        return view('livewire.admin.translation', [
            'settings' => $settings
        ]);
    }
}
