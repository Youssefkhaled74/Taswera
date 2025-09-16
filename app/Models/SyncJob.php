<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SyncJob extends Model
{
    protected $fillable = [
        'branch_id',
        'employeeName',
        'employee_id',
        'pay_amount',
        'orderprefixcode',
        'status',
        'shift_name',
        'orderphone',
        'number_of_photos',
    ];
}
