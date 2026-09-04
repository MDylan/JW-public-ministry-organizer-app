<?php 

return [
    'choose_group' => 'Válassz csoportot',
    'switch' => 'Váltás',
    'weekdays_short' => [
        '1' => 'Hé',
        '2' => 'Ke',
        '3' => 'Sze',
        '4' => 'Csü',
        '5' => 'Pé',
        '6' => 'Szo',
        '0' => 'Va'
    ],
    'modal' => [
        'tab_events' => 'Időpontok',
        'tab_set_event' => 'Időpont foglalása'
    ],
    'service_start' => 'Szolgálatod kezdete',
    'service_end' => 'Szolgálatod befejezése',
    'choose_time' => 'Válassz időpontot',
    'save' => 'Időpont mentése',
    'saved' => 'A szolgálatodat elmentettük.',
    'create_event' => 'Válassz időpontot, amikor szolgálnál',
    'edit_event' => 'Szolgálat szerkesztése',
    'save_changes' => 'Módosítások mentése',
    'cancel_edit' => 'Mégsem módosítok',
    'delete_event' => 'Szolgálat törlése',
    'need_approval' => 'Fontos: A tervezett szolgálatot jóvá kell hagynia a csoportszolgának.',
    'approval_info' => 'Engedélyezés',
    'approved' => 'Jóváhagyva',
    'eventsBar' => [
        'title' => 'Közelgő szolgálataid',
        'no_events' => 'Nincsenek betervezett szolgálataid'
    ],
    'confirmDelete' => [
        'question' => 'Biztosan törlöd a szolgálatot?',
        'message' => 'Ez nem vonható vissza.',
        'success' => 'Sikeresen törölted a szolgálatot.',
        'error' => 'Hiba a törlés során'
    ],
    'reach_max_publisher' => 'Ebben az idősávban már elértük a maximális hírnökszámot. Válassz más időpontot.',
    'invalid_value' => 'Hibás időpont érték lett megadva.',
    'publisher' => 'Hírnök',
    'choose_publisher' => 'Válassz hírnököt',
    'please_wait' => 'Kérlek várj...',
    'error' => [
        'invalid_group' => 'Hibás csoport lett kiválasztva, vagy nincs jogosultságod ezt szerkeszteni.',
        'no_permission' => 'Ehhez nincs jogosultságod.',
        'no_service_day' => 'Ezen a napon nem lehetséges a szolgálat.',
        'publisher_busy' => 'Ekkor már szolgálatban vagy egy másik csoportban. (:start - :end)',
    ],
    'paralel_events' => 'A megadott időpontokban már az alábbi csoportban is jelentkeztél szolgálatra. Ha ezt jóváhagyják akkor a másik jelentkezés automatikusan elutasításra kerül, és fordítva.',
    'date' => 'Dátum',
    'status' => 'Állapot',
    'status_0' => 'Jóváhagyásra vár!',
    'status_1' => 'Jóváhagyva',
    'status_2' => 'Elutasítva',
    'no_last_events' => 'Ebben a hónapban nem végeztél még szolgálatot.',
    'event_time' => 'Szolgálati időszak',
    'service' => [
        'placements' => 'Terjesztések',
        'language' => 'Nyelv',
        'videos'    => 'Videó',
        'return_visits' => 'Újralátogatás',
        'bible_studies' => 'Bt bevezetés',
        'note' => 'Megjegyzés',
        'no_placements' => 'Nem volt terjesztés',
        'success' => 'A terjesztéseket elmentettük.',
        'error' => 'Ehhez az eseményhez nem rögzíthetsz terjesztést.',
    ],
    'comment' => [
        'label' => 'Megjegyzés',
        'helper' => 'Megjegyzés a szolgálatodhoz. Mindenki látja, kérjük fogalmazz röviden.'
    ],
    'bulk' => [
        'button' => 'Tömeges elfogadás / elutasítás',
        'help' => 'Kattintással válaszd ki, amit elutasítanál vagy elfogadnál, majd kattints a megfelelő gombra. Ismételt kattintásra visszavonja a kijelölést.',
        'accept' => 'A kijelöltek elfogadása',
        'acccept_done' => 'A kijelölt szolgálatokat jóváhagytad',
        'reject' => 'A kijelöltek elutasítása',
        'confirmReject' => [
            'question' => 'Biztosan elutasítod a kijelölt szolgálatokat?',
            'message' => 'Összesen :number darab.',
            'success' => 'Sikeresen elutasítottad a szolgálatot.',
        ],
        'cancel' => 'Mégsem módosítok semmit',
        'error' => 'Nem volt mit elfogadni vagy elutasítani!',        

    ],
];