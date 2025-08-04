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
        return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return 'Quản trị' == $user->role || 'Trưởng phòng Thu Mua' == $user->role || 'Nhân viên Thu Mua' == $user->role;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, SupplierSelectionReport $supplierSelectionReport): bool
    {
        return 'Quản trị' == $user->role || $user->id === $supplierSelectionReport->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, SupplierSelectionReport $supplierSelectionReport): bool
    {
        return 'Quản trị' == $user->role || $user->id === $supplierSelectionReport->user_id;
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
