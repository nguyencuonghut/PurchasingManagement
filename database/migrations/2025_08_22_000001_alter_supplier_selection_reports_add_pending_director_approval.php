<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (Schema::hasTable('supplier_selection_reports')) {
            DB::statement("ALTER TABLE supplier_selection_reports MODIFY status ENUM('draft','pending_manager_approval','manager_approved','auditor_approved','pending_director_approval','director_approved','rejected') NOT NULL DEFAULT 'draft'");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasTable('supplier_selection_reports')) {
            DB::statement("ALTER TABLE supplier_selection_reports MODIFY status ENUM('draft','pending_manager_approval','manager_approved','auditor_approved','director_approved','rejected') NOT NULL DEFAULT 'draft'");
        }
    }
};
