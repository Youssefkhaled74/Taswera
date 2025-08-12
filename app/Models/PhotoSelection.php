<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PhotoSelection extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'original_photo_id',
        'barcode_prefix',
        'quantity',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}

