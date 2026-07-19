<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class admin extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        # craete admin user
        $admin = new \App\Models\User();
        $admin->name = 'admin';
        $admin->email = 'adminn@localhost';
        $admin->password = \Hash::make(value: 'ksjnndw)7u0?939?993829');
        $admin->save();


        # create 10 products
//        \App\Models\Product::factory()->count(100)->create();

    }
}
