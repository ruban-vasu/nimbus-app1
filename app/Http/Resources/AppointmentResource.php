<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AppointmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'patient_id' => $this->patient_id,
            'slot_id' => $this->slot_id,
            'status' => $this->status?->value ?? $this->status,
            'notes' => $this->notes,
            'patient' => [
                'id' => $this->patient?->id,
                'name' => $this->patient?->name,
            ],
            'slot' => [
                'id' => $this->slot?->id,
                'doctor_id' => $this->slot?->doctor_id,
                'date' => $this->slot?->date?->toDateString(),
                'start_time' => $this->slot?->start_time,
                'end_time' => $this->slot?->end_time,
                'status' => $this->slot?->status?->value ?? $this->slot?->status,
            ],
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}