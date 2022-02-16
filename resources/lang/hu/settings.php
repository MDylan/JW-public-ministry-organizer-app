<?php

return [
    'languages' => [
        'title' => 'Elérhető nyelvek',
        'country_code' => 'Országkód',
        'country_name' => 'Nyelv (Ez jelenik meg a választólistában.)',
        'empty'        => 'Még nem adtál hozzá nyelvet.',
        'default'   => 'Alapértelmezett nyelv',
        'lang_help' => 'A nyelvválasztó legalább 2 elérhető nyelv esetén jelenik meg.<br/>
                        Meglévő nyelv módosításához írd be újra az országkódot és a módosítandó szöveget.
                        Kijelölhetsz "Fordító" jogkört azon felhasználók számára, akiknek lehetővé szeretnéd tenni az online fordítási lehetőséget.
                        <hr>
                        Mielőtt elérhetővé teszel egy új nyelvet, kérjük győződj meg róla, hogy a nyelvi fájlok elérhetőek a "/resources/lang" mappában. <br/>
                        Ha az adott nyelven nincs lefordítva valami, akkor ettől még használható lesz az oldal, de nem fog megjelenni értelmezhető szöveg a tartalom helyén.<br/>
                        Bővebb információért lásd a <a class="alert-link" href="https://laravel.com/docs/8.x/localization" target="_blank">laravel dokumentációt</a>.',
        'success' => 'A nyelv hozzá lett adva',
        'translate' => 'Fordítás',
        'start_translation' => 'Fordítás elkezdése',
        'confirmDelete' => [
            'question'  => 'Biztosan törlöd ezt a nyelvet?',
            'message'   => 'Ezt követően a nyelv nem lesz elérhető senki számára.',
            'success'   => 'Sikeresen törölted a nyelvet.'
        ],
        'defaultSet' => [
            'success'   => 'Az új alapértelmezett nyelv beállítva',
            'error'     => 'Nem létezik ez a nyelv a nyelvek listájában!',
        ],
        'visibility' => [
            'show'  => 'Mindenki számára látható',
            'admin' => 'Csak admin számára látható'
        ],
        'visibility_changed' => 'A nyelv láthatósága megváltozott.',
        'translator_help' => 'Új nyelvet egy adminisztrátor tud felvenni, és ő tudja beállítani a nyelv láthatóságát is. Ha elkészültél a fordítással akkor neki jelezheted ezt.',
    ],
    'others' => [
        'title' => 'Egyéb beállítások',
        'registration'  => 'Regisztrálás lehetősége. (Ha kikapcsolod, csak belépett felhasználó - pl csoportfelvigyázó - hozhat létre új felhasználót).',
        'claim_group_creator' => 'Csoport létrehozó jogkör igényelhető a Csoportok oldalon.',
        'debugbar'  => 'Debugbar bekapcsolása (Csak tesztelési/hibajavítási céllal kapcsold be! Egyébként biztonsági kockázat!)',
        'maintenance' => 'Karbantartás mód (Csak adminisztrátorok léphetnek be, senki más.)',
        'gdpr' => 'GDPR bekapcsolása (Akkor kapcsold be, ha az EU-n belül üzemelteted az oldalt). A felhasználók lementhetik az adataikat, és aki 1 éve nem lépett be, az törölve lesz. Előtte e-mail értesítőt kap.',
    ],
    'others_saved'  => 'A beállítások sikeresen mentve lettek.',
    'run' => [
        'title'    => 'Artisan parancsok futtatása',
        'optimize' => 'Parancs futtatása',
        'success'  => 'A parancs lefutott!',
    ]
];