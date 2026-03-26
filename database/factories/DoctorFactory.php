<?php

namespace Database\Factories;

use App\Models\Clinic;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Doctor>
 */
class DoctorFactory extends Factory
{
    private static array $specializations = [
        'General Practice',
        'Cardiology',
        'Dermatology',
        'Orthopedics',
        'Pediatrics',
        'Neurology',
        'Gynecology',
        'Ophthalmology',
        'ENT',
        'Psychiatry',
    ];

    public function definition(): array
    {
        return [
            'clinic_id'        => Clinic::factory(),
            'name'             => 'Dr. ' . fake()->name(),
            'specialization'   => fake()->randomElement(self::$specializations),
            'consultation_fee' => fake()->randomFloat(2, 300, 2000),
            'is_active'        => true,
        ];
    }

    public function forClinic(int $clinicId): static
    {
        return $this->state(['clinic_id' => $clinicId]);
    }

    public function inactive(): static
    {
        return $this->state(['is_active' => false]);
    }
}
