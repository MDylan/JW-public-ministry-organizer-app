<?php

namespace Database\Seeders;

use App\Models\StaticPage;
use App\Models\User;
use Illuminate\Database\Seeder;

class StaticPagesSetupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * A telepítő a Setup\AccountController-ből callWith()-szel adja át a
     * tulajdonos azonosítóját. A paraméter opcionális, hogy a seeder
     * `artisan db:seed --class=StaticPagesSetupSeeder` alakban is
     * futtatható legyen; ilyenkor az első mainAdmin lesz a tulajdonos.
     *
     * @param  int|null  $user_id
     * @return void
     */
    public function run($user_id = null)
    {
        $user_id = $user_id ?? User::where('role', 'mainAdmin')->value('id');

        if ($user_id === null) {
            $this->command?->warn('StaticPagesSetupSeeder skipped: no mainAdmin user exists to own the pages.');

            return;
        }

        $lang = env('APP_LANG', 'en');
        $home = [
            'status' => 2,
            'slug' => 'home',
            'position' => 'hidden',
            'user_id' => $user_id,
            $lang => [
                'title' => 'Home',
                'content' => 'This is the default home page. You can edit this at Administration / Static pages menu',
            ]
        ];
        StaticPage::create($home);

        $contact = [
            'status' => 1,
            'slug' => 'contact',
            'position' => 'bottom',
            'user_id' => $user_id,
            $lang => [
                'title' => 'Contact page',
                'content' => 'This is the default contact page. You can edit this at Administration / Static pages menu',
            ]
        ];
        StaticPage::create($contact);

        $terms = [
            'status' => 1,
            'slug' => 'terms',
            'position' => 'bottom',
            'user_id' => $user_id,
            $lang => [
                'title' => 'Terms page',
                'content' => 'This is the default terms & conditions page. You can edit this at Administration / Static pages menu',
            ]
        ];
        StaticPage::create($terms);

        $help = [
            'status' => 3,
            'slug' => 'help',
            'position' => 'bottom',
            'user_id' => $user_id,
            $lang => [
                'title' => 'Help page',
                'content' => 'This is the default help page. You can edit this at Administration / Static pages menu',
            ]
        ];
        StaticPage::create($help);
    }
}
