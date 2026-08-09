<?php

namespace App\Http\Controllers;

use App\Models\GroupNewsFile;
use Illuminate\Support\Facades\Storage;

class GroupNewsFileDownloadController extends Controller
{
    /**
     * Csoporthír mellékletének letöltése.
     *
     * A `groupMember` middleware KIZÁRÓLAG az útvonal `{group}` paraméterét
     * nézi (App\Http\Middleware\GroupMember): azt igazolja, hogy a kérő tagja
     * ANNAK a csoportnak. A `{file}` viszont puszta azonosító szerint kötődik
     * modellhez, és a controller korábban nem ellenőrizte, hogy a fájl ahhoz a
     * csoporthoz tartozik-e.
     *
     * Következmény: bármely csoport bármely elfogadott tagja letölthette
     * BÁRMELY másik csoport privát hírmellékletét - elég volt a saját
     * csoportazonosítóját és egy idegen fájlazonosítót megadni. A fájlok a
     * `news_files` privát diszken ülnek, a docrooton kívül, tehát ez volt az
     * egyetlen út hozzájuk - és nyitva állt.
     *
     * A válasz 404, nem 403: egy 403 megerősítené, hogy az adott azonosítójú
     * fájl létezik.
     */
    public function download($group, GroupNewsFile $file)
    {
        $new = $file->new()->first();

        if ($new === null || (string) $new->group_id !== (string) $group) {
            abort(404);
        }

        if (Storage::disk('news_files')->exists($file->file)) {
            return Storage::disk('news_files')->download($file->file, $file->name);
        } else {
            return abort('404');
        }
    }
}
