<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Order extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'package_id',
        'total_price',
        'status',
        'processed_by',
        'branch_id',
        'whatsapp_link',
        'link_expires_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'total_price' => 'decimal:2',
        'link_expires_at' => 'datetime',
    ];

    /**
     * Get the user that owns the order.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the package for this order.
     */
    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    /**
     * Get the staff that processed the order.
     */
    public function processor()
    {
        return $this->belongsTo(Staff::class, 'processed_by');
    }

    /**
     * Get the branch that the order belongs to.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the order items for this order.
     */
    public function orderItems()
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * Get the payment for this order.
     */
    public function payment()
    {
        return $this->hasOne(Payment::class);
    }
} 