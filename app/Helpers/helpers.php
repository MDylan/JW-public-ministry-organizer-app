<?php

use App\Models\Group;
use Illuminate\Support\Facades\DB;

if(!function_exists('pwbs_poster_set_read')) {
    function pwbs_poster_set_read(int $poster_id) {
        //toggle read state
        $res = DB::table('group_poster_reads')->where('user_id', auth()->id())->where('poster_id', $poster_id);
        if($res->count() == 1) {
            $res->delete();
        } else {
            DB::table('group_poster_reads')->insert([
                'user_id' => auth()->id(),
                'poster_id' => $poster_id
            ]);
        }
    }
}

if(!function_exists('pwbs_check_group_other_admins')) {
    function pwbs_check_group_other_admins(int $groupId, int $userId) {
        $group = Group::findOrFail($groupId);
        // Utódnak csak nem anonimizált, elfogadott admin számít - lásd a
        // Group::activeAdmins() indoklását (TODO 12.2).
        $users = $group->activeAdmins()->get()->toArray();
        $total_group = 1;
        $admins = 0;
        $main_admins = [];
        $user_is_admin = false;
        foreach($users as $user) {
            if($user['id'] != $userId) {
                $admins++;
                $main_admins[$user['id']] = 1;
            } else {
                $user_is_admin = true;
            }
        }
        //check child groups too!
        $child_groups = $group->childGroups()->with('activeAdmins')->get()->toArray();
        if(count($child_groups) > 0) {
            $total_group += count($child_groups);
            foreach($child_groups as $child_group) {
                foreach($child_group['active_admins'] as $user) {
                    if($user['id'] != $userId) {
                        $admins++;
                        if(!isset($main_admins[$user['id']])) 
                            $main_admins[$user['id']] = 1;
                        else $main_admins[$user['id']]++;
                    } else {
                        $user_is_admin = true;
                    }
                }
            }
        }

        //he is not admin, so he can quit
        if($user_is_admin === false) return true;

        return (array_search($total_group, $main_admins, true) === false) ? false : true;
    }
}

if(!function_exists('pwbs_get_newsletter_roles')) {
    function pwbs_get_newsletter_roles() {
        $in = [];

        // A gate neve 'is-groupcreator', csupa kisbetűvel (AuthServiceProvider.php:37).
        // Itt korábban 'is-groupCreator' állt, és mivel a Laravel a gate-eket
        // kulcs szerinti tömbben tartja, a nevek kis-nagybetű érzékenyek: a
        // feltétel MINDIG hamis volt, tehát egy sima groupCreator soha nem kapta
        // meg a neki címzett hírleveleket - csak a mainAdmin jutott át az
        // is-admin ágon.
        if(auth()->user()->can('is-groupcreator') || auth()->user()->can('is-admin')) {
            //create group
            $in[] = 'groupCreators';
        } 
        if(auth()->user()->can('is-groupservant')) {
            $in[] = 'groupServants';            
        }
        if(auth()->user()->can('is-groupadmin')) {
            $in[] = 'groupAdmins';            
        }
        return $in;
    }
}

if(!function_exists('pwbs_weather_api_call')) {
    /**
     * Egy település időjárása, gyorsítótárral.
     *
     * A törzs a v1-patch C csomagjában az App\Support\Weather\WeatherCache
     * osztályba költözött (TODO 33.6). Ez a függvény szándékosan megmaradt
     * átjárónak, hogy a két Livewire hívási hely - Groups\UpdateGroupForm
     * :215 és :524 - ne mozduljon ugyanabban a változtatásban.
     *
     * A visszatérési szerződés változatlan: 'city_id' plusz vagy
     * 'current_weather' + 'forecast_weather', vagy 'error'.
     */
    function pwbs_weather_api_call(string $city, string $country) {
        if(config('weather') != 1) return;

        return app(\App\Support\Weather\WeatherCache::class)->forCity($city, $country);
    }
}

if(!function_exists('pwbs_asset')) {
    /**
     * Egy public/ alatti statikus fájl URL-je, cache-busting tokennel.
     *
     * Ez váltja az `eusonlito/laravel-packer`-t (TODO 21 döntése, TODO 33.8
     * végrehajtása). A csomag egyetlen valóban hasznos szolgáltatása a cache
     * busting volt, ami egy `filemtime()` hívás - minden más, amit csinált,
     * kárt okozott:
     *
     *  - Minden `url(`-t abszolúttá írt át, és ezzel 181 beágyazott `data:`
     *    URI-t tett tönkre (177 az adminlte.min.css-ben, 4 a toastr-ben).
     *  - Az abszolút URL-be a GENERÁLÓ kérés sémája sült bele, a fájl pedig
     *    korlátlanul újrahasznosult - egy http alatt készült CSS https-en
     *    mixed contentet okozott, és soha nem gyógyult meg magától.
     *  - Kérés közben írt a webgyökérbe, `local` kivételével minden
     *    környezetben - a tesztfutás is, ami a böngészőnek kiszolgált
     *    fájlokat írta felül.
     *
     * Ez a függvény egyiket sem tudja megtenni: nem ír lemezre, nem nyúl a
     * fájl tartalmához, és az URL-t kérésenként az `asset()`-ből számolja,
     * tehát a séma mindig az aktuális kérésé.
     *
     * Hiányzó fájlnál nem dob hibát, csak token nélküli URL-t ad - egy
     * elgépelt útvonal ne öljön meg egy oldalt.
     */
    function pwbs_asset(string $path): string {
        $file = public_path(ltrim($path, '/'));

        return is_file($file)
            ? asset($path).'?v='.filemtime($file)
            : asset($path);
    }
}
