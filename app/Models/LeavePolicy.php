<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LeavePolicy extends Model
{
    use HasFactory;

    protected $table = 'leavespolicy'; // ✅ Define custom table name

    protected $fillable = [
        'user_id', 
        'start_date', 
        'end_date', 
        'leave_type', 
        'reason', 
        'status',
        'hours' // ✅ New column added
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
	
	
}
