<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Branch extends Model
{
    use HasFactory , SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'location',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the staff associated with the branch.
     */
    public function staff()
    {
        return $this->hasMany(Staff::class);
    }

    /**
     * Get the users associated with the branch.
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * Get the photos associated with the branch.
     */
    public function photos()
    {
        return $this->hasMany(Photo::class);
    }

    /**
     * Get the orders associated with the branch.
     */
    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Get the payments associated with the branch.
     */
    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    /**
     * Get the sync logs associated with the branch.
     */
    public function syncLogs()
    {
        return $this->hasMany(SyncLog::class);
    }

    /**
     * Get the packages associated with the branch.
     */
    public function packages()
    {
        return $this->hasMany(Package::class);
    }

    /**
     * Get the frames associated with the branch.
     */
    public function frames()
    {
        return $this->hasMany(Frame::class);
    }

    /**
     * Get the filters associated with the branch.
     */
    public function filters()
    {
        return $this->hasMany(Filter::class);
    }
} 