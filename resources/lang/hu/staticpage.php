<?php

return [
    'create_new' => 'Új oldal létrehozása',
    'edit'  => 'Oldal szerkesztése',
    'title' => 'Oldal címe',
    'content' => 'Tartalom',
    'status' => 'Állapot',
    'statuses' => [
        0 => 'Vázlat (csak admin láthatja)',
        1 => 'Nyilvános (bárki láthatja)',
        2 => 'Csak nyilványos (csak kilépve látható)',
        3 => 'Zárt (csak belépett felhasználó láthatja)'
    ],
    'statuses_sort' => [
        0 => 'Vázlat',
        1 => 'Nyilvános',
        2 => 'Csak nyilvános',
        3 => 'Zárt'
    ],
    'slug'  => 'Slug (URL hivatkozás)',
    'slugHelp' => 'Egyedi kell legyen, szóköz nélkül. Az angol ABC betűit tartalmazhatja csak.',
    'position' => 'Link elhelyezés',
    'positions' => [
        'left' => 'Bal menü',
        'bottom' => 'Alsó menü',
        'hidden' => 'Egyik sem',
    ],
    'positions_helper' => 'A nyilvánosan is elérhető linkek "Bal menü" esetén az oldal tetején fognak megjelenni ha nincs belépve a látogató.',
    'icon' => 'Ikon',
    'iconHelp' => 'A teljes ikonkészlet <a href="https://fontawesome.com/v5.15/icons" target="_blank">megtekinthető itt</a>. 
                    Neked a "class" után lévő idézőjelek közötti részt kell kimásolnod ide.<br/>
                    Például: &lt;i class="fas fa-address-book"&gt;&lt;/i&gt; esetén a <strong>fas fa-address-book</strong> szükséges.',
    'created' => 'Az oldal létrejött.',
    'edited'  => 'Sikeres szerkesztés',
    'delete' => 'Oldal törlése',
    'confirmDelete' => [
        'question' => 'Biztosan törlöd ezt az oldalt?',
        'message' => 'A művelet nem vonható vissza!',
        'success' => 'Az oldal törölve lett.',
        'error' => 'Sikertelen törlés!'
    ],
];