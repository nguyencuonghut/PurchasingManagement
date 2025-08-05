<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class SupplierSelectionReport extends Model
{
    protected $fillable = [
        'code',
        'description',
        'file_path',
        'creator_id',
        'status',
        'reviewer_status',
        'reviewer_notes',
        'reviewer_id',
        'reviewed_at',
        'pm_approver_status',
        'pm_approver_notes',
        'pm_approver_id',
        'pm_approved_at',
        'director_approver_status',
        'director_approver_notes',
        'director_approver_id',
        'director_approved_at',
    ];

    // Accessor để tự động thêm URL công khai cho file_path khi truy vấn
    protected $appends = ['image_url']; // Thêm 'image_url' vào thuộc tính sẽ được tự động thêm vào JSON

    public function getImageUrlAttribute()
    {
        // Nếu file_path là một Data URL (tức là ảnh dán trực tiếp từ clipboard chưa được lưu vào server)
        if ($this->file_path && str_starts_with($this->file_path, 'data:image')) {
            return $this->file_path;
        }
        // Nếu file_path là đường dẫn tới file đã lưu trên server
        if ($this->file_path && Storage::disk('public')->exists($this->file_path)) {
            return Storage::url($this->file_path);
        }
        return null; // Không có file hoặc file không tồn tại
    }
}
