<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The confirmation mail for an ADDRESS CHANGE - the user's current address is
 * already verified.
 *
 * Its counterpart is VerifyFirstEmail; the choice is made in
 * MustVerifyNewEmail::sendPendingEmailVerificationMail().
 *
 * NOTE for tests: this is ShouldQueue, so under `Mail::fake()` it is asserted
 * with assertQueued(), not assertSent() - MailFake intercepts it at the queueing
 * step. The queue connection is `sync`, so in production it still goes out in
 * the same request.
 */
class VerifyNewEmail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    /** @var \Illuminate\Database\Eloquent\Model */
    public $pendingUserEmail;

    public function __construct(Model $pendingUserEmail)
    {
        $this->pendingUserEmail = $pendingUserEmail;
    }

    public function build()
    {
        $this->subject(__('Verify Email Address'));

        return $this->markdown('emails.verifyNewEmail', [
            'url' => $this->pendingUserEmail->verificationUrl(),
        ]);
    }
}
