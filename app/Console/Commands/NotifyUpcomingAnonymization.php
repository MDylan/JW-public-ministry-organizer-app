<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Notifications\UserWillBeAnonymizeNotification;
use App\Notifications\UserWillBeAnyonimizeAdminNotification;
use App\Support\Gdpr\AnonymizationPolicy;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;

/**
 * Previously this was an anonymous closure in App\Console\Kernel::schedule(),
 * scheduled with dailyAt('7:10').
 *
 * Sends two notifications:
 *  - to the affected user, 15 days before deletion,
 *  - to their group's organizers/admins, in a narrower, 6-7 day window.
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

        // TODO 12.2: instead of the previous role filter, the same succession
        // condition decides here as for anonymization. Without this, two bugs would occur:
        // a groupCreator would get anonymized without a warning, and a blocked
        // user would get an email DAILY about a deletion that will never
        // happen - because the query is not a window, but a threshold.
        $anonymizableUsers = $user::where('last_activity', '!=', null)
            ->where('isAnonymized', 0)
            ->where('last_activity', '<=', $date)
            ->get()
            ->filter(fn ($candidate) => AnonymizationPolicy::for($candidate)->allows());

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
            ->get();

        // TODO 12.2: raw join, the succession condition can't be attached here -
        // hence the post-filter. The models are loaded with a single query, and the
        // window is already only one day wide, so the set is small.
        $candidates = User::whereIn('id', $anonymizableUsers->pluck('id')->unique())->get()->keyBy('id');

        $anonymizableUsers = $anonymizableUsers->filter(
            fn ($row) => isset($candidates[$row->id])
                && AnonymizationPolicy::for($candidates[$row->id])->allows()
        );

        foreach ($anonymizableUsers as $user) {
            $lastDate = Carbon::parse($user->last_activity)->addMonths($submonths);

            $editorsList[$user->group_id]['admins'][$user->admin_id] = $user->admin_id;
            $editorsList[$user->group_id]['users'][$user->id] = [
                'lastDate' => $lastDate->format('Y-m-d'),
                'last_activity' => $user->last_activity,
                // Raw DB query, so the encrypted columns must be decrypted
                // manually - the Eloquent cast doesn't run here.
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
