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

        // Tính toán một lần và sử dụng lại
        $statusCounts = [
            'draft' => (clone $allQuery)->where('status', 'draft')->count(),
            'pending' => (clone $allQuery)->whereIn('status', ['pending_manager_approval','manager_approved','auditor_approved'])->count(),
            'approved' => (clone $allQuery)->where('status', 'director_approved')->count(),
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
            // Xem tất cả phiếu của phòng ban mình, nhưng với phiếu trạng thái draft thì chỉ xem của mình
            $datatableQuery = $datatableQuery->where('department_id', $user->department_id);
            $datatableQuery = $datatableQuery->where(function($q) use ($user) {
                $q->where('status', '!=', 'draft')
                  ->orWhere('creator_id', $user->id);
            });
        } elseif ($roleName === 'Trưởng phòng Thu Mua') {
            // Xem tất cả phiếu của phòng ban mình, trừ những phiếu ở trạng thái draft
            $datatableQuery = $datatableQuery->where('department_id', $user->department_id);
            $datatableQuery = $datatableQuery->where('status', '!=', 'draft');
        } elseif ($roleName === 'Nhân viên Kiểm Soát') {
            $datatableQuery = $datatableQuery->whereIn('status', ['manager_approved', 'auditor_approved', 'rejected', 'pending_director_approval', 'director_approved']);
        } elseif ($roleName === 'Giám đốc') {
            $datatableQuery = $datatableQuery->where(function($q) use ($user) {
                $q->whereIn('status', ['pending_director_approval','director_approved']);
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

        // Phiếu cần xử lý - filter theo đúng ý nghĩa từng Role
        $pendingReportsQuery = \App\Models\SupplierSelectionReport::query();
        if ($roleName === 'Admin') {
            $pendingReportsQuery = $pendingReportsQuery->whereIn('status', ['pending_manager_approval','manager_approved','auditor_approved']);
        } elseif ($roleName === 'Nhân viên Thu Mua') {
            $pendingReportsQuery = $pendingReportsQuery->where('creator_id', $user->id)
                ->whereIn('status', ['pending_manager_approval','manager_approved','auditor_approved']);
        } elseif ($roleName === 'Trưởng phòng Thu Mua') {
            $pendingReportsQuery = $pendingReportsQuery->where('manager_id', $user->id)
                ->where('status', 'pending_manager_approval');
        } elseif ($roleName === 'Nhân viên Kiểm Soát') {
            $pendingReportsQuery = $pendingReportsQuery->where('status', 'manager_approved');
        } elseif ($roleName === 'Giám đốc') {
            $pendingReportsQuery = $pendingReportsQuery->where('status', 'auditor_approved');
        } elseif ($roleName === 'Kế toán') {
            $pendingReportsQuery = $pendingReportsQuery->where('status', 'director_approved');
        } elseif ($roleName === 'Admin Thu Mua') {
            $pendingReportsQuery = $pendingReportsQuery->where('status', 'director_approved')
                ->where('adm_id', $user->id);
        }
        // Các role còn lại xem được toàn bộ phiếu cần xử lý
        $pendingReports = $pendingReportsQuery->orderByDesc('created_at')->limit(5)->get(['id','code','description','status','created_at']);

        // Thông báo (dummy)
        $notifications = [];

        return Inertia::render('Dashboard', [
            'stats' => [
                ['label' => 'Tổng số phiếu', 'value' => $total, 'icon' => 'pi pi-file'],
                ['label' => 'Nháp', 'value' => $statusCounts['draft'], 'icon' => 'pi pi-file-edit'],
                ['label' => 'Đang chờ duyệt', 'value' => $statusCounts['pending'], 'icon' => 'pi pi-clock'],
                ['label' => 'Đã duyệt', 'value' => $statusCounts['approved'], 'icon' => 'pi pi-check'],
                ['label' => 'Đã từ chối', 'value' => $statusCounts['rejected'], 'icon' => 'pi pi-times'],
            ],
            'statusChartData' => [
                'labels' => ['Nháp', 'Đang chờ duyệt', 'Đã duyệt', 'Đã từ chối'],
                'datasets' => [[
                    'data' => [
                        $statusCounts['draft'],
                        $statusCounts['pending'],
                        $statusCounts['approved'],
                        $statusCounts['rejected'],
                    ],
                    'backgroundColor' => ['#6b7280', '#fbbf24', '#22c55e', '#ef4444'],
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
