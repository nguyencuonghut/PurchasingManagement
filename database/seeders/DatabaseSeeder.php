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
        $this->call([
            RoleSeeder::class,
        ]);

        User::factory()->create([
            'name' => 'Tony Nguyen',
            'email' => 'nguyenvancuong@honghafeed.com.vn',
            'password' => bcrypt('Hongha@123'),
            'status' => 'On',
            'role_id' => 1, // Quản trị
        ]);
        User::factory()->create([
            'name' => 'Phạm Thị Trang',
            'email' => 'nvtm@honghafeed.com.vn',
            'password' => bcrypt('Hongha@123'),
            'status' => 'On',
            'role_id' => 2, // Nhân viên Thu Mua
        ]);
        User::factory()->create([
            'name' => 'Bùi Thị Nụ',
            'email' => 'nvks@honghafeed.com.vn',
            'password' => bcrypt('Hongha@123'),
            'status' => 'On',
            'role_id' => 3, // Nhân viên Kiểm Soát
        ]);
        User::factory()->create([
            'name' => 'Lê Thị Hồng',
            'email' => 'tptm@honghafeed.com.vn',
            'password' => bcrypt('Hongha@123'),
            'status' => 'On',
            'role_id' => 4, // Trưởng phòng Thu Mua
        ]);
        User::factory()->create([
            'name' => 'Vũ Hoàng Giang',
            'email' => 'tptm2@honghafeed.com.vn',
            'password' => bcrypt('Hongha@123'),
            'status' => 'On',
            'role_id' => 4, // Trưởng phòng Thu Mua
        ]);
        User::factory()->create([
            'name' => 'Tạ Văn Toại',
            'email' => 'gd@honghafeed.com.vn',
            'password' => bcrypt('Hongha@123'),
            'status' => 'On',
            'role_id' => 5, // Giám đốc
        ]);
        User::factory()->create([
            'name' => 'Nguyễn khôi Nguyên',
            'email' => 'gd2@honghafeed.com.vn',
            'password' => bcrypt('Hongha@123'),
            'status' => 'On',
            'role_id' => 5, // Giám đốc
        ]);
        $this->call(\Database\Seeders\SupplierSelectionReportSeeder::class);
    }
}
