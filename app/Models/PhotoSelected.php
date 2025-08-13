<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PhotoSelected extends Model
{
    use HasFactory;

    protected $table = 'photo_selected';

    protected $fillable = [
        'user_id',
        'original_photo_id',
        'quantity',
        'barcode_prefix',
        'file_path',
        'original_filename',
        'uploaded_by',
        'branch_id',
        'is_edited',
        'thumbnail_path',
        'status',
        'sync_status',
        'metadata',
    ];

    protected $casts = [
        'is_edited' => 'boolean',
        'metadata' => 'array',
    ];

    public function uploader()
    {
        return $this->belongsTo(Staff::class, 'uploaded_by');
    }

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    public function originalPhoto()
    {
        return $this->belongsTo(Photo::class, 'original_photo_id');
    }
}

