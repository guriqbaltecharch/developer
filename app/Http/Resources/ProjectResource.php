<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\TagsActivity;
use App\Http\Resources\ClientResource;
use App\Models\User; // Assuming project manager is stored in users table

class ProjectResource extends JsonResource
{
    

public function toArray($request)
{
   return [
        'id' => $this->id,
        'project_name' => $this->project_name,
        'client' => new ClientResource($this->client),
        'project_manager' => [
            'id' => $this->projectManager?->id,
            'name' => $this->projectManager?->name,
            'email' => $this->projectManager?->email,
        ],
        'requirements' => $this->requirements,
        'budget' => $this->budget,
        'deadline' => $this->deadline,
        'total_hours' => $this->total_hours,
        'tags_activitys' => json_decode($this->tags_activitys, true),
        'created_at' => $this->created_at->format('Y-m-d H:i:s'),
        'updated_at' => $this->updated_at->format('Y-m-d H:i:s'),
    ];
}


}
