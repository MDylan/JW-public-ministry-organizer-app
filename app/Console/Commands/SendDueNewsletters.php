<?php

namespace App\Console\Commands;

use App\Models\AdminNewsletter;
use App\Models\User;
use App\Notifications\Newsletter;
use Illuminate\Console\Command;

/**
 * Korábban a percenkénti closure második fele volt az
 * App\Console\Kernel::schedule()-ben.
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
                // Ismeretlen send_to érték. Korábban itt `return` állt, ami a
                // ciklus HÁTRALÉVŐ hírleveleit is kihagyta - nem csak az
                // aktuálisat -, és mivel a sent_time sem íródott ki, a parancs
                // percenként újrapróbálkozott, tartósan a sor elé állva. Egyetlen
                // elgépelt címzett-érték tehát minden további hírlevelet
                // határozatlan ideig blokkolt.
                //
                // Most a hibás sort átugorjuk, naplózzuk, és a többit kézbesítjük.
                // A sent_time SZÁNDÉKOSAN üresen marad: a hírlevél nem ment ki,
                // tehát nem szabad kézbesítettnek jelölni - de már nem is
                // akadályoz senkit.
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
