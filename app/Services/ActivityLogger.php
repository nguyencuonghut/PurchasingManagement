<?php

namespace App\Services;

use App\Models\ActivityLog;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class ActivityLogger
{
    /**
     * Record an activity log entry.
     *
     * @param Request $request
     * @param string $action e.g. created, updated, deleted, approved
     * @param Model|null $subject Eloquent model that is the subject of this activity
     * @param array $properties Arbitrary context information
     */
    public static function log(Request $request, string $action, ?Model $subject = null, array $properties = []): void
    {
        $user = $request->user();

        ActivityLog::create([
            'user_id' => $user?->id,
            'action' => $action,
            'subject_type' => $subject ? get_class($subject) : null,
            'subject_id' => $subject?->getKey(),
            'properties' => $properties ?: null,
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 1024),
        ]);
    }
}
