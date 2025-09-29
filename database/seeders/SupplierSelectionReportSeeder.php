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
        $users = User::whereIn('role_id', [1, 2, 3])->get();
        $statuses = [
            'pending_manager_approval',
            'manager_approved',
            'auditor_approved',
            'director_approved',
            'rejected',
        ];
        $year = date('Y');
        // Tìm index lớn nhất đã dùng cho năm hiện tại trước vòng lặp
        $maxIndex = SupplierSelectionReport::whereYear('created_at', $year)
            ->where('code', 'like', "$year/%/%")
            ->get()
            ->map(function($r) use ($year) {
                $parts = explode('/', $r->code);
                return isset($parts[2]) ? intval($parts[2]) : 0;
            })->max();
        $nextIndex = $maxIndex ? $maxIndex + 1 : 1;
        foreach (range(1, 100) as $i) {
            $creator = $users->random();
            $departmentCode = optional($creator->department)->code ?? 'N/A';
            $code = sprintf('%d/%s/%d', $year, $departmentCode, $nextIndex);
            SupplierSelectionReport::create([
                'code' => $code,
                'description' => 'Phiếu mua hàng số ' . $i,
                'status' => $statuses[array_rand($statuses)],
                'creator_id' => $creator->id,
                'manager_id' => $users->random()->id,
                'file_path' => '',
                'created_at' => now()->subDays(rand(0, 365)),
                'updated_at' => now(),
            ]);
            $nextIndex++;
        }
    }
}
