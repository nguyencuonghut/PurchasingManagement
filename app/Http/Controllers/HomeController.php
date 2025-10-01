<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Inertia\Inertia;

class HomeController extends Controller
{
    public function home()
    {
        $user = Auth::user();
        $roleName = optional($user->role)->name;


        // Query cho KPI/Chart (toàn bộ phiếu)
        $allQuery = \App\Models\SupplierSelectionReport::query();

        $total = (clone $allQuery)->count();
        $pending = (clone $allQuery)->whereIn('status', ['pending_manager_approval','manager_approved','auditor_approved'])->count();
        $approved = (clone $allQuery)->whereIn('status', ['manager_approved','auditor_approved','director_approved'])->count();
        $rejected = (clone $allQuery)->where('status', 'rejected')->count();

        $statusCounts = [
            'pending' => (clone $allQuery)->whereIn('status', ['pending_manager_approval','manager_approved','auditor_approved'])->count(),
            'approved' => (clone $allQuery)->whereIn('status', ['manager_approved','auditor_approved','director_approved'])->count(),
            'rejected' => (clone $allQuery)->where('status', 'rejected')->count(),
        ];

        $monthly = (clone $allQuery)
            ->selectRaw('MONTH(created_at) as month, COUNT(*) as count')
            ->groupBy('month')
            ->pluck('count', 'month')->all();
        $monthlyData = [];
        for ($i = 1; $i <= 12; $i++) {
            $monthlyData[] = $monthly[$i] ?? 0;
        }

        // Query filter theo Role cho datatable
        $datatableQuery = \App\Models\SupplierSelectionReport::query();
        if ($roleName === 'Admin') {
            // Xem tất cả phiếu
            // Không cần filter
        } elseif ($roleName === 'Nhân viên Thu Mua') {
            $datatableQuery = $datatableQuery->where('creator_id', $user->id);
        } elseif ($roleName === 'Trưởng phòng Thu Mua') {
            $datatableQuery = $datatableQuery->where(function($q) use ($user) {
                $q->where('creator_id', $user->id)
                  ->orWhere('manager_id', $user->id);
            });
        } elseif ($roleName === 'Nhân viên Kiểm Soát') {
            $datatableQuery = $datatableQuery->where('status', 'manager_approved');
        } elseif ($roleName === 'Giám đốc') {
            $datatableQuery = $datatableQuery->where(function($q) use ($user) {
                $q->where('director_id', $user->id)
                  ->where('status', 'auditor_approved');
            });
        } elseif ($roleName === 'Kế toán') {
            $datatableQuery = $datatableQuery->where('status', 'director_approved');
        } elseif ($roleName === 'Admin Thu Mua') {
            $datatableQuery = $datatableQuery->where('status', 'director_approved')
                         ->where('adm_id', $user->id);
        }
        // Các role còn lại xem được toàn bộ

        // Phiếu gần đây
        $recentReports = (clone $datatableQuery)->orderByDesc('created_at')->limit(5)->get(['id','code','description','status','created_at']);
        // Phiếu cần xử lý (ví dụ: đang chờ duyệt, hoặc chờ user xử lý)
        $pendingReports = (clone $datatableQuery)->whereIn('status', ['pending_manager_approval','manager_approved','auditor_approved'])->orderByDesc('created_at')->limit(5)->get(['id','code','description','status','created_at']);

        // Thông báo (dummy)
        $notifications = [];

        return Inertia::render('Dashboard', [
            'stats' => [
                ['label' => 'Tổng số phiếu', 'value' => $total, 'icon' => 'pi pi-file'],
                ['label' => 'Đang chờ duyệt', 'value' => $pending, 'icon' => 'pi pi-clock'],
                ['label' => 'Đã duyệt', 'value' => $approved, 'icon' => 'pi pi-check'],
                ['label' => 'Đã từ chối', 'value' => $rejected, 'icon' => 'pi pi-times'],
            ],
            'statusChartData' => [
                'labels' => ['Đang chờ', 'Đã duyệt', 'Đã từ chối'],
                'datasets' => [[
                    'data' => [
                        $statusCounts['pending'],
                        $statusCounts['approved'],
                        $statusCounts['rejected'],
                    ],
                    'backgroundColor' => ['#fbbf24', '#22c55e', '#ef4444'],
                ]],
            ],
            'monthlyChartData' => [
                'labels' => range(1,12),
                'datasets' => [[
                    'label' => 'Số phiếu',
                    'data' => $monthlyData,
                    'backgroundColor' => '#3b82f6',
                ]],
            ],
            'recentReports' => $recentReports,
            'pendingReports' => $pendingReports,
            'notifications' => $notifications,
        ]);
    }
}
