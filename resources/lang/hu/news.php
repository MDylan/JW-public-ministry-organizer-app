<?php

return [
    'no_news' => 'Jelenleg nincsenek csoport hírek',
    'create' => 'Hír létrehozása',
    'edit' => 'Hír szerkesztése',
    'title' => 'Cím',
    'status' => 'Állapot',
    'date' => 'Megjelenés dátuma',
    'date_helper' => 'Ez előtt nem fog megjelenni csak a szerkesztőknek.',
    'content' => 'Tartalom',
    /*
    editor_lang is based on /public/plugins/summernote/lang/summernote-{LANG}.min.js This file must extists!
    If you set this: 'editor_lang' => null, default english will used
    */
    'editor_lang' => 'hu-HU', 
    'statuses' => [
        0 => 'Vázlat (nem fog megjelenni)',
        1 => 'Aktív (látható a dátum után)',
    ],
    'created' => 'A hír létrejött!',
    'edited'  => 'Sikeres szerkesztés!',
    'future'  => 'A jövőben lesz látható.',
    'delete' => 'Hír törlése',
    'confirmDelete' => [
        'question' => 'Biztosan törlöd ezt a hírt?',
        'message' => 'A művelet nem vonható vissza, és a csatolt fájlok is törölve lesznek.',
        'success' => 'A hír törölve lett.',
        'error' => 'Sikertelen törlés!'
    ],
    'attachments' => 'Csatolt fájlok',
    'file' => [
        'browse' => 'Tallózás',
        'choose' => 'Fájlok feltöltése',
        'uploaded' => 'Feltöltött fájlok:',
        'available_types' => 'Engedélyezett formátumok',
        'wrong_type'    => 'Hibás fájlformátum',
        'wrong_size'    => 'Túl nagy a fájl mérete. Maximum 2 MB tölthető fel.'
    ],
    'confirmFileDelete' => [
        'question' => 'Biztosan törlöd ezt a fájlt?'
    ]
];