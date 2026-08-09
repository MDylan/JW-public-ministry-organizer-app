<?php

return [
    'max_columns' => 4,
    'default_colors' => [
        'color_default' => '#cecece',
        'color_empty' => '#10FF98',
        'color_someone' => '#1259B2',
        'color_minimum' => '#ffff00',
        'color_maximum' => '#ff0000',
    ],
    //calendars used on profile page.
    //this functions need to be exists in spatie/calendar-links
    //see: https://github.com/spatie/calendar-links
    'calendars' => [
        'google',
        'yahoo',
        'webOutlook',
        'webOffice',
        'ics'
    ],
    'github_url' => 'https://github.com/MDylan/JW-public-ministry-organizer-app',
    // A 'use_recaptcha' kulcs innen a config/security.php-ba költözött (TODO 28):
    // ebben a fájlban soha semmi nem olvasta, a naptár konfigurációja között
    // állt, a kód pedig közvetlenül env()-ből dolgozott helyette.
];