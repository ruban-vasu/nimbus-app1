<?php

namespace Database\Factories;

use App\Enums\AppointmentStatus;
use App\Enums\SlotStatus;
use App\Models\Patient;
use App\Models\Slot;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Appointment>
 */
class AppointmentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'patient_id' => Patient::factory(),
            'slot_id'    => Slot::factory()->booked(),
            'status'     => AppointmentStatus::Pending,
            'notes'      => fake()->optional(0.4)->sentence(),
        ];
    }

    public function confirmed(): static
    {
        return $this->state(['status' => AppointmentStatus::Confirmed]);
    }

    public function completed(): static
    {
        return $this->state(['status' => AppointmentStatus::Completed]);
    }

    public function cancelled(): static
    {
        return $this->state(['status' => AppointmentStatus::Cancelled]);
    }
}
