<?php

namespace App\Console\Commands;

use App\Models\AdminNewsletter;
use App\Models\User;
use App\Notifications\Newsletter;
use Illuminate\Console\Command;

/**
 * Previously this was the second half of the every-minute closure in
 * App\Console\Kernel::schedule().
 */
class SendDueNewsletters extends Command
{
    protected $signature = 'newsletters:send-due';

    protected $description = 'Send admin newsletters scheduled for today';

    public function handle()
    {
        $newsletters = AdminNewsletter::where('date', today())
            ->where('send_newsletter', 1)
            ->where('status', 1)
            ->whereNull('sent_time')
            ->get();

        $sent = 0;
        $skipped = 0;

        foreach ($newsletters as $newsletter) {
            if ($newsletter->send_to == 'groupCreators') {
                $users = User::whereIn('role', ['groupCreator', 'mainAdmin'])->get();
            } elseif ($newsletter->send_to == 'groupAdmins') {
                $users = User::whereHas('userGroupsDeletable')->get();
            } elseif ($newsletter->send_to == 'groupServants') {
                $users = User::whereHas('userGroupsEditable')->get();
            } else {
                // Unknown send_to value. Previously there was a `return` here, which also
                // skipped the REMAINING newsletters in the loop - not just the
                // current one -, and since sent_time wasn't written out either, the command
                // retried every minute, permanently blocking the queue. A single
                // mistyped recipient value therefore blocked every subsequent newsletter
                // indefinitely.
                //
                // Now we skip the faulty row, log it, and deliver the rest.
                // sent_time DELIBERATELY stays empty: the newsletter didn't go out,
                // so it must not be marked as delivered - but it no longer
                // blocks anyone either.
                $skipped++;

                $this->error(sprintf(
                    'Newsletter #%d skipped: unknown send_to value "%s".',
                    $newsletter->id,
                    $newsletter->send_to
                ));

                report(new \RuntimeException(sprintf(
                    'newsletters:send-due - unknown send_to value "%s" on newsletter #%d',
                    $newsletter->send_to,
                    $newsletter->id
                )));

                continue;
            }

            foreach ($users as $user) {
                $user->notify(new Newsletter([
                    'newsletter_id' => $newsletter->id.'_'.$user->id,
                    'subject' => $newsletter->getTranslation($user->preferredLocale())->subject,
                    'content' => $newsletter->getTranslation($user->preferredLocale())->content,
                    'recipients' => $newsletter->send_to,
                ]));
            }

            $newsletter->sent_time = date('Y-m-d H:i:s');
            $newsletter->save();
            $sent++;
        }

        $this->info("Sent {$sent} newsletter(s).");

        if ($skipped > 0) {
            $this->warn("Skipped {$skipped} newsletter(s) with an unknown send_to value.");

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
