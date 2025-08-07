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
                                    'pending_manager_approval',
                                    'manager_approved',
                                    'auditor_approved',
                                    'director_approved',
                                    'rejected'
                                    ])
                                    ->default('draft');
            $table->enum('auditor_audited_result', ['pending', 'approved', 'rejected'])->default('pending');
            $table->longText('auditor_audited_notes')->nullable();
            $table->timestamp('auditor_audited_at')->nullable();
            $table->foreignId('auditor_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('manager_approved_result', ['pending', 'approved', 'rejected'])->default('pending');
            $table->longText('manager_approved_notes')->nullable();
            $table->timestamp('manager_approved_at')->nullable();
            $table->foreignId('manager_id')->nullable()->constrained('users')->nullOnDelete();
            $table->enum('director_approved_result', ['pending', 'approved', 'rejected'])->default('pending');
            $table->longText('director_approved_notes')->nullable();
            $table->timestamp('director_approved_at')->nullable();
            $table->foreignId('director_id')->nullable()->constrained('users')->nullOnDelete();
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
