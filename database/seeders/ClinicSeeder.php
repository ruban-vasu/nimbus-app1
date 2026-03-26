<?php

namespace Database\Seeders;

use App\Models\Clinic;
use App\Models\Doctor;
use App\Models\Patient;
use App\Models\Slot;
use App\Enums\SlotStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

class ClinicSeeder extends Seeder
{
    /**
     * Creates 3 clinics, distributes 10 doctors across them,
     * then generates 200+ appointment slots for the next 7 days.
     *
     * Slot distribution strategy (per doctor, per day):
     *   - Morning block : 08:00 – 12:00  → 8 × 30-min slots
     *   - Afternoon block: 13:00 – 17:00 → 8 × 30-min slots
     *   Total: 16 slots × 10 doctors × 7 days = 1,120 slots (well over 200).
     *   ~20 % are randomly marked as booked to simulate real-world usage.
     */
    public function run(): void
    {
        // ── 3 Clinics ────────────────────────────────────────────────────
        $clinics = Clinic::factory(3)->create();

        // ── 10 Doctors distributed across clinics ────────────────────────
        // Spread evenly: 4 / 3 / 3
        $distribution = [4, 3, 3];

        $doctors = collect();
        foreach ($clinics as $index => $clinic) {
            $count     = $distribution[$index];
            $newDoctors = Doctor::factory($count)
                ->forClinic($clinic->id)
                ->create();
            $doctors = $doctors->merge($newDoctors);
        }

        // ── 200+ Slots across next 7 days ────────────────────────────────
        $today      = Carbon::today();
        $slotData   = [];
        $now        = now();

        foreach ($doctors as $doctor) {
            for ($dayOffset = 0; $dayOffset < 7; $dayOffset++) {
                $date = $today->copy()->addDays($dayOffset)->format('Y-m-d');

                // Morning: 08:00 – 12:00 (8 slots × 30 min)
                // Afternoon: 13:00 – 17:00 (8 slots × 30 min)
                $windows = [
                    ['start' => '08:00', 'end' => '12:00'],
                    ['start' => '13:00', 'end' => '17:00'],
                ];

                foreach ($windows as $window) {
                    $cursor = Carbon::parse("{$date} {$window['start']}");
                    $limit  = Carbon::parse("{$date} {$window['end']}");

                    while ($cursor->lt($limit)) {
                        $slotEnd = $cursor->copy()->addMinutes(30);

                        // Randomly mark ~20 % of slots as booked
                        $status = (random_int(1, 100) <= 20)
                            ? SlotStatus::Booked->value
                            : SlotStatus::Available->value;

                        $slotData[] = [
                            'doctor_id'  => $doctor->id,
                            'date'       => $date,
                            'start_time' => $cursor->format('H:i:s'),
                            'end_time'   => $slotEnd->format('H:i:s'),
                            'duration'   => 30,
                            'status'     => $status,
                            'created_at' => $now,
                            'updated_at' => $now,
                        ];

                        $cursor->addMinutes(30);
                    }
                }
            }
        }

        // Bulk-insert in chunks for performance
        foreach (array_chunk($slotData, 500) as $chunk) {
            Slot::insert($chunk);
        }

        $this->command->info(sprintf(
            'Seeded: %d clinics | %d doctors | %d slots',
            $clinics->count(),
            $doctors->count(),
            count($slotData)
        ));
    }
}
