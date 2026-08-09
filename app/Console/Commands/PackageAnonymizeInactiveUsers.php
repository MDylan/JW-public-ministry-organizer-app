<?php

namespace App\Console\Commands;

use App\Support\Gdpr\AnonymizationPolicy;
use Carbon\Carbon;
use Dialect\Gdpr\Commands\AnonymizeInactiveUsers as PackageCommand;

/**
 * A Dialect csomag `gdpr:anonymizeInactiveUsers` parancsának projekt-változata.
 *
 * MIÉRT KELL. A csomag a saját parancsát a GdprServiceProvider::boot()-ból
 * ütemezi (`$this->app->booted(...)`, naponta 00:00), tehát a Kernel::schedule()
 * lefutása UTÁN - onnan nem szűrhető ki. A parancs törzse pedig két lépésben
 * dolgozik:
 *
 *     $user->anonymize();
 *     $user->update(['isAnonymized' => true]);
 *
 * A TODO 12.2 őre a User::anonymize()-ban ül, tehát az első sort megállítja - a
 * második viszont akkor is ráteszi a jelzőt a védett felhasználóra. Ennek
 * önmagában is súlyos következménye van: a Group::groupUsers() és a Group::users()
 * szűri az isAnonymized-et, a User::routeNotificationFor() pedig null-t ad
 * minden anonimizáltra. Egy védett főadmin adatai megmaradnának, de eltűnne
 * minden csoportlistából és semmilyen levelet nem kapna többé.
 *
 * HOGYAN NYER. Ez az osztály a Kernel $commands tömbjéből van regisztrálva,
 * a csomagé pedig a providerből, Artisan::starting() callbackben. A
 * Kernel::getArtisan() előbb megépíti a konzolalkalmazást (ekkor futnak a
 * starting-callbackek, tehát a csomag parancsa kerül be), és CSAK UTÁNA hívja a
 * resolveCommands($this->commands)-ot. Az azonos nevű parancs felülírja az
 * előzőt, tehát a miénk marad érvényben - ütemezetten is, mert az ütemező a
 * parancs NEVÉT adja tovább.
 *
 * MI NEM VÁLTOZIK. A csomag viselkedésének minden más eleme szándékosan
 * megmarad: nem bontja a csoporttagságokat, és hét órával a projekt saját
 * parancsa előtt fut. Ez a dokumentált divergencia (lásd
 * tests/Feature/Gdpr/AnonymizeCommandDivergenceTest.php), aminek a feloldása
 * a TODO 16 csomagcsere-döntésére tartozik.
 */
class PackageAnonymizeInactiveUsers extends PackageCommand
{
    public function handle()
    {
        if (! config('gdpr.enabled')) {
            return;
        }

        $model = config('gdpr.settings.user_model_fqn', 'App\Models\User');
        $user = new $model();

        $anonymizableUsers = $user::where('last_activity', '!=', null)
            ->where('isAnonymized', 0)
            ->where('last_activity', '<=', Carbon::now()->subMonths(config('gdpr.settings.ttl')))
            ->get();

        foreach ($anonymizableUsers as $user) {
            if (! AnonymizationPolicy::for($user)->allows()) {
                continue;
            }

            $user->anonymize();
            // A csomag eredeti sora. Valójában felesleges - az isAnonymized
            // benne van a $gdprAnonymizableFields-ben, tehát az anonymize()
            // már beállította -, de megtartjuk, hogy a parancs viselkedése
            // egyébként azonos maradjon az eredetivel.
            $user->update([
                'isAnonymized' => true,
            ]);
        }
    }
}
