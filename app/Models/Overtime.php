<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Overtime extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'overtime_date',
        'planned_start',
        'planned_end',
        'reason',
        'overtime_request_photo',
        'approval_status',
        'approved_by',
        'actual_start',
        'overtime_start_photo',
        'actual_end',
        'overtime_end_photo',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function approver()
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
