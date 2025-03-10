<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone_num' => $this->phone_num,
            'emergency_phone_num' => $this->emergency_phone_num,
            'pm_id' => $this->pm_id,
            'role_id' => $this->role_id,
            'address' => $this->address,
            'team_id' => $this->team_id,
            'roles' => $this->role ? $this->role->name : null,
            'team' => $this->team ? $this->team->name : null,
            'profile_pic_url' => $this->profile_pic ? asset('storage/profile_pics/' . $this->profile_pic) : null,
        ];
    }
}

