<?php

namespace App\Http\Livewire\Admin;

use App\Http\Livewire\AppComponent;
use App\Models\Settings as ModelsSettings;
use Illuminate\Support\Facades\Validator;

class Settings extends AppComponent
{

    public $settings;
    public $lang_beeing_deleted = "";

    public $listeners = ['languageRemoveConfirmed'];

    public function mount() {

    }

    private function getLanguages() {
        return isset($this->settings['languages']) ? json_decode($this->settings['languages'], true) : [];
    }

    public function languageAdd() {
        // dd('here', $this->state);
        $validatedData = Validator::make($this->state['languageAdd'], [
            'country_code' => 'required|alpha|min:2|max:6',
            'country_name' => 'required|string|max:50|min:2',
        ])->validate();

        $languages = $this->getLanguages();
        // dd($languages);
        $languages[$validatedData['country_code']] = $validatedData['country_name'];

        $languages = \json_encode($languages);

        ModelsSettings::updateOrCreate(
            ['name' => 'languages'],
            ['value' => $languages]
        );        
        
        unset($this->state['languageAdd']);

        $this->dispatchBrowserEvent('success', ['message' => __('settings.languages.success')]);
        $this->emitTo('partials.nav-bar', 'refresh');
    }

    public function languageRemoveConfirmation($code) {
        $languages = $this->getLanguages();
        if(isset($languages[$code])) {
            $this->lang_beeing_deleted = $code;
            $this->dispatchBrowserEvent('show-languageRemove-confirmation', ['lang' => $languages[$code]]);
        }        
    }

    public function languageRemoveConfirmed() {
        $languages = $this->getLanguages(); 
        if(isset($languages[$this->lang_beeing_deleted])) {
            unset($languages[$this->lang_beeing_deleted]);

            $languages = \json_encode($languages);
            ModelsSettings::updateOrCreate(
                ['name' => 'languages'],
                ['value' => $languages]
            );  
            $this->dispatchBrowserEvent('success', ['message' => __('settings.languages.confirmDelete.success')]);
        }
        $this->lang_beeing_deleted = "";
        $this->emitTo('partials.nav-bar', 'refresh');
    }

    public function languageSetDefault() {
        $languages = $this->getLanguages(); 
        // dd($this->state);
        $new = $this->state['default_language'];
        if(isset($languages[$new])) {
            ModelsSettings::updateOrCreate(
                ['name' => 'default_language'],
                ['value' => $new]
            ); 
            $this->dispatchBrowserEvent('success', ['message' => __('settings.languages.defaultSet.success')]);
        } else {
            $this->dispatchBrowserEvent('error', ['message' => __('settings.languages.defaultSet.error')]);
        }
    }

    public function render()
    {
        $settings = ModelsSettings::all()->toArray();
        foreach($settings as $setting) {
            $this->settings[$setting['name']] = $setting['value'];
        }
        if(!isset($this->settings['default_language'])) {
            $this->settings['default_language'] = config('app.locale');
        }
        $this->state['default_language'] = $this->settings['default_language'];

        return view('livewire.admin.settings');
    }
}
