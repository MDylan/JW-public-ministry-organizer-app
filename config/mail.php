<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default Mailer
    |--------------------------------------------------------------------------
    |
    | This option controls the default mailer that is used to send any email
    | messages sent by your application. Alternative mailers may be setup
    | and used as needed; however, this mailer will be used by default.
    |
    */

    'default' => env('MAIL_MAILER', 'smtp'),

    /*
    |--------------------------------------------------------------------------
    | Mailer Configurations
    |--------------------------------------------------------------------------
    |
    | Here you may configure all of the mailers used by your application plus
    | their respective settings. Several examples have been configured for
    | you and you are free to add your own as your application requires.
    |
    | Laravel supports a variety of mail "transport" drivers to be used while
    | sending an e-mail. You will specify which one you are using for your
    | mailers below. You are free to add additional mailers as required.
    |
    | Supported: "smtp", "sendmail", "mailgun", "ses",
    |            "postmark", "log", "array"
    |
    */

    'mailers' => [
        'smtp' => [
            'transport' => 'smtp',
            'host' => env('MAIL_HOST', 'smtp.mailgun.org'),
            'port' => env('MAIL_PORT', 587),
            'encryption' => env('MAIL_ENCRYPTION', 'tls'),
            'username' => env('MAIL_USERNAME'),
            'password' => env('MAIL_PASSWORD'),
            'timeout' => null,

            /*
             * Certificate verification, for a local mail catcher with a
             * self-signed certificate.
             *
             * This replaces a stream/ssl block that WORKED on Laravel 8 -
             * MailManager:222-223 passed it to setStreamOptions() - and has
             * done nothing since Laravel 9, which hands the mailer config to
             * Symfony as DSN options instead. TODO 34 made that switch and
             * nothing noticed, because no test ever built an SMTP transport;
             * TODO 36 restores the behaviour rather than inventing one.
             *
             * Symfony reads a mailer-level verify_peer
             * (EsmtpTransportFactory:36) and turns a falsy value into
             * ssl.verify_peer = false AND ssl.verify_peer_name = false - the
             * two settings of the old block that did anything. Its
             * allow_self_signed has no equivalent and needs none: that one only
             * applies while verification is still on.
             *
             * null outside local rather than true, deliberately:
             * Dsn::getOption() resolves with ??, so null is indistinguishable
             * from an absent key and leaves Symfony's own default alone. That
             * is what keeps this exactly as narrow as the block it replaces.
             *
             * Both halves are pinned by
             * tests/Feature/Mail/MailTransportConfigTest.php.
             */
            'verify_peer' => env('APP_ENV') === 'local' ? false : null,
        ],

        'phpmail' => [
            'transport' => 'smtp',
            'host' => 'localhost',
            'port' => 25,
            'encryption' => null,
            'username' => '',
            'password' => '',
            'timeout' => null,
        ],

        'ses' => [
            'transport' => 'ses',
        ],

        'mailgun' => [
            'transport' => 'mailgun',
        ],

        'postmark' => [
            'transport' => 'postmark',
        ],

        'sendmail' => [
            'transport' => 'sendmail',
            'path' => '/usr/sbin/sendmail -bs',
        ],

        'log' => [
            'transport' => 'log',
            'channel' => env('MAIL_LOG_CHANNEL'),
        ],

        'array' => [
            'transport' => 'array',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Global "From" Address
    |--------------------------------------------------------------------------
    |
    | You may wish for all e-mails sent by your application to be sent from
    | the same address. Here, you may specify a name and address that is
    | used globally for all e-mails that are sent by your application.
    |
    */

    'from' => [
        'address' => env('MAIL_FROM_ADDRESS', 'hello@example.com'),
        'name' => env('MAIL_FROM_NAME', 'Example'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Markdown Mail Settings
    |--------------------------------------------------------------------------
    |
    | If you are using Markdown based email rendering, you may configure your
    | theme and component paths here, allowing you to customize the design
    | of the emails. Or, you may simply stick with the Laravel defaults!
    |
    */

    'markdown' => [
        'theme' => 'default',

        'paths' => [
            resource_path('views/vendor/mail'),
        ],
    ],

];
