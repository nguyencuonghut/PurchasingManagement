<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\SupplierSelectionReport;
use App\Models\User;
use Illuminate\Support\Str;

class SupplierSelectionReportSeeder extends Seeder
{
    public function run()
    {
        $users = User::whereIn('role_id', [1, 2, 3])->pluck('id'); // Lấy ID của người dùng có vai trò Quản trị, Nhân viên Thu Mua, Trưởng phòng Thu Mua
        $statuses = [
            'pending_manager_approval',
            'manager_approved',
            'auditor_approved',
            'director_approved',
            'rejected',
        ];
        for ($i = 1; $i <= 100; $i++) {
            SupplierSelectionReport::create([
                'code' => date('y') . '/' . date('m') . '/' . str_pad($i, 3, '0', STR_PAD_LEFT) . '-PIT',
                'description' => 'Phiếu mua hàng số ' . $i,
                'status' => $statuses[array_rand($statuses)],
                'creator_id' => $users->random(),
                'manager_id' => $users->random(),
                'file_path' => '',
                'created_at' => now()->subDays(rand(0, 365)),
                'updated_at' => now(),
            ]);
        }
    }
}
