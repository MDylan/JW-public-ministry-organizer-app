<?php

namespace App\Http\Livewire\Admin;

use App\Classes\setEnvironment;
use App\Http\Livewire\AppComponent;
use App\Models\Group;
use App\Models\Settings as ModelsSettings;
use App\Models\User;
use App\Support\Translation\LangFiles;
use App\Notifications\TestNotification;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Validator;
use Exception;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;

class Settings extends AppComponent
{

    public $settings;
    public $lang_beeing_deleted = "";

    public $listeners = ['languageRemoveConfirmed'];

    //These will be only checkboxes (true/false)
    public $others = [
        'registration'  => true,
        'terms_checkbox' => true,
        'claim_group_creator' => true,
        'debugbar'  => false,
        'maintenance' => false,
        'gdpr' => false,
        'use_https' => false,
        'use_recaptcha' => false,
        'show_homepage_alert' => false,
        'weather' => false,
        /*
        !!! Important! 
        If you add new element here, you must set into /app/Providers/AppServiceProvider.php file too, 
        in defaults array !!!
        */
    ];

    private $from_env = [
        'APP_NAME',
        'APP_URL',
        'TIMEZONE',
        'MAIL_MAILER',
        'MAIL_HOST',
        'MAIL_PORT',
        'MAIL_ENCRYPTION',
        'MAIL_USERNAME',
        'MAIL_PASSWORD',
        'MAIL_FROM_ADDRESS',
        // A kulcs neve KORÁBBAN OPENWAETHER_API_KEY volt - elírás, ami
        // következetesen szerepelt öt helyen, ezért működött. A
        // config/openweather.php egy kiadás erejéig a régi nevet is olvassa
        // fallbackként, mert a telepített hostok .env fájlja még azt hordozza;
        // ez a lista viszont már az új nevet írja ki mentéskor (v1-patch C).
        'OPENWEATHER_API_KEY'
    ];

    public $mailtest = null;

    public function mount() {
        $this->load();

        if(!config('app.debug')) {
            unset($this->others['debugbar']);
        }

        foreach($this->others as $key => $value) {
            if(!isset($this->settings[$key])) {
                $this->state['others'][$key] = $value;
            } else {
                $this->state['others'][$key] = $this->settings[$key];
            }
        }

        // Ez az űrlap magát a .env FÁJLT szerkeszti, tehát a fájlból kell
        // olvasnia. Korábban env()-ből olvasott, ami gyorsítótárazott
        // konfiguráció mellett null - a szerkesztő ilyenkor csupa ÜRES mezőt
        // mutatott, és a saveOthers() minden from_env kulcsot feltétel nélkül
        // visszaír, tehát egyetlen mentés kitörölte volna az APP_NAME, APP_URL
        // és az összes MAIL_* beállítást a .env-ből. Lásd
        // App\Classes\setEnvironment::value().
        $this->state['recaptcha']['site_key'] = setEnvironment::value('RECAPTCHA_SITE_KEY', '');
        $this->state['recaptcha']['secret_key'] = setEnvironment::value('RECAPTCHA_SECRET_KEY', '');
        $this->state['homepage_message'] = $this->settings['homepage_message'] ?? '';
        foreach($this->from_env as $key) {
            $this->state['env'][$key] = setEnvironment::value($key, '');
        }

        // A csoportadatok megőrzési ideje szándékosan KÍVÜL van a $others
        // tömbön és a state['others'] ágon is. A $others elemeit a nézet
        // kapcsoló-ciklusa rendereli (bootstrap-switch), ez viszont
        // háromértékű választó; a state['others'] pedig a saveOthers()
        // hatókörébe esne - lásd a saveGroupDataRetention() magyarázatát.
        $this->state['retention']['group_data'] = $this->settings['group_data_retention'] ?? '0';
    }

    private function getLanguages() {
        return isset($this->settings['languages']) ? json_decode($this->settings['languages'], true) : [];
    }

