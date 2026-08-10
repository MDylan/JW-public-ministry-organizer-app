<?php

// TODO 33.3: a sajat forditasszerkeszto cimkei. A hasznalhato reszuk a
// resources/lang/vendor/translation/hu/translation.php-bol lett atmentve, amit
// a joedixon/laravel-translation eltavolitasa vitt el; az ott talalt harom
// elgepeles ('Tipus', 'tiposnak', 'fajltban') itt javitva van.
return [
    'title' => 'Fordítás',
    'source_locale' => 'Forrásnyelv',
    'target_locale' => 'Fordítandó nyelv',
    'group' => 'Csoport',
    'json_group' => 'Gyökér JSON',
    'key' => 'Kulcs',
    'value' => 'Érték',
    'search' => 'Keresés a kulcsok és a szövegek között',
    'only_missing' => 'Csak a hiányzók',
    'missing_count' => 'Ebben a csoportban :count kulcsnak nincs fordítása.',
    'nothing_found' => 'Nincs a szűrésnek megfelelő kulcs.',
    'save' => 'Mentés',
    'save_all' => 'Oldal mentése',
    'saved' => 'A fordítás mentve.',
    'saved_count' => '{0} Nem változott semmi.|{1} Egy fordítás mentve.|[2,*] :count fordítás mentve.',
    'save_failed' => 'A fordítást nem sikerült menteni.',
    'add_key' => 'Új kulcs',
    'add_key_help' => 'A pontok almezőt hoznak létre, a gyökér JSON-nál viszont a teljes szöveg egyetlen kulcs.',
    'key_added' => 'Az új kulcs létrejött.',
    'key_exists' => 'Ez a kulcs már létezik, vagy a neve érvénytelen.',
    'unsaved_warning' => 'Lapozás, nyelv- vagy csoportváltás elveti a nem mentett módosításokat.',
    'comment_warning' => 'Mentéskor az érintett fájl újraíródik, ezért a benne lévő megjegyzések elvesznek. Változatlan érték mentése nem ír fájlt.',
    'registry_help' => 'Csak a Beállításokban felvett nyelvek jelennek meg. Egy már meglévő nyelvi könyvtár a felvétel után a fájljaival együtt válik szerkeszthetővé.',
];
