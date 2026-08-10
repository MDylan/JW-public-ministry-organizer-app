<?php

/*
|--------------------------------------------------------------------------
| Pending e-mail address confirmation
|--------------------------------------------------------------------------
|
| This file started life as the published config of
| `protonemedia/laravel-verify-new-email`. Roadmap TODO 33.5 replaced the
| package with in-house code, but the file STAYS: its keys are meaningful and
| the test suite reads them. Only the `route` key is gone - it meant "should the
| package load its own route file?", and the route now belongs to the
| application (routes/web.php).
|
*/

return [
    /**
     * Where a visitor lands after a successful confirmation. The target route
     * (`user.new-email-verified`) decides whether they go on to the login page
     * or the home page - see User\Profile::redirectAfterNewEmailVerification().
     */
    'redirect_to' => '/user/new-email-verified',

    /**
     * Whether to log the user in after confirming.
     *
     * NO by default: the link is normally opened on a different device than the
     * one that made the request, and a link sitting in an inbox must not be a
     * way into the account.
     */
    'login_after_verification' => false,

    /**
     * Whether to remember the user permanently. Only matters when the setting
     * above is true.
     */
    'login_remember' => false,

    /**
     * The model that stores pending addresses.
     *
     * It carries two guards - an anonymized user, and an address taken in the
     * meantime; see the class docblock.
     */
    'model' => \App\Models\PendingUserEmail::class,

    /**
     * The mail a NOT YET verified user receives: this is the first
     * confirmation, not an address change.
     */
    'mailable_for_first_verification' => \App\Mail\VerifyFirstEmail::class,

    /**
     * The mail an already verified user receives when moving to a new address.
     */
    'mailable_for_new_email' => \App\Mail\VerifyNewEmail::class,
];
