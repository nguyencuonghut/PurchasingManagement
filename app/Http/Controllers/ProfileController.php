<?php

namespace App\Http\Controllers;

use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ProfileController extends Controller
{
    public function profile()
    {
        $user = Auth::user();
        $activityLogs = $user->activityLogs()
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'action', 'created_at', 'properties', 'subject_id']);

        // Collect all subject_ids from activity logs
        $subjectIds = $activityLogs->pluck('subject_id')->filter()->unique()->values();
        $reportCodesById = [];
        if ($subjectIds->isNotEmpty()) {
            $reports = \App\Models\SupplierSelectionReport::whereIn('id', $subjectIds)->get(['id', 'code']);
            $reportCodesById = $reports->pluck('code', 'id');
        }

        return Inertia::render('Profile', [
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'phone' => $user->phone ?? null,
                'status' => $user->status ?? null,
            ],
            'activityLogs' => $activityLogs,
            'reportCodesById' => $reportCodesById,
        ]);
    }
}
