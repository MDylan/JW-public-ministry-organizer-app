<?php

namespace App\Http\Controllers\Setup;

use App\Classes\setEnvironment;
use App\Http\Controllers\Controller;
use App\Http\Requests\SetupMailRequest;
use App\Models\Settings;
use App\Notifications\TestNotification;
use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Session;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;

class MailController extends Controller
{
    protected array $mailConfig;

    /**
     * Display the form for configuration of the mail.
     *
     * @return View
     */
    public function index(): View
    {
        return view('setup.mail');
    }

    /**
     * Handle the test and configuration of a new mail connection.
     *
     * @param SetupMailRequest $request
     * @return RedirectResponse
     */
    public function configure(SetupMailRequest $request): RedirectResponse
    {
        $configs = $request->all();
        $reload = [
            'MAIL_MAILER' => 'mail.default',
            'MAIL_HOST' => 'mail.mailers.smtp.host',
            'MAIL_PORT' => 'mail.mailers.smtp.port',
            'MAIL_ENCRYPTION' => 'mail.mailers.smtp.encryption',
            'MAIL_USERNAME' => 'mail.mailers.smtp.username',
            'MAIL_PASSWORD' => 'mail.mailers.smtp.password',
            'MAIL_FROM_ADDRESS' => 'mail.from.address', 
        ];

        $conf = $save_config = [];
        foreach($reload as $variable => $config) {
            $conf[$config] = $configs[$variable];
            $save_config[$variable] = '"'.addslashes(trim($configs[$variable])).'"';
        }
        Config::set($conf);       
        try {
            Notification::route('mail', $configs['MAIL_FROM_ADDRESS'])->notify(new TestNotification());
            $this->mailConfig = $configs;
        } catch(Exception $e) {            
            $alert = trans('settings.mail_test_error') . ' ' . $e->getMessage();
            Session::flash('error_message', $alert);
            return redirect()->back()->withInput();
        }
        
        setEnvironment::setEnvironmentValue($save_config);

        $lang = env('APP_LANG', 'en');
        $insert_languages[$lang] = [
            'name' => '',
            'visible' => true
        ];

        $insert_languages = \json_encode($insert_languages);

        Settings::updateOrCreate(
            ['name' => 'languages'],
            ['value' => $insert_languages]
        );
        Settings::updateOrCreate(
            ['name' => 'default_language'],
            ['value' => $lang]
        ); 

        return redirect()->route('setup.account');
    }
}
