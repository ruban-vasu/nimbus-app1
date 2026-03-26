<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Patient>
 */
class PatientFactory extends Factory
{
    private static array $insuranceProviders = [
        'BlueCross BlueShield',
        'Aetna',
        'United Healthcare',
        'Cigna',
        'Humana',
        'Kaiser Permanente',
        null, // some patients are uninsured
        null,
    ];

    public function definition(): array
    {
        return [
            'name'               => fake()->name(),
            'email'              => fake()->unique()->safeEmail(),
            'phone'              => fake()->numerify('0##-###-####'),
            'date_of_birth'      => fake()->dateTimeBetween('-70 years', '-18 years')->format('Y-m-d'),
            'insurance_provider' => fake()->randomElement(self::$insuranceProviders),
        ];
    }
}
