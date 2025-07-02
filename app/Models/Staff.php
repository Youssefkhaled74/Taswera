<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class Staff extends Authenticatable
{
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'branch_id',
        'api_token',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'password' => 'hashed',
    ];

    /**
     * Get the branch that the staff belongs to.
     */
    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Get the photos uploaded by this staff member.
     */
    public function uploadedPhotos()
    {
        return $this->hasMany(Photo::class, 'uploaded_by');
    }

    /**
     * Get the orders processed by this staff member.
     */
    public function processedOrders()
    {
        return $this->hasMany(Order::class, 'processed_by');
    }

    /**
     * Get the payments received by this staff member.
     */
    public function receivedPayments()
    {
        return $this->hasMany(Payment::class, 'received_by');
    }

    /**
     * Get the users registered by this staff member.
     * Note: This requires adding a 'registered_by' field to the users table.
     */
    public function registeredUsers()
    {
        return $this->hasMany(User::class, 'registered_by');
    }
} 