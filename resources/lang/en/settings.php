<?php

return array (
  'app_name' => 'Site name (This will appear as the sender of the mail.)',
  'app_url' => 'Web address of the site (URL)',
  'failed_jobs_retry' => 'Run failed tasks again',
  'languages' => 
  array (
    'confirmDelete' => 
    array (
      'message' => 'After this action the language will not be available to anyone.',
      'question' => 'Are you sure you want to delete this language?',
      'success' => 'Language successfully deleted',
    ),
    'country_code' => 'Country code',
    'country_name' => 'Language (This will appear in the drop-down list.)',
    'default' => 'Default language',
    'defaultSet' => 
    array (
      'error' => 'This one is not included in the list of available languages!',
      'success' => 'New default language enabled',
    ),
    'empty' => 'You have not added any languages yet.',
    'lang_help' => 'The language drop-down list will appear if there are at least 2 optional languages.<br/> To modify an existing language, fill in the country code again and create the text.  You can assign "Translator" role to those whom you want to involve in translation. <hr> Before you make a new language available, make sure that the language files are available in the "/resources/lang" folder. <br/> If a text has not been translated in the selected language, the page will function properly, but no comprehendible text will appear in the place of the given content.<br/> For more information see the <a class="alert-link" href="https://laravel.com/docs/8.x/localization" target="_blank">laravel documentation</a>.',
    'start_translation' => 'Start translation',
    'success' => 'The language has been added.',
    'title' => 'Available languages',
    'translate' => 'Translation',
    'translator_help' => 'The administrator is permitted to add new language and can set the visibility of the language. Notify him when you have finished the translation.',
    'visibility' => 
    array (
      'admin' => 'It can be seen only by the admin',
      'show' => 'It can be seen by everybody',
    ),
    'visibility_changed' => 'The visibility of the language has been changed.',
  ),
  'mail' => 'Send mail',
  'mail_encryption' => 'Encryption type used for mailing',
  'mail_from_address' => 'Email address of the sender',
  'mail_host' => 'Mail server address',
  'mail_info' => 'The site sends out countless notifications to the user, so proper setting of the mailing system is crucial. You can test the settings without saving by clicking on the "Mail test" button.',
  'mail_mailer' => 'Send mail through by',
  'mail_password' => 'Password used for mailing',
  'mail_port' => 'Port (For example: 25 / 465 / 587)',
  'mail_test' => 'Mail test',
  'mail_test_error' => 'Sending email has failed!',
  'mail_test_on_progress' => 'Please wait, sending mail is in progress...',
  'mail_test_success' => 'Email has been sent successfully!',
  'mail_username' => 'Username used for mailing',
  'main' => 
  array (
    'info' => 'Please consider that whatever modification you make here, it might have a significant impact on the operation of the whole site.',
    'title' => 'Site default settings',
  ),
  'no_encryption' => 'No encryption',
  'others' => 
  array (
    'claim_group_creator' => 'Group overseer authorization can be requested on the "Group" page.',
    'debugbar' => 'Enable debug bar (Enable it only for testing/troubleshooting! Otherwise it comes with safety risks!)',
    'gdpr' => 'Enable GDPR (Enable it only if you operate the website inside the EU.) Users will be able to download their data into a .json file.',
    'maintenance' => 'Maintenance mode (Only administrators will be able to log in.)',
    'registration' => 'Registration available. (If you disable it, only signed-in member - e.g. group overseer - can add new user.)',
    'terms_checkbox' => 'Terms of use for registration (If you disable it, no accepting of any terms of use will be required.)',
    'title' => 'Other Settings',
    'use_https' => 'Enable https encryption (Warning! You shall enable it only if https connection has been activated by your hosting service provider. If you enable it mistakenly, the site will not be able to load.)',
    'use_recaptcha' => 'Enable reCaptcha (anti-spam). By enabling it, you can considerably increase the protection of the page against robots. If enabled, the page will use Google recaptcha at login attempts and at registration attempts.',
  ),
  'others_saved' => 'New settings successfully saved.',
  'recaptcha' => 
  array (
    'info' => 'By enabling it, you can considerably increase the protection of the page against robots. If enabled, the page will use Google recaptcha at login attempts and at registration attempts.',
  ),
  'run' => 
  array (
    'optimize' => 'Run command',
    'success' => 'Command ran successfully',
    'title' => 'Run Artisan commands',
  ),
  'schedule_error' => 'ERROR! The scheduled task has not run for a long time (or has not yet run at all). This might cause malfuntioning.',
  'schedule_info' => 'The scheduled tasks ensure that the site functions properly (e.g. email notifications may be sent out, user data may be updated, etc.). Please make sure that it is set up at your hosting service provider that the run of the scheduled tasks happens once every minute (cron).
You can read more about it in
<a href="https://laravel.com/docs/8.x/scheduling#running-the-scheduler" target="_blank">documentation.</a>',
  'software_url' => 'Website of the software',
  'status' => 
  array (
    'failed_jobs' => 'Failed tasks',
    'last_schedule_run' => 'Last run of scheduled tasks',
    'php_version' => 'PHP version',
    'software_version' => 'Software version',
    'title' => 'System status',
    'waiting_jobs' => 'Waiting tasks',
  ),
  'there_are_failed_jobs' => 'An error occured during the run of one or more tasks. You have received a notification email about the error. Please try to run the task(s) again. If the error keeps occuring, please contact the software developer about it and send them the content of the notification email.',
  'timezone' => 'Time zone',
);
