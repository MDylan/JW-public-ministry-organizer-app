<?php

//ezeket a jogokat oszthatja ki a rendszer a felhasználóknak

return [
    'registered' => 'Regisztrált',          //alapértelmezett, nem tud semmit csinálni
    'activated' => 'Aktivált',              //megerősítette az email címét, de még nem tagja semmilyen csoportnak
    'groupMember' => 'Csoporttag',          //valamilyen csoportnak a tagja
    'groupCreator' => 'Csoport létrehozó',  //létre tud hozni további csoportokat
    'mainAdmin' => 'Adminisztrátor'         //fő admin, ilyen csak pár embernek lehet
];
