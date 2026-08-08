<?php

namespace App\Console\Commands;

use App\Models\DayStat;
use App\Models\GroupDate;
use App\Support\Retention\RetentionWindow;
use Illuminate\Console\Command;

/**
 * A retenciós ablaknál régebbi csoportadatok végleges törlése.
 *
 * MIÉRT KÜLÖN PARANCS, ÉS MIÉRT NEM A GDPR KAPCSOLÓ VEZÉRLI
 *
 * A két érintett tábla nem tartalmaz személyes adatot: a `day_stats` sorai
 * csoportot, napot, idősávot és egy DARABSZÁMOT hordoznak, a `group_dates`
 * pedig a csoport adott napi időpont-beállításait. A törlésüket a méret
 * indokolja - élesben 471 754, illetve 48 033 sor, 2022 júniusáig
 * visszamenőleg, retenció nélkül. Ezért saját, admin felületen állítható
 * kapcsolójuk van (`settings.group_data_retention`), nem a GDPR-é: az
 * ezen a telepítésen ki van kapcsolva, és egy karbantartási feladatot nem
 * blokkolhat egy adatvédelmi kapcsoló.
 *
 * MIÉRT MEGY A GROUP_DATES IS
 *
 * A Groups\Statistics a napi sorokat a `group_dates`-ből építi, nem a
 * `day_stats`-ból (`isset($dates[$key])`). Ha csak a statisztika tűnne el,
 * a felület nem üres táblát mutatna, hanem minden régi napra egy sort azzal
 * az állítással, hogy a csoport 0 órát szolgált N elérhetőből. A hamis adat
 * rosszabb, mint a hiányzó, ezért a kettő együtt jár.
 *
 * KÖTEGELÉS
 *
 * Egyik modellen sincs observer és soft delete, tehát a builder ->delete()
 * eleve nyers DELETE. 350 000 sor viszont egyetlen tranzakcióban nem mehet,
 * ezért 5000-es kötegek `id` szerint - a MySQL grammar támogatja a
 * DELETE ... ORDER BY ... LIMIT alakot.
 */
class PurgeOldGroupData extends Command
{
    /** Egy kötegben ennyi sort törlünk. */
    private const BATCH = 5000;

    protected $signature = 'maintenance:purge-old-group-data {--dry-run : Report what would be deleted without deleting anything}';

    protected $description = 'Permanently delete day statistics and group date settings past the configured retention window';

    public function handle()
    {
        $floor = RetentionWindow::groupDataFloor();

        if ($floor === null) {
            $this->info('Group data retention is disabled; nothing to purge.');

            return self::SUCCESS;
        }

        $floor = $floor->toDateString();

        if ($this->option('dry-run')) {
            $stats = DayStat::where('day', '<', $floor)->count();
            $dates = GroupDate::where('date', '<', $floor)->count();

            $this->info("Dry run: {$stats} day statistic(s) and {$dates} group date(s) before {$floor} would be deleted.");

            return self::SUCCESS;
        }

        $stats = $this->purge(DayStat::query()->where('day', '<', $floor));
        $dates = $this->purge(GroupDate::query()->where('date', '<', $floor));

        $this->info("Deleted {$stats} day statistic(s) and {$dates} group date(s) before {$floor}.");

        return self::SUCCESS;
    }

    /**
     * Kötegelt törlés. A lezáró feltétel a ténylegesen érintett sorok száma,
     * nem egy külön count() - így egy párhuzamos írás sem tudja végtelen
     * ciklusba vinni.
     */
    private function purge($query): int
    {
        $deleted = 0;

        do {
            $rows = (clone $query)->orderBy('id')->limit(self::BATCH)->delete();
            $deleted += $rows;
        } while ($rows > 0);

        return $deleted;
    }
}
