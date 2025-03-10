<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'upwork_id', 'contact_detail'];

    public function projects()
    {
        return $this->hasMany(Project::class);
    }
}
