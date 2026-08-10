<?php
/*
* @author: Pietro Cinaglia
* 	.website: http://linkedin.com/in/pietrocinaglia
*/
	 
	return [

		/*
		* Temp folder to store update before to install it.
		*/
		'tmp_path' => '/tmp',

		/*
		* URL where your updates are stored ( e.g. for a folder named 'updates', under http://site.com/yourapp ).
		*/
		'update_baseurl' => env('UPDATE_CHANNEL', 'https://updates.teruletek.hu/v1'),

		/*
		* Set a middleware for the route: updater.update
		* Only 'auth' NOT works (manage security using 'allow_users_id' configuration)
		*
		* EnsureUpdateWithinBranch sits at the END of the list, and this is
		* deliberate: the major-version-limit check queries the channel,
		* which should only run after auth and can:is-admin have already let
		* the request through. This guard keeps the "never auto-cross a
		* major version" rule even if someone opens /updater.update directly -
		* see App\Support\Updates\UpdateBranch.
		*
		* WARNING: a `vendor:publish --force --tag=laraupdater` overwrites
		* this file, silently turning automatic major-version jumps back on
		* (and it would also lose the update_baseurl customization). If
		* publishing is needed, it has to be merged by hand.
		*/
		'middleware' => ['web', 'auth', 'can:is-admin', \App\Http\Middleware\EnsureUpdateWithinBranch::class],

		/*
		* Set which users can perform an update; 
		* This parameter accepts: ARRAY(user_id) ,or FALSE => for example: [1]  OR  [1,3,0]  OR  false
		* Generally, ADMIN have user_id=1; set FALSE to disable this check (not recommended)
		*/
		'allow_users_id' => false,
				
		 /*
		*  Laravel migrate settings
		*/
		'migrate' => true,
		/*
		* Set the update check time period, to prevent update server overload.
		* Set the number in minutes
		*/
		'version_check_time' => 15,
	];
