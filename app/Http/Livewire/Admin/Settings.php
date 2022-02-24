<?php

namespace App\Http\Livewire\Admin;

use App\Http\Livewire\AppComponent;
use App\Models\Settings as ModelsSettings;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Exception;
use Codedge\Updater\UpdaterManager;

class Settings extends AppComponent
{

    public $settings;
    public $lang_beeing_deleted = "";

    public $listeners = ['languageRemoveConfirmed'];

    //These will be only checkboxes (true/false)
    public $others = [
        'registration'  => true,
        'claim_group_creator' => true,
        'debugbar'  => false,
        'maintenance' => false,
        'gdpr' => false,
        /*
        !!! Important! 
        If you add new element here, you must set into /app/Providers/AppServiceProvider.php file too, 
        in defaults array !!!
        */
    ];

    public function mount() {
        $this->load();

        foreach($this->others as $key => $value) {
            if(!isset($this->settings[$key])) {
                $this->state['others'][$key] = $value;
            } else {
                $this->state['others'][$key] = $this->settings[$key];
            }
        }
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
        $languages[$validatedData['country_code']] = [
            'name' => $validatedData['country_name'],
            'visible' => true
        ];

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

    public function languageVisibility($code) {
        $languages = $this->getLanguages();
        if(isset($languages[$code])) {
            $languages[$code] = [
                'name' => $languages[$code]['name'],
                'visible' => ($languages[$code]['visible'] ? false : true)
            ];

            $languages = \json_encode($languages);

            ModelsSettings::updateOrCreate(
                ['name' => 'languages'],
                ['value' => $languages]
            );        

            $this->dispatchBrowserEvent('success', ['message' => __('settings.languages.visibility_changed')]);
        } 
    }

    public function saveOthers() {
        // dd($this->state['others']);
        if(isset($this->state['others'])) {
            foreach($this->state['others'] as $key => $value) {
                ModelsSettings::updateOrCreate(
                    ['name' => $key],
                    ['value' => $value]
                ); 
            }
            $this->dispatchBrowserEvent('success', ['message' => __('settings.others_saved')]);
        }
    }

    public function load() {
        $settings = ModelsSettings::all()->toArray();
        foreach($settings as $setting) {
            $this->settings[$setting['name']] = $setting['value'];
        }
        if(!isset($this->settings['default_language'])) {
            $this->settings['default_language'] = config('app.locale');
        }
        // $update = $this->check_update();
        
    }

    private function check_update() {

        $updater = new UpdaterManager();

        // Check if new version is available
        if($updater->source()->isNewVersionAvailable()) {
            return true;
            // // Get the current installed version
            // echo $updater->source()->getVersionInstalled();
    
            // // Get the new version available
            // $versionAvailable = $updater->source()->getVersionAvailable();
    
            // // Create a release
            // $release = $updater->source()->fetch($versionAvailable);
    
            // // Run the update process
            // $updater->source()->update($release);
            
        } else {
            // echo "No new version available.";
            return false;
        }
    
    }

    public function run($command) {
        $commands = [
            'optimize' => [
                'command' => 'optimize:clear'
            ],
            'cache_clear' => [
                'command' => 'cache:clear'
            ],
            'view_clear' => [
                'command' => 'view:clear',
            ],
            'migrate' => [
                'command' => 'migrate',
                'params' => ['--force'=> true]
            ],
        ];
        if(isset($commands[$command])) {

            try {
                if(isset($commands[$command]['params'])) {
                    Artisan::call($commands[$command]['command'], $commands[$command]['params']);
                } else {
                    Artisan::call($commands[$command]['command']);
                }
            } catch (Exception $e) {
                return $this->response($e->getMessage(), 'error');
            }

            // Artisan::call($commands[$command]);
            $this->dispatchBrowserEvent('success', ['message' => __('settings.run.success')]);
        }
    }

    public function render()
    {
        
        $this->load();        

        $this->state['default_language'] = $this->settings['default_language'];

        return view('livewire.admin.settings');
    }
}
