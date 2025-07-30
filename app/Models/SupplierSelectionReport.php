<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SupplierSelectionReport extends Model
{
    protected $fillable = [
        'code',
        'description',
        'file_path',
    ];
}
