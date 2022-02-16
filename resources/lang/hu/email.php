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
    'finishRegistration' => [
        'subject' => 'Meghívtak a(z) :appName oldal felhasználói közé',
        'line_1' => 'Szeretnénk értesíteni, hogy :groupAdmin meghívott a(z) :appName felhasználói közé!',
        'line_2' => 'A regisztrációd még nem végleges, :day nap áll rendelkezésedre, hogy a lenti linkre kattintva véglegesítsd a regisztrációt, egyéb esetben a fiókodat automatikusan törölni fogjuk.',
        'line_3' => 'Felhasználónév: :userMail',
        'line_4' => 'A regisztráció véglegesítése során tudod megadni a kívánt jelszavadat is.',
        'line_5' => 'A lenti linket csak a regisztráció véglegesítéséhez tudod használni.',
        'done' => [
            'subject' => 'Regisztrációd elkészült!',
            'line_1' => 'Üdvözlünk az oldal felhasználói között. Mostantól be tudsz lépni a beállított jelszavaddal. A Csoportok menüben tudod elfogadni vagy elutasítani a csoportmeghívásaidat. Az oldal további használatáról a Súgó menüben tájékozódhatsz.'
        ]
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
            'user_logout' => 'Kiléptél a csoportból, így a kilépésed utáni szolgálataid is törölve lettek.',
            'service_day_deleted' => 'Erre a napra nem lehet már szolgálatot beütemezni, ezért törölve lett mindenki szolgálata.',
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
    'ParentGroupAttached' => [
        'subject' => 'Fontos: Új főcsoport lett beállítva',
        'line_1' => 'Szeretnénk értesíteni, hogy a(z) :groupName csoportodhoz új alcsoport lett rendelve.',
        'line_2' => 'Ezentúl minden hírnök automatikusan átmásolára kerül az alcsoportba.',
        'line_3' => 'Az alcsoport neve: :childGroupName',
        'line_4' => 'A módosítást :userName végezte el.',
        'line_5' => 'Ha változtatni szeretnél ezen a beállításon, akkor kérjük menj a "Hírnökök" menübe és szüntesd meg az összekapcsolást.'
    ],
    'ParentGroupDetached' => [
        'subject' => 'Fontos: A főcsoport beállítás megszűnt',
        'line_1' => 'Szeretnénk értesíteni, hogy a(z) :childGroupName csoportod le lett kapcsolva az eddigi főcsoportról.',
        'line_2' => 'Ezentúl nem kerülnek át automatikusan a hírnökök ebbe a csoportba.',
        'line_3' => 'Az eddigi főcsoport neve: :groupName',
        'line_4' => 'A módosítást :userName végezte el.',
        'line_5' => 'Ha változtatni szeretnél ezen a beállításon, akkor kérjük menj a "Hírnökök" menübe és kapcsold össze a két csoportot újra.'
    ],
    'GroupUserLogout' => [
        'subject' => 'Kiléptél a(z) :groupName csoportból',
        'line_1' => 'Értesítünk, hogy kiléptél a(z) :groupName csoportból. Ezentúl nem fogod látni a csoport naptárát, és a jövőbeli szolgálataid is törölve lettek ebből a csoportból.',
        'line_2' => 'A kilépésedet :userName kezdeményezte, ha ez nem te vagy, akkor nála érdeklődhetsz ennek okáról.'
    ],
    'deletePersonalData' => [
        'subject' => 'Személyes adataid törlése',
        'line_1' => 'Kérted, hogy töröljük a személyes adataidat.',
        'line_2' => 'A lenti hivatkozásra kattintva kérjük erősítsd meg ezt a szándékodat. A gombra kattintással a törlés automatikusan megtörténik.',
        'line_3' => 'A biztonságod miatt ez a hivatkozás 1 órán át érvényes. Ha nem te kérted az adataid törlését, akkor ne kattints a linkre, és haladéktalanul változtass jelszót az oldalon, mert valaki más tette meg ezt a helyedben.',
    ],
];