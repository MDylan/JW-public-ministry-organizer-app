<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Support\Retention\RetentionWindow;
use Illuminate\Support\Facades\DB;
use Illuminate\Console\Command;

/**
 * A retenciós ablaknál régebbi események VÉGLEGES törlése.
 *
 * MIÉRT LÉTEZIK
 *
 * Semmi nem takarította az `events` táblát kor alapján. A
 * `maintenance:daily-cleanup` csak a MÁR soft-deletelt sorokat üríti 3
 * hónapnál, az élő eseményeket nem érinti - így az élesben 164 371 élő
 * eseménysor gyűlt össze 2022 júniusáig visszamenőleg, benne minden hírnök
 * minden szolgálati beosztásával. Ez személyes adat, ezért a GDPR
 * kapcsolóhoz tartozik.
 *
 * A TÖRLÉS BUILDER-SZINTŰ, ÉS EZ NEM STÍLUSKÉRDÉS
 *
 * Az Eloquent\Builder::forceDelete() egyetlen `$this->query->delete()`, tehát
 * NEM indít modelleseményt. Modellpéldányonként törölve az
 * EventObserver::deleted() futna le minden soron: levelet küldene az érintett
 * hírnöknek ÉS a csoport minden adminjának, és soronként írna egy
 * log_histories bejegyzést. Az első éles futásnál ez 121 000 esemény -
 * több százezer levél, és egy naplótábla, ami közben nagyobbra hízna, mint
 * amennyit a törlés felszabadított. Ugyanezt a megoldást használja a
 * DailyCleanup is.
 *
 * A KÖTEGELÉS `id` SZERINT MEGY
 *
 * Az `events.day` nincs önállóan indexelve (csak a `(group_id, day)`
 * összetettben), viszont az `id` és a `day` egy folyamatosan bővülő táblában
 * erősen korrelált, tehát az elsődleges kulcs szerinti 1000-es kötegek
 * gyorsan zárnak. Egyetlen 121 000 soros DELETE ráadásul egyetlen hosszú
 * InnoDB tranzakció lenne, a kaszkáddal együtt.
 *
 * AMI VELE MEGY
 *
 * Az `event_service_reports.event_id` idegen kulcs ON DELETE CASCADE, tehát
 * az esemény szolgálati jelentései (elhelyezések, videók, újralátogatások,
 * bibliatanulmányozások) is törlődnek. Ez tudatos: a jelentés ugyanúgy
 * személyhez kötött adat, mint maga az esemény. A parancs a futás ELŐTT
 * megszámolja őket, hogy a jelentésben ne csak az események száma szerepeljen.
 *
 * A withTrashed() gyakorlatilag semmit nem tesz hozzá - a DailyCleanup a
 * kukát már 3 hónapnál kiüríti -, de nélküle a soft-deletelt régi sorok
 * kimaradnának. Nem teherviselő, csak teljes.
 */
class PurgeOldEvents extends Command
{
    /** Egy kötegben ennyi eseményt törlünk. */
    private const BATCH = 1000;

    protected $signature = 'gdpr:purge-old-events {--dry-run : Report what would be deleted without deleting anything}';

    protected $description = 'Permanently delete events older than the GDPR retention window, with their service reports';

    public function handle()
    {
        // Az őr itt van, nem az ütemezőben: a kézzel indított futástól is
        // védenie kell. A gdpr: előtagú parancsok mind így csinálják.
        if (! config('gdpr.enabled')) {
            $this->warn('GDPR handling is disabled, nothing to do.');

            return self::SUCCESS;
        }

        $floor = RetentionWindow::eventsFloor()->toDateString();

        $reports = DB::table('event_service_reports')
            ->join('events', 'events.id', '=', 'event_service_reports.event_id')
            ->where('events.day', '<', $floor)
            ->count();

        if ($this->option('dry-run')) {
            $events = Event::withTrashed()->where('day', '<', $floor)->count();

            $this->info("Dry run: {$events} event(s) and {$reports} service report(s) before {$floor} would be deleted.");

            return self::SUCCESS;
        }

        $deleted = 0;

        do {
            $ids = Event::withTrashed()
                ->where('day', '<', $floor)
                ->orderBy('id')
                ->limit(self::BATCH)
                ->pluck('id');

            if ($ids->isEmpty()) {
                break;
            }

            $deleted += Event::withTrashed()->whereIn('id', $ids)->forceDelete();
        } while (true);

        $this->info("Deleted {$deleted} event(s) and {$reports} service report(s) before {$floor}.");

        return self::SUCCESS;
    }
}
