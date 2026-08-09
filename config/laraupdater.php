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
		* Az EnsureUpdateWithinBranch a sor VÉGÉN áll, és ez szándékos: a
		* major-korlát ellenőrzése a csatornát kérdezi meg, ami csak azután
		* fusson le, hogy az auth és a can:is-admin már átengedte a kérést.
		* Ez a guard tartja meg a "major verziót automatikusan nem lépünk át"
		* szabályt akkor is, ha valaki közvetlenül nyitja meg az /updater.update
		* címet - lásd App\Support\Updates\UpdateBranch.
		*
		* FIGYELEM: egy `vendor:publish --force --tag=laraupdater` ezt a fájlt
		* felülírja, és ezzel némán visszakapcsolja az automatikus major-ugrást
		* (ahogy az update_baseurl testreszabását is elveszítené). Ha publikálni
		* kell, kézzel kell összefésülni.
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
