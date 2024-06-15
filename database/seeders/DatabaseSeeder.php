<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run()
    {

        \App\Models\User::updateOrCreate(['email' => 'admin'],[
            'name' => 'Admin',
            'password' => bcrypt('admin'),
        ]);
         \App\Models\Client::factory(15)->create();
    }
}
