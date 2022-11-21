<?php


return [
    'addNew' => 'Új csoport létrehozása',
    'group_head' => 'Csoport alapadatok',
    'name' => 'Csoport neve',
    'editGroup' => 'Csoport szerkesztése',
    'deletegroup' => 'Csoport törlése',
    'notInGroup' => 'Jelenleg még egyetlen csoportnak sem vagy a tagja. Vedd fel a kapcsolatot a gyülekezeted/csoportod felvigyázójával, hogy meg tudjon hívni a helyi csoportba.',
    'areYouSureDelete' => 'Biztosan törlöd ezt a csoportot (:groupName)? A művelet nem vonható vissza! A csoporthoz tartozó minden adat elvész!',
    'deleteUsers' => 'Töröld a hírnököket is a programból.',
    'deleteUsersInfo' => 'Ha bejelölöd, akkor a csoportban lévő minden hírnök regisztrációja és személyes adata is törölve lesz, téged is beleértve. Kivéve azokat, akik más csoportban is szolgálnak. A törlés pár percen belül megtörténik, és visszavonhatatlan.',
    'groupCreated' => 'A csoport létrejött!',
    'create_info' => 'A létrehozás után tudod majd a csoport adatait szerkeszteni. Téged automatikusan hozzá fog adni csoportfelvigyázó jogkörrel. Mindenki mást majd a Hírnökök menüben tudsz hozzáadni.',
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
    'role_helper' => [
        'member' => 'Csak a saját adatait kezelheti.',
        'helper' => 'Szerkesztheti mások időpont foglalását is.',
        'roler' => 'Kezelheti a csoport adatait, jogosultságokat oszthat ki (kivéve csoportfelvigyázó jogkört), híreket szerkeszthet és a statisztikákat is látja.',
        'admin' => 'Bármit csinálhat a csoporttal. Tipp: Egyedül ő képes törölni is a csoportot és összekötni a csoportot egy másikkal (ahol szintén csoportfelvigyázó joga van), ezért ezt a jogosultságot csak korlátozott számban oszd ki. Ezeken kívül minden mást a csoportszolga is el tud végezni.'
    ],
    'min_publishers' => 'Hírnök száma (legalább)',
    'min_publishers_placeholder' => 'Például: 2',
    'max_publishers' => 'Hírnök száma (maximum)',
    'max_publishers_placeholder' => 'Például: 4',
    'min_time' => 'Legkevesebb eltölthető idő',
    'group_languages' => 'A csoport nyelvei',
    'group_languages_info' => 'A híreket ezeken a nyelveken adhatod meg. A felhasználó felületet nem érinti. Ha nem adsz meg semmit, akkor mindegyik nyelv elérhető marad.',
    'min_time_options' => [
        30 => 'Fél óra',
        60 => '1 óra',
        90 => '1,5 óra',
        120 => '2 óra',
    ],
    'max_time' => 'Legtöbbet eltölthető idő',
    'max_time_options' => [
        60 => '1 óra',
        90 => '1,5 óra',
        120 => '2 óra',
        180 => '3 óra',
        240 => '4 óra',
        320 => '5 óra',
        360 => '6 óra',
        420 => '7 óra',
        480 => '8 óra',
    ],
    'replyToAddress' => 'Válasz email cím',
    'replyToHelper' => 'Megadhatsz egy email címet, ahová a hírnök válaszolhat, ha értesítést kap valamiről (pl. a szolgálata elfogadásáról). Ha üresen hagyod, akkor a rendszer email címe lesz beállítva (:defaultMail).',
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
    'color_explanation' => [
        'title' => 'A színek magyarázata',
        'info' => 'A különböző színek segítenek gyorsan átlátnod, hogy adott napon hol van még lehetőség szolgálatra.',
        'color_default' => 'Ezen a napon nincs szolgálat.',
        'color_empty' => 'Ebben az idősávban még senki nem jelentkezett szolgálatra.',
        'color_someone' => 'Valaki jelentkezett, de még nincs meg a minimális létszám.',
        'color_minimum' => 'A minimális létszámú hírnök megvan, de még lehet jelentkezni.',
        'color_maximum' => 'Betelt a hírnökök száma.',
        'your_service' => 'Ezen a napon neked is szolgálatod van.',
    ],
    'showPhone' => 'Telefonszámok megjelenítése',
    'showPhone_help' => 'Mutassa a telefonszámokat a naptárban?',
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
    'disabled_time_slots' => 'Letiltott időpontok',
    'disabled_time_slots_info' => 'Figyelem! A kiválasztott időpontokra NEM lehet majd időpontot foglalni!',
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
    'finish_guest_registration' => [
        'label' => 'Vendég regisztráció',
        'help' => 'Ha bejelölöd, akkor a felhasználónak nem kell a regisztrációt megcsinálnia, hanem belépteti a rendszer és bekerül a csoportba is. Új jelszót is kap, melyről emailben értesítjük.',
        'alert' => 'Ezt a funkciót csak olyan hírnöknél használd, aki egyébként nem tudna regisztrálni.'
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
            'error' => 'Sikertelen törlés!',
            'error_this_is_child' => 'A hírnököt csak a főcsoportban lehet törölni, itt nem.',
        ],
        'add' => [
            'title' => 'Új hírnök hozzáadása',
            'info' => 'A hozzáadás gombra kattintva a rendszer azonnal hozzáadja csoporttag jogosultsággal a hírnököt. Emailben értesítve lesz, hogy meghívtad a csoportba, és ha nincs fiókja, akkor a rendszer automatikusan készít neki egyet.',
            'email_language' => 'Az email értesítő ezen a nyelven menjen (ha nincs még fiókja):',
            'email_language_error' => 'A kiválasztott nyelv nem érhető el.',
            'success' => 'Hozzáadva :number új hírnök!'
        ],
        
    ],
    'link' => [
        'title' => 'Csoportok összekötése',
        'help' => 'Itt választhatsz egy főcsoportot, ahonnan automatikusan át szeretnéd másolni a hírnököket.',
        'no_admin_in_other_group' => 'Nem vagy csoportfelvigyázó más csoportokban.',
        'danger' => 'Figyelem! Az összekapcsolást követően a választott főcsoport hírnökei ide is be lesznek téve. A jogosultságok NEM másolódnak át, azt külön be tudod majd állítani, ahogy szeretnéd. Viszont aki nem szerepel a főcsoportban, az ebből az alcsoportból törölve lesz.',
        'button' => 'Összekapcsolás',
        'error_no_selection' => 'Nem választottál csoportot.',
        'error_not_in_group' => 'A kiválasztott csoportban nem vagy csoportfelvigyázó.',
        'error_same_group' => 'Magával nem kötheted össze a csoportot! :)',
        'error_this_is_child' => 'A kiválasztott csoport már egy másik csoporthoz van kapcsolva! Előbb azt meg kell szüntetni.',
        'error_this_is_parent' => 'Egy másik csoport már össze van kapcsolva a jelenleg szerkesztett csoportoddal. Előbb azt a kapcsolatot szüntesd meg.',
        'success' => 'Az összekapcsolás sikeres volt!',
        'error' => 'Hiba az összekapcsolás közben.',
        'parent' => [
            'help' => 'Tájékoztatás: A hírnökök más csoporthoz is át vannak másolva.',
            'info' => 'Ennek a csoportnak a tagjait az alábbi alcsoportokba másolja át automatikusan a rendszer. Ha megszűnteted a kapcsolatot köztük, a csoportok tagjai nem fognak törlődni sehol, viszont ezentúl nem kerül átmásolásra az alcsoportba senki, akit felveszel ebbe a csoportba.',
            'child_group_name' => 'Alcsoport neve',
            'detach' => [
                'button' => 'Szétkapcsolás',
                'question' => 'Valóban lekapcsolod a(z) :groupName csoportot?',
                'message' => 'Ezt követően a csoport tagjai nem fognak átmásolódni oda.',
                'success' => 'Sikeres szétkapcsolás!',
                'error' => 'Hiba a szétkapcsoláskor. Próbáld meg újra.',
            ]            
        ],
        'child' => [
            'help' => 'Tájékoztatás: A hírnököket a(z) :groupName csoportból másoljuk át. Ott tudsz új hírnököt hozzáadni vagy törölni.',
            'info' => 'Ennek a csoportnak a tagjait az alábbi főcsoportból másolja át automatikusan a rendszer. Ha megszűnteted a kapcsolatot, a csoportok tagjai nem fognak törlődni sehol, viszont ezentúl nem kerül átmásolásra ebbe a csoportba senki, akit felvesznek abba a csoportba.',
            'copy_data' => 'Beállíthatod, hogy a hírnököknek mely adatát másolja még át a főcsoportból. A mentés után automatikusan megtörténik az átmásolás és innentől itt nem fogod tudni módosítani ezeket a mezőket.',
            'parent_group_name' => 'Főcsoport neve',
            'detach' => [
                'button' => 'Szétkapcsolás',
                'question' => 'Valóban szétkapcsolod a két csoportot?',
                'message' => 'Ezt követően a csoport tagjai nem fognak átmásolódni ide.',
                'success' => 'Sikeres szétkapcsolás!',
                'error' => 'Hiba a szétkapcsoláskor. Próbáld meg újra.',
            ]            
        ],
    ],
    'main-group' => 'Főcsoport',
    'sub-group' => 'Alcsoport',
    'sub-group-alert' => 'Ezt itt nem módosíthatod, mert beállítottad, hogy ezt a paramétert a főcsoportból (:groupName) vegye át. Ott tudsz rajta módosítani.',
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
    'days_info' => 'Ha már van valakinek betervezve olyan szolgálata, ami kívül esik az új időponttól, akkor az módosítva vagy törölve lesz attól függően, hogy belefér e az új időtartamba vagy sem. A régi szolgálati napokat és a különleges napokat ez nem érinti.',
    'special_dates' => [
        'title' => 'Különleges napok',
        'info' => 'Itt különleges napokat adhatsz meg, amikor valamiért eltér a szolgálat ideje attól ahogy egyébként lenni szokott, vagy le is tilthatsz adott napot, hogy aznapra ne lehessen szolgálatot betervezni. Ha aznapra már valaki betervezett szolgálatot, akkor mentés után a rendszer automatikusan ellenőrzi, hogy belefér e a megadott időtartamba. Ha nem, akkor módosítja/törli a szolgálatot.',
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
        'under_edit' => 'Szerkesztés alatt a fenti űrlapon!',
        'confirmDelete' => [
            'question' => 'Biztosan törlöd ezt a napot?',
            'message' => 'Ha törlöd, akkor minden erre a napra mentett szolgálat módosítva/törölve lesz, attól függően, hogy miként érinti a módosítás.',
            'success' => 'Az adott nap törölve lett.',
            'error' => 'Sikertelen törlés!'
        ],
        'no_special_dates' => 'Nincsenek különleges napok ebben a hónapban.',
        'saved' => 'A különleges nap elmentve.',
    ],
    'min' => 'min',
    'max' => 'max',
    'service' => 'Szolgálat',
    'service_publishers' => 'Minimum :min, maximum :max hírnök',
    'service_time' => 'Minimum :min, maximum :max szolgálat',
    'signs' => [
        'title' => 'Speciális jelek',
        'info' => 'Az alábbi jeleket választhatják a hírnökök ennél a csoportnál (Nem kötelező használni). Ez segíthet, hogy átlásd, kinek van pl kulcsa a teremhez, vagy autója. A megnevezést testreszabhatod, látni fogják a hírnökök.',
        'name' => 'Megnevezés',
        'change' => 'Módosíthatja a hírnök?',
        'success' => 'Sikeresen módosult!',
        'error' => 'Nem vagy jogosult módosítani.'
    ],
    'poster' => [
        'button' => 'Infó',
        'title' => 'Aktuális információk',
        'info' => 'Az aktuális információk látszódnak a csoport naptáránál és az adott napot megnyitva, az oldal tetején, azoknál a napoknál amit érint.',
        'field_info' => 'Információ',
        'field_show_date' => 'Mikortól látszódjon?',
        'show_date_helpBlock' => 'Ekkortól fog látszódni a csoportnál. Kötelező megadni.',
        'field_hide_date' => 'Meddig látszódjon?',
        'hide_date_helpBlock' => 'Eddig fog megjelenni. A beállított nap éjféléig látszódik. Ha nem adsz meg semmit, akkor addig fog látszódni, amíg be nem állítasz valamit.',
        'success' => 'Az információ elmentve!',
        'until_revoked' => 'visszavonásig',
        'confirmDelete' => [
            'question' => 'Biztosan törlöd ezt az információt?',
            'message' => 'Törlés után már nem fog látszódni sehol a tartalma',
            'success' => 'Az információ törölve lett.',
            'error' => 'Sikertelen törlés!'
        ],
        'i_have_read' => 'Elolvastam',
    ],
    'filter' => [
        'title' => 'Szűrés',
        'myself' => 'Magamra',
        'off_all' => 'Minden szűrő ki',
    ],
    'update' => [
        'from' => 'Módosítás életbelépésének dátuma',
        'info' => 'A fenti módosítások a megadott naptól lesznek érvényesek. Vedd figyelembe, hogy ha későbbi dátumot adsz meg mint a mai nap, akkor új módosítást már nem fogsz tudni beállítani addig a napig.',
        'wrong_date' => 'Nem adhatsz meg korábbi dátumot mint a mai nap',
        'in_progress' => 'Egy jövőbeli módosítás már el lett mentve. Addig nem módosíthatod az aktuális adatokat, amíg az életbe nem lép. A változtatás ekkor fog életbe lépni: :dateFrom. A változtatást :userName kezdeményezte, vele egyeztess kérlek.',
        'in_progress_show' => 'Ide kattintva megtekintheted a tervezett módosítást.',
        'in_progress_delete' => 'Törlöm a jövőbeli változtatást',
        'in_progress_delete_confirm' => [
            'question' => 'Biztosan törlöd ezt a jövőbeli változtatást?',
            'message' => 'A betervezett szolgálatok felül lesznek bírálva és módosítja/törli a rendszer.',
            'success' => 'A jövőbeli változtatás törölve lett.',
            'error' => 'Sikertelen törlés!'
        ],
    ],
    'messages' => [
        'title' => 'Üzenőfal',
        'type' => 'Üzenet írása',
        'urgent' => 'Sürgős',
        'urgent_info' => 'Sürgős esetben a csoportszolgák és csoportfelvigyázók emailben is megkapják az üzenetedet. Kérjük csak tényleg sürgős esetben használd ezt a funkciót.',
        'no_messages' => 'Nincsenek üzenetek.',
        'info' => 'Az üzenőfal azoknak érhető el, akiknek szolgálatuk lesz a következő 24 órában, vagy szolgálatuk volt az elmúlt 2 órában. ',
        'be_short' => 'Ha üzenetet írsz, kérjük fogalmazz röviden és csak a lényeges információkat írd le.',
        'limit' => 'Túl sok próbálkozás, kérlek várj.',
        'deleted' => 'Törölve.',
        'cant_write' => 'Jelenleg nem küldhetsz üzenetet.',
        'admin' => [
            'info' => 'Ha bekapcsolod, akkor a Főoldalon az adott csoportnál elérhető lesz az üzenőfal funkció. A Hírnökök menüben további jogosultságot tudsz adni majd egyes hírnököknek, illetve meg is tilthatod, hogy valaki üzenetet tudjon írni (ettől még látni fogja az üzeneteket). Az üzeneteket maximum 7 napig őrizzük, utána törli a rendszer.',
            'activate' => 'Bekapcsolod ezt a funkciót?',
            'who_can_write' => 'Ki írhat üzenetet?',
            'anyone' => 'Bármely hírnök a csoportban',
            'authorized_only' => 'Csak akinek jogosultságot adunk',
            'priority' => 'Szeretnéd használni a sürgős üzenet funkciót?',
            'priority_info' => 'Igen esetén az üzenet írásakor megadható, hogy egy üzenet sürgős, ekkor a csoportszolgák és a jogosultsággal rendelkezők értesítést kapnak emailben az adott üzenetről.',
        ],
        'user' => [
            'title' => 'Üzenőfal beállítások',
            'when_use' => 'Mikor használhatja az üzenőfalat?',
            'default' => 'Amikor szolgálata van',
            'cant_write' => 'Nem írhat az üzenőfalra, csak láthatja',
            'can_write' => 'Bármikor írhat az üzenőfalra',            
            'catch_urgent' => 'Továbbítsd neki sürgős üzeneteket',
            
        ]
    ]
];
