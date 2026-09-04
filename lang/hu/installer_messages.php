<?php


return [

    /*
     *
     * Shared translations.
     *
     */
    'title' => 'Laravel Telepítő',
    'next' => 'Következő Lépés',
    'back' => 'Vissza',
    'finish' => 'Telepítés',
    'forms' => [
        'errorTitle' => 'Az alábbi hibákat találtam:',
    ],

    /*
     *
     * Home page translations.
     *
     */
    'welcome' => [
        'templateTitle' => 'Üdvözlünk',
        'title'   => 'Laravel Telepítő',
        'message' => 'Könnyű Telepítés és Beállítás.',
        'next'    => 'Követelmények Ellenőrzése',
    ],

    /*
     *
     * Requirements page translations.
     *
     */
    'requirements' => [
        'templateTitle' => '1. Lépés | Szerver Követelmények',
        'title' => 'Szerver Követelmények',
        'next'    => 'Jogosultságok Ellenőrzése',
    ],

    /*
     *
     * Permissions page translations.
     *
     */
    'permissions' => [
        'templateTitle' => '2. Lépés | Jogosultságok',
        'title' => 'Jogosultságok',
        'next' => 'Környezet Beállítása',
    ],

    /*
     *
     * Environment page translations.
     *
     */
    'environment' => [
        'menu' => [
            'templateTitle' => '3. Lépés | Környezet Beállításai',
            'title' => 'Környezet Beállításai',
            'desc' => 'Kérlek válaszd ki, hogyan szeretnéd az <code>.env</code> fájlt beállítani.',
            'wizard-button' => 'Varázsló Segítségével',
            'classic-button' => 'Klasszikus Szövegszerkesztővel',
        ],
        'wizard' => [
            'templateTitle' => '3. Lépés | Környezet Beállításai | Irányított Varázsló',
            'title' => 'Irányított <code>.env</code> Varázsló',
            'tabs' => [
                'environment' => 'Környezet',
                'database' => 'Adatbázis',
                'application' => 'Alkalmazás',
            ],
            'form' => [
                'name_required' => 'An environment name is required.',
                'app_name_label' => 'App Name',
                'app_name_placeholder' => 'App Name',
                'app_environment_label' => 'App Environment',
                'app_environment_label_local' => 'Local',
                'app_environment_label_developement' => 'Development',
                'app_environment_label_qa' => 'Qa',
                'app_environment_label_production' => 'Production',
                'app_environment_label_other' => 'Other',
                'app_environment_placeholder_other' => 'Enter your environment...',
                'app_debug_label' => 'App Debug',
                'app_debug_label_true' => 'True',
                'app_debug_label_false' => 'False',
                'app_log_level_label' => 'App Log Level',
                'app_log_level_label_debug' => 'debug',
                'app_log_level_label_info' => 'info',
                'app_log_level_label_notice' => 'notice',
                'app_log_level_label_warning' => 'warning',
                'app_log_level_label_error' => 'error',
                'app_log_level_label_critical' => 'critical',
                'app_log_level_label_alert' => 'alert',
                'app_log_level_label_emergency' => 'emergency',
                'app_url_label' => 'App Url',
                'app_url_placeholder' => 'App Url',
                'app_timezone' => 'Időzóna',
                'app_timezone_placeholder' => 'Időzóna helye',
                'app_timezone_info' => 'Támogatott időzónák',
                'db_connection_failed' => 'Could not connect to the database.',
                'db_connection_label' => 'Database Connection',
                'db_connection_label_mysql' => 'mysql',
                'db_connection_label_sqlite' => 'sqlite',
                'db_connection_label_pgsql' => 'pgsql',
                'db_connection_label_sqlsrv' => 'sqlsrv',
                'db_host_label' => 'Database Host',
                'db_host_placeholder' => 'Database Host',
                'db_port_label' => 'Database Port',
                'db_port_placeholder' => 'Database Port',
                'db_name_label' => 'Database Name',
                'db_name_placeholder' => 'Database Name',
                'db_username_label' => 'Database User Name',
                'db_username_placeholder' => 'Database User Name',
                'db_password_label' => 'Database Password',
                'db_password_placeholder' => 'Database Password',

                'app_tabs' => [
                    'more_info' => 'További információk',
                    'broadcasting_title' => 'Broadcasting, Caching, Session, &amp; Queue',
                    'broadcasting_label' => 'Broadcast Driver',
                    'broadcasting_placeholder' => 'Broadcast Driver',
                    'cache_label' => 'Cache Driver',
                    'cache_placeholder' => 'Cache Driver',
                    'session_label' => 'Session Driver',
                    'session_placeholder' => 'Session Driver',
                    'queue_label' => 'Queue Driver',
                    'queue_placeholder' => 'Queue Driver',
                    'redis_label' => 'Redis Driver',
                    'redis_host' => 'Redis Host',
                    'redis_password' => 'Redis Password',
                    'redis_port' => 'Redis Port',

                    'mail_label' => 'Mail',
                    'mail_driver_label' => 'Mail Driver',
                    'mail_driver_placeholder' => 'Mail Driver',
                    'mail_host_label' => 'Mail Host',
                    'mail_host_placeholder' => 'Mail Host',
                    'mail_port_label' => 'Mail Port',
                    'mail_port_placeholder' => 'Mail Port',
                    'mail_from_address' => 'Feladó email címe',
                    'mail_from_address_placeholder' => 'Erre a címre fognak tudni választ írni',
                    'mail_username_label' => 'Email fiók felhasználóneve',
                    'mail_username_placeholder' => 'A szolgáltatónál beállított felhasználónév',
                    'mail_password_label' => 'Email fiók jelszava',
                    'mail_password_placeholder' => 'Amit a szolgáltatótól kaptál',
                    'mail_encryption_label' => 'Email Titkosítás',
                    'mail_encryption_placeholder' => 'Például: ssl, tls',

                    'pusher_label' => 'Pusher',
                    'pusher_app_id_label' => 'Pusher App Id',
                    'pusher_app_id_palceholder' => 'Pusher App Id',
                    'pusher_app_key_label' => 'Pusher App Key',
                    'pusher_app_key_palceholder' => 'Pusher App Key',
                    'pusher_app_secret_label' => 'Pusher App Secret',
                    'pusher_app_secret_palceholder' => 'Pusher App Secret',

                    'admin_label' => 'Adminisztrátor',
                    'admin_email' => 'Adminisztrátor email címe',
                    'admin_email_placeholder' => 'Ezzel tudsz belépni telepítés után',
                    'admin_password' => 'Jelszó',
                    'admin_password_confirmation' => 'Jelszó megerősítés'
                ],
                'buttons' => [
                    'setup_database' => 'Adatbázis Beállítás',
                    'setup_application' => 'Alkalmazás Beállítás',
                    'install' => 'Telepítés',
                ],
            ],
        ],
        'classic' => [
            'templateTitle' => '3. Lépés | Környezeti beállítások | Klasszikus szerkesztő',
            'title' => 'Klasszikus Környezeti beállítások Szerkesztő',
            'save' => '.env Mentése',
            'back' => 'Használj Varázslót',
            'install' => 'Mentés és Telepítés',
        ],
        'success' => 'Az .env fájl mentésre került..',
        'errors' => 'Nem tudom menteni az .env fájlt, kérlek készítsd el manuálisan.',
    ],

    'install' => 'Telepítés',

    /*
     *
     * Installed Log translations.
     *
     */
    'installed' => [
        'success_log_message' => 'Laravel Telepítő sikeresen TELEPÜLT itt: ',
    ],

    /*
     *
     * Final page translations.
     *
     */
    'final' => [
        'title' => 'A Telepítés Befejeződött',
        'templateTitle' => 'Telepítés Befejezve',
        'finished' => 'Az alkalmazás sikersen települt.',
        'migration' => 'Migrációs &amp; Console Kimenet (Seed):',
        'console' => 'Alkalmazás Konzol Kimenet:',
        'log' => 'Telepítési Log Tartalma:',
        'env' => 'Végső .env Fájl:',
        'exit' => 'Kattints ide a kilépéshez',
    ],

    /*
     *
     * Update specific translations
     *
     */
    'updater' => [
        /*
         *
         * Shared translations.
         *
         */
        'title' => 'Laravel Updater',

        /*
         *
         * Welcome page translations for update feature.
         *
         */
        'welcome' => [
            'title'   => 'Welcome To The Updater',
            'message' => 'Welcome to the update wizard.',
        ],

        /*
         *
         * Welcome page translations for update feature.
         *
         */
        'overview' => [
            'title'   => 'Overview',
            'message' => 'There is 1 update.|There are :number updates.',
            'install_updates' => 'Install Updates',
        ],

        /*
         *
         * Final page translations.
         *
         */
        'final' => [
            'title' => 'Finished',
            'finished' => 'Application\'s database has been successfully updated.',
            'exit' => 'Click here to exit',
        ],

        'log' => [
            'success_message' => 'Laravel Installer successfully UPDATED on ',
        ],
    ],
];
