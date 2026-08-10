<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

/**
 * The confirmation mail for a FIRST VERIFICATION - the user's current address is
 * not verified yet, so this is not an address change but the first proof of the
 * address given at registration (or requested since).
 *
 * Its counterpart is VerifyNewEmail; the choice is made in
 * MustVerifyNewEmail::sendPendingEmailVerificationMail().
 *
 * Until roadmap TODO 33.5 its view was the package's ENGLISH stub in a 22-locale
 * application, while its sibling had been translated all along. The new view
 * closed that gap.
 *
 * NOTE for tests: this is ShouldQueue, so under `Mail::fake()` it is asserted
 * with assertQueued(), not assertSent().
 */
class VerifyFirstEmail extends Mailable implements ShouldQueue
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

        return $this->markdown('emails.verifyFirstEmail', [
            'url' => $this->pendingUserEmail->verificationUrl(),
        ]);
    }
}
