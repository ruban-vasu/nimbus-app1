<?php

namespace Tests\Feature;

use App\Enums\SlotStatus;
use App\Models\Clinic;
use App\Models\Slot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DoctorApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_doctors_with_filters_and_pagination(): void
    {
        $clinicA = Clinic::factory()->create(['name' => 'Clinic A']);
        $clinicB = Clinic::factory()->create(['name' => 'Clinic B']);

        $clinicA->doctors()->create([
            'name' => 'Dr. Alice',
            'specialization' => 'Cardiology',
            'consultation_fee' => 500,
            'is_active' => true,
        ]);

        $clinicA->doctors()->create([
            'name' => 'Dr. Bob',
            'specialization' => 'Dermatology',
            'consultation_fee' => 400,
            'is_active' => true,
        ]);

        $clinicB->doctors()->create([
            'name' => 'Dr. Carol',
            'specialization' => 'Cardiology',
            'consultation_fee' => 600,
            'is_active' => true,
        ]);

        $response = $this->getJson(route('api.doctors.index', [
            'clinic_id' => $clinicA->id,
            'specialization' => 'Cardiology',
            'per_page' => 10,
        ]));

        $response->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.name', 'Dr. Alice')
            ->assertJsonPath('data.0.clinic.name', 'Clinic A')
            ->assertJsonPath('meta.per_page', 10);
    }

    public function test_it_lists_available_slots_for_a_doctor_with_date_range_filter(): void
    {
        $doctor = $this->createDoctor();

        Slot::factory()->forDoctor($doctor->id)->create([
            'date' => '2026-03-27',
            'status' => SlotStatus::Available,
        ]);

        Slot::factory()->forDoctor($doctor->id)->create([
            'date' => '2026-03-29',
            'status' => SlotStatus::Available,
        ]);

        Slot::factory()->forDoctor($doctor->id)->create([
            'date' => '2026-04-03',
            'status' => SlotStatus::Available,
        ]);

        Slot::factory()->forDoctor($doctor->id)->create([
            'date' => '2026-03-28',
            'status' => SlotStatus::Booked,
        ]);

        $response = $this->getJson(route('api.doctors.slots.index', [
            'id' => $doctor->id,
            'start_date' => '2026-03-27',
            'end_date' => '2026-03-30',
            'per_page' => 10,
        ]));

        $response->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.doctor_id', $doctor->id)
            ->assertJsonPath('data.0.status', SlotStatus::Available->value)
            ->assertJsonPath('meta.per_page', 10);
    }

    public function test_it_generates_slots_for_a_doctor_in_a_date_range(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-26 08:00:00'));

        $doctor = $this->createDoctor();

        $response = $this->postJson(route('api.doctors.slots.generate', $doctor->id), [
            'start_date' => '2026-03-27',
            'end_date' => '2026-03-28',
            'slot_duration' => 30,
            'morning_start' => '09:00',
            'morning_end' => '10:00',
            'afternoon_start' => '14:00',
            'afternoon_end' => '15:00',
        ]);

        $response->assertCreated()
            ->assertJsonPath('meta.message', 'Slots generated successfully.')
            ->assertJsonPath('meta.count', 8)
            ->assertJsonCount(8, 'data');

        $this->assertDatabaseCount('slots', 8);

        $this->assertDatabaseHas('slots', [
            'doctor_id' => $doctor->id,
            'date' => '2026-03-27',
            'start_time' => '09:00:00',
            'end_time' => '09:30:00',
            'status' => SlotStatus::Available->value,
        ]);

        Carbon::setTestNow();
    }

    public function test_it_does_not_generate_overlapping_slots_for_the_same_doctor(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-26 08:00:00'));

        $doctor = $this->createDoctor();

        Slot::factory()->forDoctor($doctor->id)->create([
            'date' => '2026-03-27',
            'start_time' => '09:30:00',
            'end_time' => '10:00:00',
            'duration' => 30,
            'status' => SlotStatus::Available,
        ]);

        $response = $this->postJson(route('api.doctors.slots.generate', $doctor->id), [
            'start_date' => '2026-03-27',
            'end_date' => '2026-03-27',
            'slot_duration' => 30,
            'morning_start' => '09:00',
            'morning_end' => '10:30',
            'afternoon_start' => '14:00',
            'afternoon_end' => '14:00',
        ]);

        $response->assertCreated()
            ->assertJsonPath('meta.count', 2);

        $this->assertDatabaseHas('slots', [
            'doctor_id' => $doctor->id,
            'date' => '2026-03-27',
            'start_time' => '09:00:00',
            'end_time' => '09:30:00',
        ]);

        $this->assertDatabaseHas('slots', [
            'doctor_id' => $doctor->id,
            'date' => '2026-03-27',
            'start_time' => '10:00:00',
            'end_time' => '10:30:00',
        ]);

        $this->assertDatabaseCount('slots', 3);

        Carbon::setTestNow();
    }

    protected function createDoctor()
    {
        $clinic = Clinic::factory()->create();

        return $clinic->doctors()->create([
            'name' => 'Dr. Test Doctor',
            'specialization' => 'General Practice',
            'consultation_fee' => 500,
            'is_active' => true,
        ]);
    }
}