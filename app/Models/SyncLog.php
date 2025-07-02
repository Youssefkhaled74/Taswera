<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SyncLog extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'branch_id',
        'synced_at',
        'total_sales',
        'total_orders',
        'total_users',
        'total_photos',
        'status',
        'error_message',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'synced_at' => 'datetime',
        'total_sales' => 'decimal:2',
        'total_orders' => 'integer',
        'total_users' => 'integer',
        'total_photos' => 'integer',
    ];

    /**
     * Get the branch that the sync log belongs to.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
} 