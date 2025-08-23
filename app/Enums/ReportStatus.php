<?php

namespace App\Enums;

final class ReportStatus
{
    public const DRAFT = 'draft';
    public const PENDING_MANAGER = 'pending_manager_approval';
    public const MANAGER_APPROVED = 'manager_approved';
    public const AUDITOR_APPROVED = 'auditor_approved';
    public const PENDING_DIRECTOR = 'pending_director_approval';
    public const DIRECTOR_APPROVED = 'director_approved';
    public const REJECTED = 'rejected';
}
