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

        // Query base
        $query = \App\Models\SupplierSelectionReport::query();
        if ($roleName === 'Trưởng phòng Thu Mua') {
            $query = $query->where(function($q) use ($user) {
                $q->where('creator_id', $user->id)
                  ->orWhere('manager_id', $user->id);
            });
        } elseif ($roleName === 'Nhân viên Thu Mua') {
            $query = $query->where('creator_id', $user->id);
        } elseif ($roleName === 'Kế toán' || $roleName === 'Admin Thu Mua') {
            $query = $query->where('status', 'director_approved');
        }
        // Các role còn lại xem được toàn bộ

        // Thống kê
        $total = (clone $query)->count();
        $pending = (clone $query)->whereIn('status', ['pending_manager_approval','manager_approved','auditor_approved'])->count();
        $approved = (clone $query)->whereIn('status', ['manager_approved','auditor_approved','director_approved'])->count();
        $rejected = (clone $query)->where('status', 'rejected')->count();

        // Biểu đồ trạng thái
        $statusCounts = [
            'pending' => (clone $query)->whereIn('status', ['pending_manager_approval','manager_approved','auditor_approved'])->count(),
            'approved' => (clone $query)->whereIn('status', ['manager_approved','auditor_approved','director_approved'])->count(),
            'rejected' => (clone $query)->where('status', 'rejected')->count(),
        ];

        // Biểu đồ theo tháng
        $monthly = (clone $query)
            ->selectRaw('MONTH(created_at) as month, COUNT(*) as count')
            ->groupBy('month')
            ->pluck('count', 'month')->all();
        $monthlyData = [];
        for ($i = 1; $i <= 12; $i++) {
            $monthlyData[] = $monthly[$i] ?? 0;
        }

        // Phiếu gần đây
        $recentReports = (clone $query)->orderByDesc('created_at')->limit(5)->get(['id','code','description','status','created_at']);
        // Phiếu cần xử lý (ví dụ: đang chờ duyệt, hoặc chờ user xử lý)
        $pendingReports = (clone $query)->whereIn('status', ['pending_manager_approval','manager_approved','auditor_approved'])->orderByDesc('created_at')->limit(5)->get(['id','code','description','status','created_at']);

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
