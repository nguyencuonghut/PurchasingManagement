<?php

namespace App\Policies;

use App\Models\SupplierSelectionReport;
use App\Models\User;
use Illuminate\Auth\Access\Response;

class SupplierSelectionReportPolicy
{
    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return false;
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, SupplierSelectionReport $supplierSelectionReport): bool
    {
        $roleName = optional($user->role)->name;
        $departmentId = $user->department_id ?? null;
        $isDraft = $supplierSelectionReport->status === 'draft';
        $isSameDepartment = $supplierSelectionReport->creator && $supplierSelectionReport->creator->department_id == $departmentId;
        $isCreator = $supplierSelectionReport->creator_id == $user->id;

        if ($roleName === 'Nhân viên Thu Mua') {
            // Xem phiếu cùng phòng ban, phiếu draft chỉ xem phiếu do mình tạo
            return $isSameDepartment && (!$isDraft || $isCreator);
        }
        if ($roleName === 'Trưởng phòng Thu Mua') {
            // Xem phiếu cùng phòng ban, không xem phiếu draft
            return $isSameDepartment && !$isDraft;
        }
        // Các role khác: cho phép xem toàn bộ (hoặc mở rộng thêm nghiệp vụ nếu cần)
        return true;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return true;//return in_array(optional($user->role)->name, ['Quản trị', 'Trưởng phòng Thu Mua', 'Nhân viên Thu Mua']);
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SupplierSelectionReport $supplierSelectionReport): bool
    {
        return (optional($user->role)->name === 'Quản trị') || $user->id === $supplierSelectionReport->creator_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SupplierSelectionReport $supplierSelectionReport): bool
    {
        return (optional($user->role)->name === 'Quản trị') || $user->id === $supplierSelectionReport->creator_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, SupplierSelectionReport $supplierSelectionReport): bool
    {
        return false;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, SupplierSelectionReport $supplierSelectionReport): bool
    {
        return false;
    }
}
