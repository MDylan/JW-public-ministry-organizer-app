<?php

return [

    /*
     * The notification that will be sent when a job fails.
     */
    'notification' => \Spatie\FailedJobMonitor\Notification::class,

    /*
     * The notifiable to which the notification will be sent. The default
     * notifiable will use the mail and slack configuration specified
     * in this config file.
     */
    'notifiable' => \Spatie\FailedJobMonitor\Notifiable::class,

    /*
     * By default notifications are sent for all failures. You can pass a callable to filter
     * out certain notifications. The given callable will receive the notification. If the callable
     * return false, the notification will not be sent.
     */
    'notificationFilter' => null,

    /*
     * The channels to which the notification will be sent.
     */
    'channels' => ['mail'],

    'mail' => [
        /*
         * ?: rather than env()'s second argument, and that is not a style
         * preference. An env() default only applies when the KEY IS ABSENT,
         * and .env.example ships MAIL_FROM_ADDRESS=null - which env() resolves
         * to a real null (Illuminate\Support\Env:88), not to the string
         * "null". The default therefore never got a turn on exactly the
         * installs that had not configured mail yet.
         *
         * That matters here more than anywhere else, because
         * Spatie\FailedJobMonitor\Notifiable::routeNotificationForMail() is
         * typed : array and passes this value straight through. A null
         * recipient is a TypeError - raised by the thing whose entire job is
         * to report failed jobs, on the first job that fails. ?: catches the
         * empty string too, which lands in the same place.
         *
         * Measured by tests/Feature/Mail/FailedJobMonitorRouteTest.php: the
         * vendor constraint, env()'s handling of "null", and this line's own
         * output.
         */
        'to' => env('MAIL_FROM_ADDRESS') ?: 'email@example.com',
    ],

    'slack' => [
        'webhook_url' => env('FAILED_JOB_SLACK_WEBHOOK_URL'),
    ],
];
