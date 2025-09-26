<?php

namespace App\Policies;

use App\Models\QuotationFile;
use App\Models\User;

class QuotationFilePolicy
{
    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, QuotationFile $quotationFile): bool
    {
        if (optional($user->role)->name === 'Quản trị') {
            return true;
        }

        $report = $quotationFile->supplierSelectionReport; // relation defined on model
        return $report && (int) $report->creator_id === (int) $user->id;
    }
}
