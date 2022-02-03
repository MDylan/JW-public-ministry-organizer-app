<?php


return [
    'addNew' => 'Új csoport létrehozása',
    'group_head' => 'Csoport alapadatok',
    'name' => 'Csoport neve',
    'editGroup' => 'Csoport szerkesztése',
    'deletegroup' => 'Csoport törlése',
    'notInGroup' => 'Jelenleg még egyetlen csoportnak sem vagy a tagja. Vedd fel a kapcsolatot a gyülekezeted/csoportod felvigyázójával, hogy meg tudjon hívni a helyi csoportba.',
    'areYouSureDelete' => 'Biztosan törlöd ezt a csoportot? A művelet nem vonható vissza! A csoporthoz tartozó minden adat elvész!',
    'groupCreated' => 'A csoport létrejött!',
    'groupUpdated' => 'A csoport sikeresen módosult!',
    'groupDeleted' => 'A csoport törölve lett!',
    'groupDeleted_log' => 'A csoport törölve lett.',
    'role_head' => 'Jogosultságok leírása',
    'roles' => [
        'member' => 'Csoporttag',           
        'helper' => 'Csoport segítő',     
        'roler' => 'Csoportszolga',  
        'admin' => 'Csoportfelvigyázó'
    ],
    'roles_css' => [ //NE FORDÍTSD LE / DO NOT TRANSLATE
        'member' => 'secondary',           
        'helper' => 'info',     
        'roler' => 'success',  
        'admin' => 'primary'
    ],
    'role_helper' => [
        'member' => 'Csak a saját adatait kezelheti.',
        'helper' => 'Szerkesztheti mások időpont foglalását is.',
        'roler' => 'Kezelheti a csoport adatait, jogosultságokat oszthat ki (kivéve csoportfelvigyázó jogkört), híreket szerkeszthet és a statisztikákat is látja.',
        'admin' => 'Bármit csinálhat a csoporttal. Tipp: Egyedül ő képes törölni is a csoportot, ezért ezt a jogosultságot csak korlátozott számban oszd ki. A törlésen kívül minden mást a csoportszolga is el tud végezni.'
    ],
    'min_publishers' => 'Hírnök száma (legalább)',
    'min_publishers_placeholder' => 'Például: 2',
    'max_publishers' => 'Hírnök száma (maximum)',
    'max_publishers_placeholder' => 'Például: 4',
    'min_time' => 'Legkevesebb eltölthető idő',
    'min_time_options' => [
        30 => 'Fél óra',
        60 => '1 óra',
        120 => '2 óra',
    ],
    'max_time' => 'Legtöbbet eltölthető idő',
    'max_time_options' => [
        60 => '1 óra',
        120 => '2 óra',
        180 => '3 óra',
        240 => '4 óra',
        320 => '5 óra',
    ],
    'max_extend_days' => 'Hány nappal előre foglalhatnak le időpontot?',
    'max_extend_days_placeholder' => 'Például 60',
    'need_approval' => 'Jóváhagyás szükséges',
    'need_approval_help' => 'Igen esetén minden jelentkezést külön el kell fogadni.',
    'days_head' => 'Szolgálati napok',
    'calendar_colors' => 'Naptár színek beállítása',
    'color_default' => 'Nincs szolgálat',
    'color_empty' => 'Üres',
    'color_someone' => 'Valaki jelentkezett',
    'color_minimum' => 'Minimum létszám elérve',
    'color_maximum' => 'Maximum létszám elérve',
    'days' => [
        '1' => 'Hétfő',
        '2' => 'Kedd',
        '3' => 'Szerda',
        '4' => 'Csütörtök',
        '5' => 'Péntek',
        '6' => 'Szombat',
        '0' => 'Vasárnap',
    ],
    'start_time' => 'Szolgálat kezdete',
    'end_time' => 'Szolgálat vége',
    'times' => [
        '00:00' => '00:00',
        '00:30' => '00:30',
        '01:00' => '01:00',
        '01:30' => '01:30',
        '02:00' => '02:00',
        '03:00' => '03:00',
        '03:30' => '03:30',
        '02:30' => '02:30',
        '04:00' => '04:00',
        '04:30' => '04:30',
        '05:00' => '05:00',
        '05:30' => '05:30',
        '06:00' => '06:00',
        '06:30' => '06:30',
        '07:00' => '07:00',
        '07:30' => '07:30',
        '08:00' => '08:00',
        '08:30' => '08:30',
        '09:00' => '09:00',
        '09:30' => '09:30',
        '10:00' => '10:00',
        '10:30' => '10:30',
        '11:00' => '11:00',
        '11:30' => '11:30',
        '12:00' => '12:00',
        '12:30' => '12:30',
        '13:00' => '13:00',
        '13:30' => '13:30',
        '14:00' => '14:00',
        '14:30' => '14:30',
        '15:00' => '15:00',
        '15:30' => '15:30',
        '16:00' => '16:00',
        '16:30' => '16:30',
        '17:00' => '17:00',
        '17:30' => '17:30',
        '18:00' => '18:00',
        '18:30' => '18:30',
        '19:00' => '19:00',
        '19:30' => '19:30',
        '20:00' => '20:00',
        '20:30' => '20:30',
        '21:00' => '21:00',
        '21:30' => '21:30',
        '22:00' => '22:00',
        '22:30' => '22:30',
        '23:00' => '23:00',
        '23:30' => '23:30',
        '24:00' => '24:00',
    ],
    'users' => 'Hírnökök',
    'users_helper' => 'Elég az email címet megadnod. Ha nincs még regisztrációja, akkor automatikusan fog neki készülni egy hozzáférés, melyről emailben értesítjük. A nevét, telefonszámát utána kell majd megadnia.',
    'user_add' => 'Hozzáadás',
    'search_placeholder' => 'Minden emailt új sorba írj',
    'note' => 'Megjegyzés',
    'hidden' => 'Rejtett',
    'hidden_helper' => 'A rejtett felhasználók nem fognak látszódni a csoport tagjai között, csak a csoportszolga és a csoportfelvigyázó számára. Csak az események között lesznek láthatóak, ha beterveznek egy szolgálatot.',
    'note_helper' => 'A felhasználóhoz írt megjegyzést csak a Csoportfelvigyázó és a Csoportszolga látja.',
    'notGroupCreator' => 'Ha csoportokat szeretnél létrehozni, akkor kérjük kérj ehhez jogosultságot az oldal adminisztrátoraitól.',
    'requestButton' => 'Ehhez kattints ide, és töltsd ki az űrlapot.',
    'request' => [
        'title' => 'Csoport létrehozásához jogosultság igénylése',
        'congregation' => 'Gyülekezeted',
        'reason' => 'Miért szeretnél saját csoportot létrehozni?',
        'reason_helper' => 'Pl adott gyülekezetet/csoport kiszolgálásához',
        'info' => 'Kérjük, <strong>csak akkor igényelj csoport létrehozási jogosultságot, ha a gyülekezetedben te vagy megbízva ennek szervezésével</strong>. Egyéb esetben kérjük szólj a gyülekezeted felvigyázóinak, hogy ők igényeljenek ilyen jogosultságot, és utána az email címedet megadásával meg tudnak hívni a csoportba. Fenntartjuk a jogot ahhoz, hogy igénylésedet elutasítsuk. Itt megadott adataidat nem fogjuk tárolni, jelen elbírálás után töröljük.',
        'button' => 'Igénylés beküldése',
        'phoneError' => 'A telefonszámod nincs megadva. Kérjük add meg ezt a Profilom oldalon, és utána küld el az igénylésedet!',
        'sent' => 'Az igénylésedet továbbítottuk az oldal adminisztrátorainak. Kérjük, várd meg a válaszukat!',
        'log' => 'Csoport létrehozási jogosultságot igényelt'
    ],
    'requestMail' => [
        'subject' => 'Csoport létrehozási jogosultság igénylése',
        'line_1' => 'Valaki csoport létrehozási jogosultságot igényelt. Adatai: ',
        'line_2' => 'Gyülekezete:',
        'line_3' => 'Az igénylés oka:',
        'line_4' => 'Jogosultságot a Felhasználók menüpontban tudsz neki adni, ha jóváhagyod.',
    ],
    'accept_saved' => 'A meghívást elfogadtad.',
    'accept_log' => 'Belépett a csoportba.',
    'accept_error' => 'Nem sikerült menteni a kérésedet.',
    'accept_rejected' => 'A meghívást elutasítottad.',
    'reject_question' => 'Biztosan elutasítod a meghívást?',
    'reject_message' => 'A művelet nem vonható vissza.',
    'reject_log' => 'Elutasította a csoport meghívást.',
    'logout' => [
        'button' => 'Kilépés',
        'question' => 'Biztosan kilépsz a csoportból?',
        'message' => 'Ezentúl nem fogod látni a csoport eseményeit.',
        'success' => 'Sikeresen kiléptél a csoportból!',
        'error'  => 'Hiba a kilépés során',
        'no_admin' => 'Te vagy az egyedüli csoportfelvigyázó a csoportban. Kilépés előtt add át ezt a jogkört valakinek.',
        'no_other_admin' => 'Nincs más csoportfelvigyázó, jelölj ki valakit helyette.',
        'log' => 'Kilépett a csoportból',
        'self_delete_error' => 'Magadat nem törölheted a csoportból. A Csoportok oldalon lépj ki, ha szeretnél.'
    ],
    'error_no_admin_user' => 'Nem jelöltél ki senkit csoportfelvigyázónak!',
    'error_no_right' => 'Nincs jogosultságod új csoportfelvigyázót kinevezni.',
    'error_no_right_to_remove_admin' => 'Nem veheted el a csoportfelvigyázó jogosultságát.',
    'user' => [
        'saved' => 'A hírnök adatai frissítve lettek.',
        'confirmDelete' => [
            'question' => 'Biztosan törlöd :name hírnököt?',
            'message' => 'Törlés esetén nem fog hozzáférni a csoport naptárához.',
            'success' => 'A hírnök el lett távolítva a csoportból.',
            'error' => 'Sikertelen törlés!'
        ],
        'add' => [
            'title' => 'Új hírnök hozzáadása',
            'info' => 'A hozzáadás gombra kattintva a rendszer azonnal hozzáadja csoporttag jogosultsággal a hírnököt. Emailben értesítve lesz, hogy meghívtad a csoportba, és ha nincs fiókja, akkor a rendszer automatikusan készít neki egyet.',
            'success' => 'Hozzáadva :number új hírnök!'
        ],
        
    ],
    'news' => 'Csoport hírek',
    'news_add' => 'Hír létrehozása',
    'waiting_approval' => 'Még nem fogadta el a meghívást.',
    'manage' => 'Kezelés',
    'literature' => [
        'title' => 'A csoport ezeken a nyelveken terjeszt irodalmat',
        'language' => 'Nyelv',
        'help' => 'Az itt hozzáadott nyelvek alapján tudják a hírnökök leadni a közterületen végzett munka szántóföldi eredményét. Ha nem adsz meg egy nyelvet sem, akkor ez a funkció nem lesz elérhető számukra.',
        'added' => 'A nyelv hozzá lett adva!',
        'saved' => 'A nyelv neve megváltozott',
        'add_error' => 'Hiba a nyelv hozzáadásakor',
        'save_error' => 'Hiba a nyelv mentésekor',
        'tooShort' => 'Kérlek legalább 3 karaktert adj meg.',
        'confirmDelete' => [
            'question' => 'Biztosan törlöd ezt a nyelvet?',
            'message' => 'Ha törlöd, akkor - az űrlap mentése után - minden korábbi szántóföldi jelentés, ami ehhez a nyelvhez lett rögzítve, törlésre kerül.',
            'success' => 'A nyelv törölve lett.',
            'error' => 'Sikertelen törlés!'
        ],
    ],
    'history' => 'Előzmények',
    'days_info' => 'Ha módosítod a napok idő-intervallumát, akkor az a mai naptól lép életbe. Ha már van valakinek betervezve olyan szolgálata, ami kívül esik az új időponttól, akkor az módosítva vagy törölve lesz attól függően, hogy belefér e az új időtartamba vagy sem. A régi szolgálati napokat és a lent megadott különleges napokat ez nem érinti.',
    'special_dates' => [
        'title' => 'Különleges napok',
        'info' => 'Itt különleges napokat adhatsz meg, amikor valamiért eltérő a szolgálat ideje a fent beállítottól, vagy le is tilthatsz adott napot, hogy aznapra ne lehessen szolgálatot betervezni. Ha aznapra már valaki betervezett szolgálatot, akkor mentés után a rendszer automatikusan ellenőrzi, hogy belefér e a megadott időtartamba. Ha nem, akkor módosítja/törli a szolgálatot.',
        'date' => 'Dátum',
        'date_status' => 'Végeztek szolgálatot?',
        'statuses' => [
            0 => 'Nem',
            2 => 'Igen',
        ],
        'statuses_short' => [
            0 => 'Nincs szolgálat',
            2 => 'Van szolgálat',
        ],
        'note' => 'Megjegyzés (A csoport tagjai is látják)',
        'note_placeholder' => 'Pl. különleges kampány.',
        'date_start' => 'Szolgálat kezdete',
        'date_end' => 'Szolgálat vége',
        'confirmDelete' => [
            'question' => 'Biztosan törlöd ezt a napot?',
            'message' => 'Ha törlöd, akkor - az űrlap mentése után - minden erre a napra mentett szolgálat módosítva/törölve lesz, attól függően, hogy miként érinti a módosítás.',
            'success' => 'Az adott nap törölve lett.',
            'error' => 'Sikertelen törlés!'
        ],
    ],
    'min' => 'min',
    'max' => 'max',
    'service' => 'Szolgálat',
    'service_publishers' => 'Minimum :min, maximum :max hírnök',
    'service_time' => 'Minimum :min, maximum :max szolgálat',
    
];
