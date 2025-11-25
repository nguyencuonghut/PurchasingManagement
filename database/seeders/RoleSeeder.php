<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = [
            'Quản trị',
            'Nhân viên mua hàng',
            'Trưởng phòng',
            'Nhân viên Kiểm Soát',
            'Giám đốc',
            'Kế toán',
            'Admin Thu Mua'
        ];

        foreach ($roles as $role) {
            Role::firstOrCreate(['name' => $role]);
        }
    }
}
