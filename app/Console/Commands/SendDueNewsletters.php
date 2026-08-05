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

        foreach ($newsletters as $newsletter) {
            if ($newsletter->send_to == 'groupCreators') {
                $users = User::whereIn('role', ['groupCreator', 'mainAdmin'])->get();
            } elseif ($newsletter->send_to == 'groupAdmins') {
                $users = User::whereHas('userGroupsDeletable')->get();
            } elseif ($newsletter->send_to == 'groupServants') {
                $users = User::whereHas('userGroupsEditable')->get();
            } else {
                // Megőrzött eredeti viselkedés: ismeretlen send_to érték esetén
                // a closure return-nel kilépett, ami a ciklus HÁTRALÉVŐ
                // hírleveleit is kihagyta - nem csak az aktuálisat. A sent_time
                // sem íródik ki, így minden percben újrapróbálkozik, és
                // tartósan blokkolja a mögötte sorban álló hírleveleket.
                // Hibagyanús, de az upgrade alatt szándékosan változatlan.
                // Lásd: roadmap TODO 06.
                return self::SUCCESS;
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

        return self::SUCCESS;
    }
}
