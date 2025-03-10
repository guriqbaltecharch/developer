<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PerformaSheet extends Model
{
    use HasFactory;

    protected $fillable = ['user_id', 'data'];

    protected $casts = [
        'data' => 'array', // Automatically decode JSON in Laravel
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
