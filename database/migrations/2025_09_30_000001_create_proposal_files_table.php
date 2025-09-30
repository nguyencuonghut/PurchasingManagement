<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('proposal_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('supplier_selection_report_id');
            $table->string('file_name');
            $table->string('file_path');
            $table->string('file_type')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->timestamps();

            $table->foreign('supplier_selection_report_id')
                ->references('id')
                ->on('supplier_selection_reports')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proposal_files');
    }
};
