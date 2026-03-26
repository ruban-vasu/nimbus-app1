<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\SlotStatus;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Slot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ClinicDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_returns_clinic_dashboard_with_todays_stats_and_upcoming_slots(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-26 09:00:00'));

        $clinic = Clinic::factory()->create(['name' => 'Nimbus Central Clinic']);
        $otherClinic = Clinic::factory()->create();

        $doctorA = $clinic->doctors()->create([
            'name' => 'Dr. Asha',
            'specialization' => 'Cardiology',
            'consultation_fee' => 700,
            'is_active' => true,
        ]);

        $doctorB = $clinic->doctors()->create([
            'name' => 'Dr. Vivek',
            'specialization' => 'Dermatology',
            'consultation_fee' => 600,
            'is_active' => true,
        ]);

        $externalDoctor = $otherClinic->doctors()->create([
            'name' => 'Dr. External',
            'specialization' => 'Orthopedics',
            'consultation_fee' => 800,
            'is_active' => true,
        ]);

        $availableToday = Slot::factory()->forDoctor($doctorA->id)->create([
            'date' => '2026-03-26',
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
            'duration' => 30,
            'status' => SlotStatus::Available,
        ]);

        $bookedToday = Slot::factory()->forDoctor($doctorB->id)->create([
            'date' => '2026-03-26',
            'start_time' => '11:00:00',
            'end_time' => '11:30:00',
            'duration' => 30,
            'status' => SlotStatus::Booked,
        ]);

        $blockedToday = Slot::factory()->forDoctor($doctorA->id)->create([
            'date' => '2026-03-26',
            'start_time' => '12:00:00',
            'end_time' => '12:30:00',
            'duration' => 30,
            'status' => SlotStatus::Blocked,
        ]);

        $futureUpcoming = Slot::factory()->forDoctor($doctorA->id)->create([
            'date' => '2026-03-27',
            'start_time' => '09:00:00',
            'end_time' => '09:30:00',
            'duration' => 30,
            'status' => SlotStatus::Available,
        ]);

        Slot::factory()->forDoctor($externalDoctor->id)->create([
            'date' => '2026-03-26',
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
            'duration' => 30,
            'status' => SlotStatus::Booked,
        ]);

        $patient = Patient::factory()->create();

        Appointment::factory()->create([
            'patient_id' => $patient->id,
            'slot_id' => $bookedToday->id,
            'status' => AppointmentStatus::Confirmed,
        ]);

        Appointment::factory()->create([
            'patient_id' => $patient->id,
            'slot_id' => $blockedToday->id,
            'status' => AppointmentStatus::Pending,
        ]);

        $response = $this->getJson(route('api.clinics.dashboard', $clinic->id));

        $response->assertOk()
            ->assertJsonPath('data.clinic.id', $clinic->id)
            ->assertJsonPath('data.clinic.name', 'Nimbus Central Clinic')
            ->assertJsonPath('data.stats.doctor_count', 2)
            ->assertJsonPath('data.stats.today_slots', 3)
            ->assertJsonPath('data.stats.available_today_slots', 1)
            ->assertJsonPath('data.stats.booked_today_slots', 1)
            ->assertJsonPath('data.stats.blocked_today_slots', 1)
            ->assertJsonPath('data.stats.today_appointments', 2)
            ->assertJsonPath('data.stats.confirmed_today_appointments', 1)
            ->assertJsonCount(4, 'data.upcoming_slots');

        $upcomingSlotIds = collect($response->json('data.upcoming_slots'))->pluck('id');

        $this->assertTrue($upcomingSlotIds->contains($availableToday->id));
        $this->assertTrue($upcomingSlotIds->contains($bookedToday->id));
        $this->assertTrue($upcomingSlotIds->contains($blockedToday->id));
        $this->assertTrue($upcomingSlotIds->contains($futureUpcoming->id));

        Carbon::setTestNow();
    }
}