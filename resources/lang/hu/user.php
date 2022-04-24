<?php

return [
    'email' => 'Email',
    'registered' => 'Regisztrált',
    'addNew' => 'Új felhasználó hozzáadása',
    'emailExists' => 'Ezzel az email címmel már van regisztráció',
    'login' => 'Belépés',
    'rememberMe' => 'Emlékezzen rám',
    'captcha_error' => 'Hibás captcha, vagy túl sokat vártál. Próbáld újra.',
    'lostPassword' => 'Elfelejtett jelszó',
    'register' => 'Regisztráció',
    'registerMessage' => 'Új fiók létrehozása',
    'name' => 'Név',
    'password' => 'Jelszó',
    'passwordConfirmation' => 'Jelszó megerősítése',
    'password_info' => 'Minimum 8 karakter, kis és nagybetű valamint szám is kötelező.',
    'agreeTerms' => 'Elfogadom a használat <a href="/page/terms" target="_blank">feltételeit</a>',
    'loginWithUser' => 'Már van fiókom, belépek',
    'updateProfile' => 'Adataim szerkesztése',
    'userData' => 'Elérhetőségeim',
    'updatePassword' => 'Jelszó módosítás',
    'phone' => 'Telefonszám',
    'editUser' => 'Felhasználó szerkesztése',
    'userSaved' => 'A felhasználó adatai el lettek mentve.',
    'deleteUser' => 'Felhasználó törlése',
    'areYouSureDelete' => 'Biztosan törlöd ezt a felhasználót (:userName)? A művelet nem vonható vissza!',
    'userDeleted' => 'A felhasználó törölve lett',
    'password_updated' => 'A jelszavad sikeresen módosult.',
    'profile_updated' => 'Az adataid sikeresen módosultak.',
    'profile_empty' => 'Kérjük add meg a hiányzó adataidat ahhoz, hogy továbbléphess.',
    'last_login' => 'Legutóbbi belépés',
    'phone_helper' => 'Csak szám lehet országhívóval az elején, pl:',
    'finish' => [
        'registration' => 'Regisztráció véglegesítése',
        'helper' => 'Kérjük véglegesítsd a regisztrációdat, a lenti adatok megadásával.',
        'button' => 'Regisztráció véglegesítése',
        'cancel' => 'Kérem az email címem törlését a rendszerből',
        'done' => 'A regisztrációd elkészült! :) Az alábbi oldalon tudod elfogadni vagy elutasítani a csoport meghívásaidat.',
        'cancelAlert' => 'Biztosan törlöd az email címedet?',
        'cancelDone' => 'Az email címedet töröltük az adatbázisunkból.',
        'info' => 'Tájékoztatás az adataid védelméről: Személyes adataidat csak jelen oldalon használjuk fel. Az email címedet és a telefonszámodat alapértelmezetten mindenki láthatja, akivel egy csoportban szolgálsz, de a regisztráció után a "Profilom" menüben beállíthatod, hogy csak a Csoportszolgák és Csoport segítők láthassák.',
    ],
    'gdpr' => [
        'my_personal_datas' => 'Személyes adataim',
        'info' => 'A jelenlegi jelszavad megadása után, itt letöltheted a személyes adataidat, amit tárolunk rólad.',
        'download' => 'Adataim letöltése'
    ],
    'delete' => [
        'title' => 'Adataim törlése',
        'info' => 'A jelszavad megadása után törölheted személyes adataidat.',
        'alert' => 'Figyelem, ezt követően a jelenlegi profilod törölve lesz, minden személyes adatod elvész, és a rendszer azonnal kiléptet.',
        'verify_needed' => 'Adataid törlését meg kell erősítened. Egy emailt küldtünk, és ott rá kell kattintanod a hivatkozásra.',
        'button' => 'Kérem az adataim törlését',
        'success' => 'Az adataidat töröltük. Köszönjük, hogy eddig használtad az oldalunkat!'
    ],
    'calendars' => 'Naptárak',
    'calendars_info' => 'Ha bekapcsolod valamelyiket, akkor a jobb oldali esemény sávról gyorsan hozzá tudod adni a naptáradhoz a szolgálatodat.',
    'calendar' => [
        'google' => 'Google',
        'yahoo' => 'Yahoo',
        'webOutlook' => 'Outlook',
        'webOffice' => 'Office',
        'ics' => 'ics fájl'
    ],
    'first_day_of_week' => 'A hét első napja',
    'two_factor' => [
        'title' => 'Kétlépcsős azonosítás (2FA) beállítása',
        'help' => 'A kétlépcsős azonosítás (más néven kétfaktoros hitelesítés) egy plusz biztonsági szinttel látja el fiókodat, arra az esetre, ha ellopják a jelszavadat. A kétlépcsős azonosítás beállítása után két dolgot kell használod, amikor bejelentkezel a fiókodba: A jelszavadat és egy eszközt, pl a telefonodat.',
        'enabled' => 'A kétlépcsős azonosítás engedélyezve lett!',
        'scan' => 'Szkenneld be a lenti QR kódot az eszközöd hitelesítő alkalmazásával, hogy be tudj lépni az oldalra legközelebb. Ha nem ismersz ilyen alkalmazást, lent találsz javaslatokat',
        'disabled' => 'A kétlépcsős azonosítás ki lett kapcsolva.',
        'store_it' => 'Kérlek mentsd el a lenti visszaállítási kódokat BIZTONSÁGOS helyre. Ha valamiért nem férsz hozzá a telefonodhoz, akkor ezek használatával tudni majd belépni.',
        'status_enabled' => 'A kétlépcsős azonosítás (2FA) jelenleg be van kapcsolva.',
        'status_disabled' => 'A kétlépcsős azonosítás (2FA) jelenleg ki van kapcsolva.',
        'button_disable' => 'Kétlépcsős azonosítás kikapcsolása',
        'add_code' => 'Add meg a hitelesítő kódot',
        'add_recovery' => 'Kérlek add meg az egyik visszaállítási kódodat.',
        'recommended_apps' => 'Javasolt alkalmazások',
        'recovery_codes' => 'Visszaállítási kódok',
        'error' => 'Hibás azonosítási kód!',
        'please_confirm' => 'Kérjük add meg a hitelesítő alkalmazásban lévő kódot, ezzel visszaigazolod, hogy megfelelően beállítottad a kétlépcsős azonosítást. Amíg ezt nem teszed meg, a kétlépcsős azonosítás nem fog működni.',
        'no_device' => 'Nincs nálam az eszköz, kódot adok meg.',
        'have_device' => 'Hitelesítő kódot adok meg.',
        'copy_to_clipboard' => 'Vágolapra másolás',
        'copy_success' => 'A kódok a vágólapra kerültek.',
    ],
    'online' => 'Épp online',
    'hidden' => [
        'title' => 'Adataim elrejtése',
        'help' => 'Az email címedet és a telefonszámodat alapértelmezetten mindenki láthatja, akivel egy csoportban szolgálsz. Itt megadhatod, hogy csak azok lássák akik jogosultsággal bírnak a csoportodban (pl. csoportszolga, csoport segítő).',
    ],

];