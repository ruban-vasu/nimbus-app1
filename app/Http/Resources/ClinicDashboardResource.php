<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ClinicDashboardResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'clinic' => [
                'id' => $this['clinic']['id'],
                'name' => $this['clinic']['name'],
                'phone' => $this['clinic']['phone'],
            ],
            'today' => $this['today'],
            'stats' => [
                'doctor_count' => $this['stats']['doctor_count'],
                'today_slots' => $this['stats']['today_slots'],
                'available_today_slots' => $this['stats']['available_today_slots'],
                'booked_today_slots' => $this['stats']['booked_today_slots'],
                'blocked_today_slots' => $this['stats']['blocked_today_slots'],
                'today_appointments' => $this['stats']['today_appointments'],
                'confirmed_today_appointments' => $this['stats']['confirmed_today_appointments'],
            ],
            'upcoming_slots' => SlotResource::collection($this['upcoming_slots']),
        ];
    }
}