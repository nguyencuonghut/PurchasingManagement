<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('supplier_selection_reports', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->text('description')->nullable();
            $table->text('file_path');
            $table->foreignId('creator_id')->constrained('users')->onDelete('cascade');
            $table->enum('status', ['draft',
                                    'pending_review',
                                    'reviewed',
                                    'pending_pm_approval',
                                    'pm_approved',
                                    'pending_director_approval',
                                    'director_approved',
                                    'rejected'
                                    ])
                                    ->default('draft');
            $table->enum('reviewer_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->longText('reviewer_notes')->nullable();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('reviewed_at')->nullable();
            $table->enum('pm_approver_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->longText('pm_approver_notes')->nullable();
            $table->foreignId('pm_approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('pm_approved_at')->nullable();
            $table->enum('director_approver_status', ['pending', 'approved', 'rejected'])->default('pending');
            $table->longText('director_approver_notes')->nullable();
            $table->foreignId('director_approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('director_approved_at')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('supplier_selection_reports');
    }
};
