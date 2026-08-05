<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\UserWillBeAnonymizeNotification;
use App\Notifications\UserWillBeAnyonimizeAdminNotification;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Korábban névtelen closure volt az App\Console\Kernel::schedule()-ben,
 * dailyAt('7:10') ütemezéssel.
 *
 * Két értesítést küld:
 *  - az érintett felhasználónak, 15 nappal a törlés előtt,
 *  - a csoportjuk szervezőinek/felelőseinek, egy szűkebb, 6-7 napos ablakban.
 */
class NotifyUpcomingAnonymization extends Command
{
    protected $signature = 'gdpr:notify-anonymization';

    protected $description = 'Notify inactive users and their group editors about upcoming anonymization';

    public function handle()
    {
        if (! config('gdpr.enabled')) {
            $this->warn('GDPR handling is disabled, nothing to do.');

            return self::SUCCESS;
        }

        $this->notifyAffectedUsers();
        $this->notifyGroupEditors();

        return self::SUCCESS;
    }

    private function notifyAffectedUsers(): void
    {
        $date = Carbon::now()->subMonths(config('gdpr.settings.ttl'));
        $date->addDays(15);

        $model = config('gdpr.settings.user_model_fqn', 'App\Models\User');
        $user = new $model();

        $anonymizableUsers = $user::where('last_activity', '!=', null)
            ->where('isAnonymized', 0)
            ->where('last_activity', '<=', $date)
            ->whereNotIn('role', ['mainAdmin', 'groupCreator'])
            ->get();

        foreach ($anonymizableUsers as $user) {
            $lastDate = Carbon::parse($user->last_activity)->addMonths(config('gdpr.settings.ttl'));
            $user->notify(new UserWillBeAnonymizeNotification(['lastDate' => $lastDate->format('Y-m-d')]));
        }

        $this->info('Notified '.$anonymizableUsers->count().' user(s) about upcoming anonymization.');
    }

    private function notifyGroupEditors(): void
    {
        $editorsList = [];
        $submonths = config('gdpr.settings.ttl');

        $minDate = Carbon::now()->subMonths($submonths)->addDays(7);
        $maxDate = Carbon::now()->subMonths($submonths)->addDays(6);

        $anonymizableUsers = DB::table('users AS U')
            ->join('group_user AS GU', function ($join) {
                $join->on('U.id', '=', 'GU.user_id')
                    ->whereNotNull('GU.accepted_at')
                    ->whereNull('GU.deleted_at');
            })
            ->join('groups AS G', function ($join) {
                $join->on('GU.group_id', '=', 'G.id')
                    ->whereNull('G.parent_group_id');
            })
            ->join('group_user as ADMIN', function ($join) {
                $join->on('ADMIN.group_id', '=', 'GU.group_id')
                    ->whereNotNull('ADMIN.accepted_at')
                    ->whereNull('ADMIN.deleted_at')
                    ->whereIn('ADMIN.group_role', ['roler', 'admin']);
            })
            ->select('U.id', 'U.email', 'U.name', 'U.last_activity', 'G.id as group_id', 'G.name as group_name', 'ADMIN.user_id as admin_id')
            ->where('U.last_activity', '!=', null)
            ->where('U.isAnonymized', 0)
            ->whereBetween('U.last_activity', [$maxDate->format('Y-m-d'), $minDate->format('Y-m-d')])
            ->whereNotIn('U.role', ['mainAdmin', 'groupCreator'])
            ->get();

        foreach ($anonymizableUsers as $user) {
            $lastDate = Carbon::parse($user->last_activity)->addMonths($submonths);

            $editorsList[$user->group_id]['admins'][$user->admin_id] = $user->admin_id;
            $editorsList[$user->group_id]['users'][$user->id] = [
                'lastDate' => $lastDate->format('Y-m-d'),
                'last_activity' => $user->last_activity,
                // Nyers DB lekérdezés, ezért a titkosított oszlopokat kézzel
                // kell visszafejteni - az Eloquent cast itt nem fut le.
                'name' => Crypt::decryptString($user->name),
            ];
            $editorsList[$user->group_id]['name'] = Crypt::decryptString($user->group_name);
        }

        foreach ($editorsList as $list) {
            $admins = User::whereIn('id', $list['admins'])->get();
            Notification::send($admins, new UserWillBeAnyonimizeAdminNotification($list));
        }

        $this->info('Notified editors of '.count($editorsList).' group(s).');
    }
}
