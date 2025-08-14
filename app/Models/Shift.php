<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Shift extends Model
{
    use HasFactory;

    protected $fillable = [
        'branch_id',
        'name',
        'from',
        'to',
    ];

    public function branch()
    {
        return $this->belongsTo(Branch::class);
    }
}
