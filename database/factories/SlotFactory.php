<?php

namespace Database\Factories;

use App\Enums\SlotStatus;
use App\Models\Doctor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Carbon;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Slot>
 */
class SlotFactory extends Factory
{
    /** Typical clinic slot durations in minutes. */
    private const DURATIONS = [15, 20, 30, 45, 60];

    public function definition(): array
    {
        $duration  = fake()->randomElement(self::DURATIONS);
        $startHour = fake()->numberBetween(8, 16);
        $startMin  = fake()->randomElement([0, 15, 30, 45]);
        $start     = Carbon::today()->setTime($startHour, $startMin);
        $end       = $start->copy()->addMinutes($duration);

        return [
            'doctor_id'  => Doctor::factory(),
            'date'       => fake()->dateTimeBetween('today', '+7 days')->format('Y-m-d'),
            'start_time' => $start->format('H:i:s'),
            'end_time'   => $end->format('H:i:s'),
            'duration'   => $duration,
            'status'     => SlotStatus::Available,
        ];
    }

    /** Pin to a specific doctor. */
    public function forDoctor(int $doctorId): static
    {
        return $this->state(['doctor_id' => $doctorId]);
    }

    /** Pin to a specific date. */
    public function onDate(string $date): static
    {
        return $this->state(['date' => $date]);
    }

    public function booked(): static
    {
        return $this->state(['status' => SlotStatus::Booked]);
    }

    public function blocked(): static
    {
        return $this->state(['status' => SlotStatus::Blocked]);
    }
}
