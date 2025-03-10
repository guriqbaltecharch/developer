<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Project extends Model
{
    use HasFactory;

    protected $fillable = ['sales_team_id', 'client_id', 'project_name', 'requirements', 'budget', 'deadline', 'project_manager_id', 'assigned_by'];

    public function client()
    {
        return $this->belongsTo(Client::class);
    }

    public function salesTeam()
    {
        return $this->belongsTo(Team::class, 'sales_team_id');
    }

    public function projectManager()
    {
        return $this->belongsTo(User::class, 'project_manager_id');
    }

    public function assignedBy()
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }

    public function assignedEmployees()
    {
        return $this->belongsToMany(User::class, 'project_user', 'project_id', 'user_id', 'user_email');
		
    }
	
	public function assignedUsers()
    {
        return $this->belongsToMany(User::class, 'project_user');
    }
}
