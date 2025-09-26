<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Department;

class DepartmentSeeder extends Seeder
{
    public function run(): void
    {
        Department::insert([
            ['code' => 'TM', 'name' => 'Phòng Thu Mua'],
            ['code' => 'KS', 'name' => 'Phòng Kiểm Soát'],
            ['code' => 'PK', 'name' => 'Phòng Kế Toán'],
            ['code' => 'GD', 'name' => 'Ban Giám Đốc'],
            ['code' => 'IT', 'name' => 'BP IT'],
        ]);
    }
}
