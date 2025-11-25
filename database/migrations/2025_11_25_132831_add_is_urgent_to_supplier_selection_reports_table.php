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
        Schema::table('supplier_selection_reports', function (Blueprint $table) {
            $table->boolean('is_urgent')->default(false)->after('status')->comment('Báo cáo khẩn cấp - bỏ qua Kiểm Soát Nội Bộ');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('supplier_selection_reports', function (Blueprint $table) {
            $table->dropColumn('is_urgent');
        });
    }
};
