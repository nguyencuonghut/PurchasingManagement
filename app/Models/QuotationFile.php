<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class QuotationFile extends Model
{
    protected $fillable = [
        'supplier_selection_report_id',
        'file_name',
        'file_path',
        'file_type',
        'file_size',
    ];

    public function supplierSelectionReport()
    {
        return $this->belongsTo(SupplierSelectionReport::class);
    }

    public function getFileUrlAttribute()
    {
        if ($this->file_path) {
            return Storage::url($this->file_path);
        }
        return null;
    }

    public function getFileExtensionAttribute()
    {
        return pathinfo($this->file_name, PATHINFO_EXTENSION);
    }

    public function getFileSizeFormattedAttribute()
    {
        if ($this->file_size) {
            $units = ['B', 'KB', 'MB', 'GB'];
            $bytes = $this->file_size;
            for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
                $bytes /= 1024;
            }
            return round($bytes, 2) . ' ' . $units[$i];
        }
        return 'N/A';
    }
}
