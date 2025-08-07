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
        'manager_approved_result',
        'manager_approved_notes',
        'manager_id',
        'manager_approved_at',
        'auditor_audited_result',
        'auditor_audited_notes',
        'auditor_id',
        'auditor_audited_at',
        'director_approved_result',
        'director_approved_notes',
        'director_id',
        'director_approved_at',    ];

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

    public function creator()
    {
        return $this->belongsTo(User::class, 'creator_id');
    }

    public function getCreatorNameAttribute()
    {
        return $this->creator ? $this->creator->name : 'N/A';
    }

    public function manager()
    {
        return $this->belongsTo(User::class, 'manager_id');
    }

    public function getManagerNameAttribute()
    {
        return $this->manager ? $this->manager->name : 'N/A';
    }

    public function auditor()
    {
        return $this->belongsTo(User::class, 'auditor_id');
    }

    public function getAuditorNameAttribute()
    {
        return $this->auditor ? $this->auditor->name : 'N/A';
    }

    public function director()
    {
        return $this->belongsTo(User::class, 'director_id');
    }

    public function getDirectorNameAttribute()
    {
        return $this->director ? $this->director->name : 'N/A';
    }
}
