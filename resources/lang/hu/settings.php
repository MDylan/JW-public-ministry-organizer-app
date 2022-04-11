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
        'terms_checkbox' => 'Felhasználási feltételek a regisztrációnál (ha kikapcsolod, nem kell elfogadni semmilyen felhasználási feltételeket)',
        'claim_group_creator' => 'Csoport létrehozó jogkör igényelhető a Csoportok oldalon.',
        'debugbar'  => 'Debugbar bekapcsolása (Csak tesztelési/hibajavítási céllal kapcsold be! Egyébként biztonsági kockázat!)',
        'maintenance' => 'Karbantartás mód (Csak adminisztrátorok léphetnek be, senki más.)',
        'gdpr' => 'GDPR bekapcsolása (Akkor kapcsold be, ha az EU-n belül üzemelteted az oldalt). A felhasználók lementhetik a személyes adataikat egy .json fájlba.',
        'use_recaptcha' => 'reCaptcha használata (spam védelem). Bekapcsolásával jelentősen megerősítheted az oldal robotok elleni védelmét. Google recaptchat fog használni a belépésnél és a regisztrációnál az oldal.',
        'use_https' => 'https titkosítás bekapcsolása (Figyelem! Akkor kapcsold be, ha a tárhely szolgáltatód aktiválta a https kapcsolatot. Téves bekapcsolás esetén az oldal nem fog betöltődni!)',
    ],
    'others_saved'  => 'A beállítások sikeresen mentve lettek.',
    'run' => [
        'title'    => 'Artisan parancsok futtatása',
        'optimize' => 'Parancs futtatása',
        'success'  => 'A parancs lefutott!',
    ],
    'recaptcha' => [
        'info' => 'Bekapcsolásával jelentősen megerősítheted az oldal robotok elleni védelmét. Google recaptchat fog használni a belépésnél és a regisztrációnál az oldal.',
    ],
    'main' => [
        'title' => 'Az oldal alapbeállításai',
        'info' => 'Figyelj rá, hogy amit itt módosítasz, az egész oldal használatát _jelentősen_ befolyásolhatja.',
    ],    
    'app_name' => 'Az oldal neve (ez fog megjelenni a levél feladójánál)',
    'app_url' => 'Az oldal webcíme (URL)',
    'timezone' => 'Időzóna',
    'mail' => 'Levél küldés',
    'mail_info' => 'Az oldal számtalan értesítést küld a felhasználónak, így nagyon fontos a levelezés megfelelő beállítása. A "Kapcsolat tesztelése" gomb segítségével tudod mentés nélkül is tesztelni a beállításokat.',
    'mail_test' => 'Kapcsolat tesztelése',
    'mail_test_on_progress' => 'Kérlek várj, próbálok levelet küldeni...',
    'mail_test_success' => 'Sikeres email küldés!',
    'mail_test_error' => 'Hiba a küldés során!',
    'mail_mailer' =>  'Levélküldés módja',
    'mail_host' => 'Levelezési kiszolgáló címe',
    'mail_port' => 'Port (Például: 25 / 465 / 587)',
    'mail_encryption' => 'A levelezéshez használt titkosítás módja',
    'no_encryption' => 'Nincs titkosítás',
    'mail_username' => 'Levelezéshez használt felhasználói név',
    'mail_password' => 'Levelezéshez használt jelszó',
    'mail_from_address' => 'A feladó email címe',
];