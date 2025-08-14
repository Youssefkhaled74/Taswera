<?php

namespace App\Models;

use Illuminate\Support\Facades\Log;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Photo extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'barcode_prefix',
        'file_path',
        'original_filename',
        'uploaded_by',
        'branch_id',
        'is_edited',
        'thumbnail_path',
        'status',
        'sync_status',
        'metadata'
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_edited' => 'boolean',
        'metadata' => 'array',
    ];

    /**
     * Get the user that owns the photo.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the staff that uploaded the photo.
     * This is an alias for the uploader relationship for backward compatibility.
     */
    public function staff()
    {
        return $this->belongsTo(Staff::class, 'uploaded_by');
    }

    /**
     * Get the staff that uploaded the photo.
     */
    public function uploader()
    {
        return $this->belongsTo(Staff::class, 'uploaded_by');
    }

    /**
     * Get the branch that the photo belongs to.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the order items for this photo.
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Extract the barcode from the file path
     */
    public function getBarcode(): ?string
    {
        $filePath = $this->file_path;
        $matches = [];
        if (preg_match('/\/(\d{8})\//', $filePath, $matches)) {
            return $matches[1];
        } else {
            // Add some debugging code here
            Log::debug("No barcode found in file path: $filePath");
            return null;
        }
    }
}
