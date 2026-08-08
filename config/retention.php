<?php

/*
|--------------------------------------------------------------------------
| Adatretenció
|--------------------------------------------------------------------------
|
| Meddig őrizzük a régi adatokat. Három adathalmaznak három külön
| kapcsolója van, mert három különböző indok hajtja őket:
|
|   - események (+ a rájuk kaszkádoló szolgálati jelentések): GDPR.
|     A kapcsoló a `gdpr.enabled`, az ablak az itteni `events_months`.
|   - csoportadatok (day_stats, group_dates): MÉRET. Nincs bennük
|     személyes adat, ezért nem a GDPR kapcsolóhoz tartoznak, hanem egy
|     admin felületi beállításhoz (`settings.group_data_retention`),
|     aminek az engedélyezett értékeit a `group_data_options` sorolja fel.
|   - aktivitásnapló: a config/activitylog.php `delete_records_older_than_days`
|     kulcsa (90 nap), a Spatie `activitylog:clean` parancsán keresztül.
|
| Ebben a fájlban SZÁNDÉKOSAN nincs env() hívás. A retenciós ablak
| szabályzati döntés, nem telepítésenként változó beállítás, és így a fájl
| eleve `config:cache`-biztos - lásd a config/security.php magyarázatát.
|
| A padlókat sehol nem szabad kézzel újraszámolni: az egyetlen igazságforrás
| az App\Support\Retention\RetentionWindow, amit a parancsok és a felületi
| korlátok egyaránt olvasnak.
|
*/

return [

    /*
    | Hány hónapnál régebbi eseményeket törlünk. A törlés VÉGLEGES, és az
    | idegen kulcson keresztül elviszi az esemény szolgálati jelentéseit is
    | (event_service_reports, ON DELETE CASCADE).
    */
    'events_months' => 13,

    /*
    | A `group_data_retention` admin beállítás engedélyezett értékei
    | hónapban; a '0' a kikapcsolt állapot. Ez a lista egyszerre szolgál a
    | felületi választó forrásaként és a RetentionWindow whitelistjeként:
    | a settings táblába validáció nélkül is bekerülhet érték, ezért a
    | beolvasásnál CASTOLNI TILOS - egy (int) 'x' nullát adna, a nulla
    | hónapos ablak pedig az egész táblát letarolná.
    */
    'group_data_options' => ['0', '12', '24'],

];
