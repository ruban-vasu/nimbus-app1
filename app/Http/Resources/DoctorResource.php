<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class DoctorResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'clinic_id' => $this->clinic_id,
            'name' => $this->name,
            'specialization' => $this->specialization,
            'consultation_fee' => $this->consultation_fee,
            'is_active' => $this->is_active,
            'clinic' => [
                'id' => $this->clinic?->id,
                'name' => $this->clinic?->name,
            ],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}