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
        // Only a non-anonymized, accepted admin counts as a successor - see the
        // rationale for Group::activeAdmins() (TODO 12.2).
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

        // The gate name is 'is-groupcreator', all lowercase (AuthServiceProvider.php:37).
        // Here it used to say 'is-groupCreator', and since Laravel keeps
        // gates in an array keyed by name, the names are case-sensitive: the
        // condition was ALWAYS false, so a plain groupCreator never got
        // the newsletters addressed to them - only the mainAdmin got through the
        // is-admin branch.
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
     * The weather for a given city, with caching.
     *
     * The body moved into the App\Support\Weather\WeatherCache class in the
     * v1-patch C package (TODO 33.6). This function deliberately remains as a
     * pass-through, so the two Livewire call sites - Groups\UpdateGroupForm
     * :215 and :524 - don't have to move in the same change.
     *
     * The return contract is unchanged: 'city_id' plus either
     * 'current_weather' + 'forecast_weather', or 'error'.
     */
    function pwbs_weather_api_call(string $city, string $country) {
        if(config('weather') != 1) return;

        return app(\App\Support\Weather\WeatherCache::class)->forCity($city, $country);
    }
}

if(!function_exists('pwbs_asset')) {
    /**
     * The URL of a static file under public/, with a cache-busting token.
     *
     * This replaces `eusonlito/laravel-packer` (TODO 21 decision, TODO 33.8
     * execution). The package's only genuinely useful service was cache
     * busting, which is a single `filemtime()` call - everything else it did
     * caused harm:
     *
     *  - It rewrote every `url(` to be absolute, and in doing so broke 181
     *    embedded `data:` URIs (177 in adminlte.min.css, 4 in toastr).
     *  - The absolute URL baked in the scheme of the GENERATING request, and
     *    the file was then reused without limit - CSS generated under http
     *    caused mixed content on https, and it never healed itself.
     *  - It wrote into the web root mid-request, in every environment except
     *    `local` - including the test run, which overwrote the
     *    files served to the browser.
     *
     * This function can do none of that: it doesn't write to disk, doesn't touch
     * the file's contents, and computes the URL per request from `asset()`,
     * so the scheme is always that of the current request.
     *
     * On a missing file it doesn't throw, it just returns a URL without a token - a
     * mistyped path shouldn't kill a page.
     */
    function pwbs_asset(string $path): string {
        $file = public_path(ltrim($path, '/'));

        return is_file($file)
            ? asset($path).'?v='.filemtime($file)
            : asset($path);
    }
}
