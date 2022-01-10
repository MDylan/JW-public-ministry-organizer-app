<?php

return [
    'dear' => 'Kedves',
    'registerWelcome' => 'Elkészült a regisztrációd a :url oldalon. Kérjük, hogy aktiváld a regisztrációdat azzal, hogy az alábbi hivatkozásra kattintasz.',
    'registerFail' => 'Amennyiben nem te kérted ezt a regisztrációt, regisztrációdat automatikusan törölni fogjuk 48 óra múlva.',
    'signature' => 'Üdvözlettel a Közterület Szervező oldal készítői',
    'newadmin' => [
        'subject' => 'Új adminisztrátor lett kinevezve',
        'line_1' => 'Ez egy automatikus értesítés arról, hogy :newAdmin ki lett nevezve az oldalad adminisztrátornak.',
        'line_2' => 'Aki kinevezte: :adminBy',
        'line_3' => 'Ha ez tévedés, akkor kérlek mielőbb vedd el tőle az Adminisztrátor jogosultságot!'
    ],
    'groupUserAdded' => [
        'subject' => 'Meghívás a(z) :groupName csoportba',
        'line_1' => 'Ez egy automatikus értesítés arról, hogy :groupAdmin meghívott, hogy csatlakozz a(z) :groupName csoporthoz.',
        'line_2' => 'A bejelentkezésedet követően a "Csoportok" menüben tudod elfogadni vagy elutasítani a meghívást.',
    ],
    'loginData' => [
        'subject' => 'Belépési adataid',
        'line_1' => ':groupAdmin létrehozott neked egy fiókot az oldalon.',
        'line_2' => 'Bejelentkezési adataid:',
        'line_3' => 'Felhasználónév: :userMail',
        'line_4' => 'Jelszó: :userPassword',
        'line_5' => 'Kérjük, ezt a jelszót mindenképpen változtasd meg a bejelentkezésedet követően a "Profilom" oldalon.',
    ],
    'event' => [
        'deleted' => [
            'subject' => 'A :date napra tervezett szolgálatod törölve lett! (:groupName)',
            'line_1' => 'Szeretnénk értesíteni, hogy a(z) :groupName csoportban az alábbi szolgálatod törölve lett:',
            'line_2' => ':oldServiceDate',
            'line_3' => 'A törlés oka: :reason',
            'line_4' => 'A törlést :userName kezdeményezte, ő tud bővebb felvilágosítással szolgálni.',
        ],
        'deletion_reasons' => [
            'unknown' => 'Ismeretlen.',
            'modified_service_time' => 'Ezen a napon módosult a szolgálat ideje, és a tervezett szolgálatod nem fért bele az új szolgálati időbe.',
        ],
        'modified' => [
            'subject' => 'A :date napra tervezett szolgálatod módosítva lett! (:groupName)',
            'line_1' => 'Szeretnénk értesíteni, hogy a(z) :groupName csoportban az alábbi szolgálatod módosult:',
            'line_2' => 'Régi időpont: :oldServiceDate',
            'line_3' => 'Új időpont: :newServiceDate',
            'line_4' => 'Kérjük ennek megfelelően tervezd a szolgálatodat.',
            'line_5' => 'A módosítás oka: :reason',
            'line_6' => 'A módosítást :userName kezdeményezte, ő tud bővebb felvilágosítással szolgálni.',
        ],
        'modify_reasons' => [
            'unknown' => 'Ismeretlen.',
            'modified_service_time' => 'Ezen a napon módosult a szolgálat ideje, és a tervezett szolgálatod nem fért bele az új szolgálati időbe.',
        ],
        'created' => [
            'subject' => 'A :date napra tervezett szolgálatod (:groupName)',
            'line_1' => 'Szeretnénk értesíteni, hogy a(z) :groupName csoportban :userName az alábbi szolgálatot tervezte be számodra.',
            'line_2' => ':newServiceDate',
            'line_3' => 'Jó szolgálatot! :)',
        ],
        'status_changed' => [
            '0' => [
                'subject' => 'A szolgálatod elbírálás alatt van :date',
                'line_1' => 'Szeretnénk értesíteni, hogy a(z) :groupName csoportban az alábbi szolgálatod elbírálás alatt van. Egy csoportszolga hamarosan eldönti, hogy megfelelő e az időpont, és értesítést fogsz kapni a döntéséről. Kérjük, amíg ez nem történik meg, ne vedd biztosra a szolgálatot.',
                'line_2' => ':newServiceDate',
            ],
            '1' => [
                'subject' => 'A tervezett szolgálatodat jóváhagyták :date !',
                'line_1' => 'Szeretnénk értesíteni, hogy a(z) :groupName csoportban az alábbi szolgálatot elfogadták. Jó szolgálatot! :)',
                'line_2' => ':newServiceDate',
            ],
            '2' => [
                'subject' => 'A tervezett szolgálatodat nem fogadták el :date ',
                'line_1' => 'Szeretnénk értesíteni, hogy a(z) :groupName csoportban az alábbi szolgálatot nem fogadták el. Kérjük, hogy keress egy másik időpontot a szolgálatod végzéséhez.',
                'line_2' => ':newServiceDate',
            ],

        ]
    ],
];