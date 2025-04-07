<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use App\Models\TagsActivity;

class ProjectResource extends JsonResource
{
    

public function toArray($request)
{
    $tagsIds = json_decode($this->tags_activitys, true);

    $tags = is_array($tagsIds)
        ? TagsActivity::whereIn('id', $tagsIds)->get(['id', 'name'])
        : [];

    return [
        'id' => $this->id,
        'project_name' => $this->project_name,
        'client' => $this->client,
        'requirements' => $this->requirements,
        'budget' => $this->budget,
        'deadline' => $this->deadline,
        'tags_activities' => $tags,
        'created_at' => $this->created_at,
        'updated_at' => $this->updated_at,
    ];
}
}
