<?php

namespace Database\Seeders;

use App\Models\StaticPage;
use Illuminate\Database\Seeder;

class StaticPagesSetupSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $lang = env('APP_LANG', 'en');
        $home = [
            'status' => 2,
            'slug' => 'home',
            'position' => 'hidden',
            'user_id' => 1,
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
            'user_id' => 1,
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
            'user_id' => 1,
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
            'user_id' => 1,
            $lang => [
                'title' => 'Help page',
                'content' => 'This is the default help page. You can edit this at Administration / Static pages menu',
            ]
        ];
        StaticPage::create($help);
    }
}
