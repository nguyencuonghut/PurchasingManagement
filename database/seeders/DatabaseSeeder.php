<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::factory(100)->create();

        User::factory()->create([
            'name' => 'Tony Nguyen',
            'email' => 'nguyenvancuong@honghafeed.com.vn',
            'password' => bcrypt('Hongha@123'),
            'status' => 'On',
            'role' => 'Quản trị',
        ]);
    }
}
