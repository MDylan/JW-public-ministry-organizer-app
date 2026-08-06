<?php

use App\Models\Group;
use App\Models\Settings;
use App\Models\WeatherCity;
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

if(!function_exists('pwbs_check_group_admins')) {
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

        if(auth()->user()->can('is-groupCreator') || auth()->user()->can('is-admin')) {
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
    function pwbs_weather_api_call(string $city, string $country) {
        if(config('weather') != 1) return;
        
        $city = ucfirst(trim($city));
        $country = strtoupper(trim($country));

        $last_get = WeatherCity::where('city', $city)->where('country', $country)->first();
        // dd($last_get);
        if(!empty($last_get) && $last_get->updated_at > now()->subMinutes(59)) {
            return [
                'city_id' => $last_get->id,
                'current_weather' => json_decode( $last_get->current_weather, true ),
                'forecast_weather' => json_decode($last_get->forecast_weather, true)
            ];
        } else {
            if(!empty($last_get)) {
                if($last_get->last_try > now()->subMinutes(15)) {
                    return [
                        'city_id' => $last_get->id,
                        'error' => __('group.weather.too_many_requests')
                    ];
                }
            }
            //get weather info
            
            try {
                $wt = new \RakibDevs\Weather\Weather();
                $f_weather = $wt->get3HourlyByCity($city, $country);
                $c_weather = $wt->getCurrentByCity($city . ", " . $country);

                if (!empty($c_weather) && !empty($f_weather)) {
                    $current_weather = json_encode((array) $c_weather);
                    $forecast_weather = json_encode((array) $f_weather);

                    $res = WeatherCity::updateOrCreate(
                        ['city' => $city, 'country' => $country],
                        [
                            'current_weather' => $current_weather,
                            'forecast_weather' => $forecast_weather,
                            'last_try' => now()
                        ]
                    );
                    // dd("na");
                    // dd($weather_data);
                    //update monthly usage value
                    Settings::updateOrCreate(
                        [
                            'name' => 'weather_monthly_call'
                        ],
                        [
                            'value' => DB::raw("IF(ISNULL(value + 2),2,value+2)"),
                        ]
                    );
                    return [
                        'city_id' => $res->id,
                        'current_weather' => json_decode($current_weather, true),
                        'forecast_weather' => json_decode($forecast_weather, true)
                    ];
                } else {
                    //I will update the last try time, for avoid too many requests
                    $res = WeatherCity::updateOrCreate(
                        ['city' => $city, 'country' => $country],
                        [
                            'last_try' => now()
                        ]
                    );
                    return [
                        'city_id' => $res->id,
                        'error' => __('group.weather.no_data')
                    ];
                }
            } catch (Exception $e) {
                return [
                    'error' => $e->getMessage()
                ];
            }
            
        }
    }
}