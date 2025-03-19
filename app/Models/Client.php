<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Client extends Model
{
    use HasFactory;

    protected $fillable = ['name',  'contact_detail', 'hire_through', 'hire_on_id'];


    public function projects()
    {
        return $this->hasMany(Project::class);
    }
	
	/*public function projects()
    {
        return $this->hasMany(Project::class, 'client_id');
    }*/
}
