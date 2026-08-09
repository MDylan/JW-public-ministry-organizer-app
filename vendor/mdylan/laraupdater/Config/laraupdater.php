<?php
/*
* @author: Pietro Cinaglia
* 	.website: http://linkedin.com/in/pietrocinaglia
*
* Fork maintained by David Molnar (https://github.com/MDylan/laraupdater).
*/

return [

    /*
    * Temp folder to store the update before installing it.
    * Resolved against base_path(), so '/tmp' means <project root>/tmp.
    * The directory is created on first use if it does not exist.
    */
    'tmp_path' => '/tmp',

    /*
    * URL where your updates are stored ( e.g. for a folder named 'updates', under http://site.com/yourapp ).
    * A plain local directory path also works, which is what the test suite uses.
    */
    'update_baseurl' => 'http://site.com/yourapp/updates',

    /*
    * Middleware stack for ALL THREE updater routes (check, currentVersion, update).
    * Since v2 the read-only endpoints are guarded too, so this stack is the only
    * thing standing in front of them - put a role/gate check in it, e.g.
    * ['web', 'auth', 'can:is-admin'].
    */
    'middleware' => ['web', 'auth'],

    /*
    * Set which users can perform an update;
    * This parameter accepts: ARRAY(user_id) ,or FALSE => for example: [1]  OR  [1,3,0]  OR  false
    * Generally, ADMIN have user_id=1; set FALSE when the middleware stack above
    * already gates on a role.
    */
    'allow_users_id' => [1],

    /*
    * Run `php artisan migrate --force` after a successful install.
    */
    'migrate' => true,

    /*
    * Set the update check time period, to prevent update server overload.
    * Set the number in minutes.
    */
    'version_check_time' => 15,

    /*
    * Seconds to wait for the manifest (laraupdater.json). check() runs on every
    * admin page render once the cache expires, so this bounds how long a silent
    * update server can block a page load.
    */
    'request_timeout' => 10,

    /*
    * Seconds to wait for the release archive. This is a per-read timeout, so a
    * slow but progressing download is not cut off.
    */
    'download_timeout' => 60,
];
