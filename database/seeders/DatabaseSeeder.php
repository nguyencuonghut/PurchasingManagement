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
        //User::factory(100)->create();

        User::factory()->create([
            'name' => 'Tony Nguyen',
            'email' => 'nguyenvancuong@honghafeed.com.vn',
            'password' => bcrypt('Hongha@123'),
            'status' => 'On',
            'role' => 'Quản trị',
        ]);
        User::factory()->create([
            'name' => 'Phạm Thị Trang',
            'email' => 'nvtm@honghafeed.com.vn',
            'password' => bcrypt('Hongha@123'),
            'status' => 'On',
            'role' => 'Nhân viên Thu Mua',
        ]);
        User::factory()->create([
            'name' => 'Bùi Thị Nụ',
            'email' => 'nvks@honghafeed.com.vn',
            'password' => bcrypt('Hongha@123'),
            'status' => 'On',
            'role' => 'Nhân viên Kiểm Soát',
        ]);
        User::factory()->create([
            'name' => 'Lê Thị Hồng',
            'email' => 'tptm@honghafeed.com.vn',
            'password' => bcrypt('Hongha@123'),
            'status' => 'On',
            'role' => 'Trưởng phòng Thu Mua',
        ]);
        User::factory()->create([
            'name' => 'Tạ Văn Toại',
            'email' => 'gd@honghafeed.com.vn',
            'password' => bcrypt('Hongha@123'),
            'status' => 'On',
            'role' => 'Giám đốc',
        ]);
        // User::factory()->create([
        //     'name' => 'Nguyễn khôi Nguyên',
        //     'email' => 'nguyenkhoinguyen@honghafeed.com.vn',
        //     'password' => bcrypt('Hongha@123'),
        //     'status' => 'On',
        //     'role' => 'Giám đốc',
        // ]);
    }
}
