<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Payment extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'order_id',
        'amount',
        'method',
        'paid_at',
        'received_by',
        'transaction_id',
        'branch_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
    ];

    /**
     * Get the order that owns the payment.
     */
    public function order()
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * Get the staff that received the payment.
     */
    public function receiver()
    {
        return $this->belongsTo(Staff::class, 'received_by');
    }

    /**
     * Get the branch that the payment belongs to.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
} 