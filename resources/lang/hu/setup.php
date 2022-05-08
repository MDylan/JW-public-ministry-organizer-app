<?php

return [
    'setup' => 'Telepítés',
    'continue' => 'Folytatás',
    'try_again' => 'Próbáld újra',

    'welcome' => 'Üdvözöl a telepítő',
    'intro' => 'A következő lépésekben beállíthatod a programot, hogy megfelelően működjön.',
    'intro.step1' => 'Követelmények ellenőrzése.',
    'intro.step2' => 'Alapbeállítások elvégzése',
    'intro.step3' => 'Adatbázis beállítás és a kapcsolat ellenőrzése.',
    'intro.step4' => 'Levelezés beállítása.',
    'intro.step5' => 'Felhasználói fiókod létrehozása.',

    'check_requirements' => 'Követelmények ellenőrzése',
    'requirements.php_version' => 'PHP verzió >= 8.0.7',
    'requirements.allow_url_fopen' => 'PHP Allow URL fopen',
    'requirements.extension_bcmath' => 'PHP bővítmény: BCMath',
    'requirements.extension_ctype' => 'PHP bővítmény: Ctype',
    'requirements.extension_json' => 'PHP bővítmény: JSON',
    'requirements.extension_mbstring' => 'PHP bővítmény: Mbstring',
    'requirements.extension_openssl' => 'PHP bővítmény: OpenSSL',
    'requirements.extension_pdo_mysql' => 'PHP bővítmény: PDO',
    'requirements.extension_tokenizer' => 'PHP bővítmény: Tokenizer',
    'requirements.extension_xml' => 'PHP bővítmény: XML',
    'requirements.extension_intl' => 'PHP bővítmény: Intl',
    'requirements.env_writable' => '.env fájl létezik és írható',
    'requirements.storage_writable' => '/storage and /storage/logs könyvtárak írhatóak',

    'database_configuration' => 'Adatbázis beállítás',
    'database_configure' => 'Adatbázis beállítása',
    'database.intro' => 'Ha már kitöltötted az adatbázis adatokat az .env fájlban, akkor a beviteli mezők előre ki vannak töltve. Egyéb esetben add meg a megfelelő adatbázis adatokat.',
    'database.config_error' => 'Az adatbázis nincs beállítva. Kérlek ellenőrizd a kapcsolatot. Részletek:',
    'database.db_host' => 'Adatbázis szerver (host)',
    'database.db_port' => 'Adatbázis port',
    'database.db_name' => 'Adatbázis neve',
    'database.db_user' => 'Adatbázis felhasználó',
    'database.db_password' => 'Adatbázis jelszó',
    'database.complete_hint' => 'Az adatbázis beállítása és előkészítése pár másodpercet igénybe vehet. Kérlek légy türelemmel.',

    'database.data_present' => 'Figyelem! Adatokat találtam a megadott adatbázisban. Kérlek ellenőrizd, hogy van mentésed erről az adatbázisról, és erősítsd meg, hogy törlöd az adatokat.',
    'database.overwrite_data' => 'Megerősítem, hogy minden adat törölhető és felülírható az adatbázisban',

    'mail_configure' => 'Levelezés beállítása',
    'mail_intro' => 'Az oldal számtalan értesítést küld a felhasználóknak, így nagyon fontos a levelezés megfelelő beállítása.',
    'mail_validation' => [
        'MAIL_MAILER' => 'levélküldés módja',
        'MAIL_HOST' => 'szerver (host)',
        'MAIL_PORT' => 'port',
        'MAIL_ENCRYPTION' => 'titkosítás módja',
        'MAIL_USERNAME' => 'felhasználói név'
    ],

    'account_setup' => 'Fiók beállítása',
    'account_setup.intro' => 'Mielőtt használatba vehetnéd a programot, el kell készítened a felhasználói fiókodat.',
    'account_setup.name' => 'Írd be a neved',
    'account_setup.email' => 'Írd be az email címed',
    'account_setup.password' => 'Adj meg egy erős jelszót',
    'account_setup.password_requirements' => 'Minimum hossz: 8 karakter',
    'account_setup.password_confirmed' => 'Jelszó megerősítése',
    'account_setup.create' => 'Fiók létrehozása',

    'complete' => 'A telepítés készen van!',
    'outro' => 'Befejezted a telepítést és most már használatba veheted a programot. Már be is léptél. Köszönjük, hogy ezt a programot használod! :)',
];