<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    protected $fillable = [
        'barcode_prefix',
        'user_id',
        'branch_id',
        'staff_id',
        'num_photos',
        'amount',
        'tax_rate',
        'tax_amount',
        'total_amount',
        'invoice_method',
        'status',
        'metadata'
    ];
    // status can be 'paid', 'unpaid', 'cancelled', etc.
    

    protected $dates = [
        'created_at',
        'updated_at'
    ];
    // Casts


    protected $casts = [
        'metadata' => 'array',
        'amount' => 'decimal:2',
        'tax_rate' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'status' => 'string',
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
} 