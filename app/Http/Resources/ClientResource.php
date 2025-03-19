<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClientResource extends JsonResource
{
    public function toArray(Request $request)
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'contact_detail' => $this->contact_detail,
            'hire_through' => $this->hire_through, // ✅ Ensure these exist
            'hire_on_id' => $this->hire_on_id, // ✅ Ensure these exist
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
