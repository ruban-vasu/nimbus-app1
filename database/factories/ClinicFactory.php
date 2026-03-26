<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Clinic>
 */
class ClinicFactory extends Factory
{
    public function definition(): array
    {
        $days = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];

        $hours = collect($days)->mapWithKeys(fn ($day) => [
            $day => $day === 'saturday' ? '09:00-13:00' : '08:00-18:00',
        ])->all();

        return [
            'name'             => fake()->company() . ' Clinic',
            'address'          => fake()->streetAddress() . ', ' . fake()->city(),
            'phone'            => fake()->numerify('0##-###-####'),
            'operating_hours'  => $hours,
            'is_active'        => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