    public function languageAdd() {
        $validatedData = Validator::make($this->state['languageAdd'], [
            'country_code' => 'required|alpha|min:2|max:6',
            'country_name' => 'required|string|max:50|min:2',
        ])->validate();

        $languages = $this->getLanguages();
        $languages[$validatedData['country_code']] = [
            'name' => $validatedData['country_name'],
            'visible' => true
        ];

        $languages = \json_encode($languages);

        ModelsSettings::updateOrCreate(
            ['name' => 'languages'],
            ['value' => $languages]
        );

        // TODO 33.3: the other half of what used to be a split brain. This
        // method registered the locale and never created its directory, while
        // the removed package's UI created the directory and never registered
        // the locale - so a language added here had no files, and one added
        // there never reached the language switcher. Registering is now the one
        // place both happen.
        //
        // ensureLocale() never touches a directory that already exists, which
        // is what lets a locale whose files predate the registry be adopted
        // with its translations intact.
        app(LangFiles::class)->ensureLocale($validatedData['country_code']);

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
        $new = $this->state['default_language'];
        if(isset($languages[$new])) {
            ModelsSettings::updateOrCreate(
                ['name' => 'default_language'],
                ['value' => $new]
            ); 
            $setEnv = [];
            $setEnv['APP_LANG'] = '"'.$new.'"';
            setEnvironment::setEnvironmentValue($setEnv);

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

    /**
     * A csoportadatok (day_stats, group_dates) megőrzési ideje.
     *
     * Saját mentője van, nem a saveOthers()-é, három okból:
     *
     *  - a saveOthers() a settings sorok után az EGÉSZ .env fájlt újraírja
     *    (USE_HTTPS, GDPR_ENABLED, minden MAIL_*, APP_URL) és config:clear-t
     *    hív; egy megőrzési beállításnak nincs szüksége ekkora hatósugárra,
     *  - a setEnvironmentValue() abort(403)-mal elszállhat AZUTÁN, hogy a
     *    settings sorok már elmentek - részleges sikert hagyva maga után,
     *  - a saveOthers() a komponens egyetlen szándékosan teszteletlen
     *    metódusa (a .env.testing fájlt írná felül, lásd az AdminSettingsTest
     *    osztálydokját). Egy VÉGLEGES TÖRLÉST vezérlő beállítás nem
     *    maradhat lefedettség nélkül.
     *
     * A whitelist itt is kötelező, nem csak a RetentionWindow-ban: a settings
     * táblába érvénytelen érték se kerüljön be.
     */
    public function saveGroupDataRetention() {
        $value = (string) ($this->state['retention']['group_data'] ?? '0');

        if(!in_array($value, config('retention.group_data_options'), true)) {
            $this->state['retention']['group_data'] = $this->settings['group_data_retention'] ?? '0';
            $this->dispatchBrowserEvent('error', ['message' => __('settings.retention.invalid')]);
            return;
        }

        ModelsSettings::updateOrCreate(
            ['name' => 'group_data_retention'],
            ['value' => $value]
        );

        $this->dispatchBrowserEvent('success', ['message' => __('settings.retention.saved')]);
    }

    public function saveOthers() {
        // dd($this->state);
        if(isset($this->state['others'])) {
            foreach($this->state['others'] as $key => $value) {
                ModelsSettings::updateOrCreate(
                    ['name' => $key],
                    ['value' => $value]
                ); 
            }

            if(isset($this->state['homepage_message'])) {
                ModelsSettings::updateOrCreate(
                    ['name' => 'homepage_message'],
                    ['value' => $this->state['homepage_message']]
                ); 
            }

            $setEnv = [];

            if($this->state['others']['use_recaptcha']) {
                if(strlen(trim($this->state['recaptcha']['site_key'])) > 0
                    && strlen(trim($this->state['recaptcha']['secret_key'])) > 0
                ) {
                    $setEnv['USE_RECAPTCHA'] = "true";
                    $setEnv['RECAPTCHA_SITE_KEY'] = '"'.trim($this->state['recaptcha']['site_key']).'"';
                    $setEnv['RECAPTCHA_SECRET_KEY'] = '"'.trim($this->state['recaptcha']['secret_key']).'"';
                    
                } else {
                    $setEnv['USE_RECAPTCHA'] = "false";
                }
            } else {
                $setEnv['USE_RECAPTCHA'] = "false";
            }
            foreach($this->from_env as $key) {
                $setEnv[$key] = '"'.addslashes(trim($this->state['env'][$key])).'"';
            }
            //set https
            $setEnv['USE_HTTPS'] = ($this->state['others']['use_https']) ? "true" : "false";
            if($this->state['others']['use_https']) {
                $setEnv['APP_URL'] = str_replace("http://", "https://", $setEnv['APP_URL']);
            } else {
                $setEnv['APP_URL'] = str_replace("https://", "http://", $setEnv['APP_URL']);
            }
            $setEnv['GDPR_ENABLED'] = ($this->state['others']['gdpr']) ? "true" : "false";

            // A getenv() ugyanabba a csapdába esett, mint az env(): gyorsítótárazott
            // konfiguráció mellett a .env be sem töltődik, tehát hamisat ad, és a
            // CSS-gyorsítótár minden mentésnél fölöslegesen kiürült.
            if($setEnv['USE_HTTPS'] != setEnvironment::value('USE_HTTPS')) {
                //clear css cache
                foreach (glob(public_path()."/plugins/fontawesome-free/css/*-cache_fontawesome.css") as $filename) {
                    unlink($filename);
                }
            }
            
            if(count($setEnv)) {
                setEnvironment::setEnvironmentValue($setEnv);
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
    }

    public function run($command) {
        $commands = [
            'optimize' => [
                'command' => 'optimize:clear'
            ],
            'cache_clear' => [
                'command' => 'cache:clear'
            ],
            'config_clear' => [
                'command' => 'config:clear'
            ],
            'view_clear' => [
                'command' => 'view:clear',
            ],
            'migrate' => [
                'command' => 'migrate',
                'params' => ['--force'=> true]
            ],
            'retry' => [
                'command' => 'queue:retry all'
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
                return dd($e->getMessage());
            }
            $this->dispatchBrowserEvent('success', ['message' => __('settings.run.success')]);
        }
    }

    public function testMail() {
        $reload = [
            'MAIL_MAILER' => 'mail.default',
            'MAIL_HOST' => 'mail.mailers.smtp.host',
            'MAIL_PORT' => 'mail.mailers.smtp.port',
            'MAIL_ENCRYPTION' => 'mail.mailers.smtp.encryption',
            'MAIL_USERNAME' => 'mail.mailers.smtp.username',
            'MAIL_PASSWORD' => 'mail.mailers.smtp.password',
            'MAIL_FROM_ADDRESS' => 'mail.from.address', 
        ];
        $old_conf = [];
        
        foreach($reload as $variable => $config) {
            $old_conf[$config] = Config::get($config);
        }
        $conf = [];
        foreach($reload as $variable => $config) {
            $conf[$config] = $this->state['env'][$variable];
        }
        Config::set($conf);       
        try {
            Notification::route('mail', $this->state['env']['MAIL_FROM_ADDRESS'])->notify(new TestNotification());
            $this->mailtest = true;
        } catch(Exception $e) {            
            $this->mailtest = $e->getMessage();
        }
        Config::set($old_conf);
    }

    public function render()
    {
        
        $this->load();

        $failed_jobs = DB::table('failed_jobs')->get()->count();

        $this->state['default_language'] = $this->settings['default_language'];


        return view('livewire.admin.settings', [
            'waiting_jobs' => Queue::size(),
            'failed_jobs' => $failed_jobs,
            'stat_users' => User::all()->count(),
            'stat_online' => User::whereBetween('last_activity', [now()->subMinute(5), now()])->get()->count(),
            'stat_groups' => Group::all()->count()
        ]);
    }
}
