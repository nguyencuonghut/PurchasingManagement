<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('supplier_selection_reports')) {
            // Cho phép file_path NULL để phù hợp với nghiệp vụ draft/không bắt buộc đính kèm ảnh
            DB::statement("ALTER TABLE supplier_selection_reports MODIFY file_path TEXT NULL");
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('supplier_selection_reports')) {
            // Trả về NOT NULL (lưu ý: có thể fail nếu đang có bản ghi NULL)
            DB::statement("ALTER TABLE supplier_selection_reports MODIFY file_path TEXT NOT NULL");
        }
    }
};
