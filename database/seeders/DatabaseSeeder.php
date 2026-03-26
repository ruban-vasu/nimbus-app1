<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // Fixed admin / test user
        User::factory()->create([
            'name'  => 'Test User',
            'email' => 'test@example.com',
        ]);

        // Domain data — order matters (FK dependencies)
        $this->call([
            ClinicSeeder::class,   // clinics → doctors → slots
            PatientSeeder::class,  // patients (no FK dependency)
        ]);
    }
}
