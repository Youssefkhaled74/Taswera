<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class OrderItem extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_id',
        'photo_id',
        'frame',
        'filter',
        'edited_photo_path',
        'selected_photo_id',
        'original_photo_id',
    ];

    /**
     * Get the order that owns the order item.
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the photo for this order item.
     */
    public function photo()
    {
        return $this->belongsTo(Photo::class);
    }

    public function selected()
    {
        return $this->belongsTo(PhotoSelected::class, 'selected_photo_id');
    }
} 