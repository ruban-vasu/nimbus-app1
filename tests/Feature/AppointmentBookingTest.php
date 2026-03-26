<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\SlotStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Patient;
use App\Models\Slot;
use App\Services\AppointmentService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class AppointmentBookingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('cache.default', 'array');
    }

    public function test_it_books_an_available_slot_and_marks_it_booked(): void
    {
        $patient = Patient::factory()->create();
        $slot = Slot::factory()->forDoctor($this->createDoctor()->id)->create([
            'date' => now()->addDay()->toDateString(),
            'status' => SlotStatus::Available,
        ]);

        $response = $this->postJson(route('api.appointments.store'), [
            'patient_id' => $patient->id,
            'slot_id' => $slot->id,
            'status' => AppointmentStatus::Confirmed->value,
            'notes' => 'Initial consultation',
        ]);

        $response->assertCreated()
            ->assertJsonPath('data.patient_id', $patient->id)
            ->assertJsonPath('data.slot_id', $slot->id)
            ->assertJsonPath('data.status', AppointmentStatus::Confirmed->value);

        $this->assertDatabaseHas('appointments', [
            'patient_id' => $patient->id,
            'slot_id' => $slot->id,
            'status' => AppointmentStatus::Confirmed->value,
        ]);

        $this->assertDatabaseHas('slots', [
            'id' => $slot->id,
            'status' => SlotStatus::Booked->value,
        ]);
    }

    public function test_it_rejects_double_booking_for_the_same_slot(): void
    {
        $doctor = $this->createDoctor();
        $firstPatient = Patient::factory()->create();
        $secondPatient = Patient::factory()->create();
        $slot = Slot::factory()->forDoctor($doctor->id)->create([
            'date' => now()->addDay()->toDateString(),
            'status' => SlotStatus::Available,
        ]);

        $this->postJson(route('api.appointments.store'), [
            'patient_id' => $firstPatient->id,
            'slot_id' => $slot->id,
        ])->assertCreated();

        $this->postJson(route('api.appointments.store'), [
            'patient_id' => $secondPatient->id,
            'slot_id' => $slot->id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION')
            ->assertJsonPath('error.message', 'The selected slot is no longer available.');

        $this->assertDatabaseCount('appointments', 1);
        $this->assertDatabaseHas('slots', [
            'id' => $slot->id,
            'status' => SlotStatus::Booked->value,
        ]);
    }

    public function test_it_surfaces_lock_contention_during_concurrent_booking_flow(): void
    {
        $patient = Patient::factory()->create();
        $slot = Slot::factory()->forDoctor($this->createDoctor()->id)->create([
            'date' => now()->addDay()->toDateString(),
            'status' => SlotStatus::Available,
        ]);

        $appointmentService = Mockery::mock(AppointmentService::class);
        $appointmentService->shouldReceive('book')
            ->once()
            ->with($patient->id, $slot->id, AppointmentStatus::Confirmed, null)
            ->andThrow(new BusinessRuleException('Unable to acquire slot lock. Please try again.'));

        $this->app->instance(AppointmentService::class, $appointmentService);

        $this->postJson(route('api.appointments.store'), [
            'patient_id' => $patient->id,
            'slot_id' => $slot->id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION')
            ->assertJsonPath('error.message', 'Unable to acquire slot lock. Please try again.');

        $this->assertDatabaseCount('appointments', 0);
        $this->assertDatabaseHas('slots', [
            'id' => $slot->id,
            'status' => SlotStatus::Available->value,
        ]);
    }

    public function test_it_rejects_booking_a_past_slot(): void
    {
        $patient = Patient::factory()->create();
        $slot = Slot::factory()->forDoctor($this->createDoctor()->id)->create([
            'date' => Carbon::yesterday()->toDateString(),
            'start_time' => '09:00:00',
            'end_time' => '09:30:00',
            'status' => SlotStatus::Available,
        ]);

        $this->postJson(route('api.appointments.store'), [
            'patient_id' => $patient->id,
            'slot_id' => $slot->id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION')
            ->assertJsonPath('error.message', 'Appointments cannot be booked for a past date or time.');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_it_rejects_booking_when_patient_has_exceeded_three_appointments_in_24_hours(): void
    {
        Carbon::setTestNow(now());

        $doctor = $this->createDoctor();
        $patient = Patient::factory()->create();

        $existingSlots = Slot::factory(3)->forDoctor($doctor->id)->create([
            'date' => now()->addDay()->toDateString(),
            'status' => SlotStatus::Booked,
        ]);

        foreach ($existingSlots as $slot) {
            Appointment::factory()->create([
                'patient_id' => $patient->id,
                'slot_id' => $slot->id,
                'status' => AppointmentStatus::Confirmed,
                'created_at' => now()->subHours(6),
                'updated_at' => now()->subHours(6),
            ]);
        }

        $newSlot = Slot::factory()->forDoctor($doctor->id)->create([
            'date' => now()->addDay()->toDateString(),
            'status' => SlotStatus::Available,
        ]);

        $this->postJson(route('api.appointments.store'), [
            'patient_id' => $patient->id,
            'slot_id' => $newSlot->id,
        ])
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION')
            ->assertJsonPath('error.message', 'The patient has exceeded the maximum of 3 appointments in 24 hours.');

        $this->assertDatabaseCount('appointments', 3);
        Carbon::setTestNow();
    }

    public function test_it_cancels_an_appointment_more_than_four_hours_before_start_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-26 08:00:00'));

        $patient = Patient::factory()->create();
        $slot = Slot::factory()->forDoctor($this->createDoctor()->id)->create([
            'date' => '2026-03-26',
            'start_time' => '14:30:00',
            'end_time' => '15:00:00',
            'status' => SlotStatus::Booked,
        ]);

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'slot_id' => $slot->id,
            'status' => AppointmentStatus::Confirmed,
        ]);

        $response = $this->patchJson(route('api.appointments.cancel', $appointment->id));

        $response->assertOk()
            ->assertJsonPath('data.id', $appointment->id)
            ->assertJsonPath('data.status', AppointmentStatus::Cancelled->value)
            ->assertJsonPath('data.slot.status', SlotStatus::Available->value);

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => AppointmentStatus::Cancelled->value,
        ]);

        $this->assertDatabaseHas('slots', [
            'id' => $slot->id,
            'status' => SlotStatus::Available->value,
        ]);

        Carbon::setTestNow();
    }

    public function test_it_rejects_cancellation_within_four_hours_of_start_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-26 10:30:00'));

        $patient = Patient::factory()->create();
        $slot = Slot::factory()->forDoctor($this->createDoctor()->id)->create([
            'date' => '2026-03-26',
            'start_time' => '13:30:00',
            'end_time' => '14:00:00',
            'status' => SlotStatus::Booked,
        ]);

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'slot_id' => $slot->id,
            'status' => AppointmentStatus::Confirmed,
        ]);

        $this->patchJson(route('api.appointments.cancel', $appointment->id))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION')
            ->assertJsonPath('error.message', 'Appointments can only be cancelled more than 4 hours before the scheduled time.');

        $this->assertDatabaseHas('appointments', [
            'id' => $appointment->id,
            'status' => AppointmentStatus::Confirmed->value,
        ]);

        $this->assertDatabaseHas('slots', [
            'id' => $slot->id,
            'status' => SlotStatus::Booked->value,
        ]);

        Carbon::setTestNow();
    }

    public function test_it_rejects_cancellation_exactly_four_hours_before_start_time(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-03-26 09:30:00'));

        $patient = Patient::factory()->create();
        $slot = Slot::factory()->forDoctor($this->createDoctor()->id)->create([
            'date' => '2026-03-26',
            'start_time' => '13:30:00',
            'end_time' => '14:00:00',
            'status' => SlotStatus::Booked,
        ]);

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'slot_id' => $slot->id,
            'status' => AppointmentStatus::Confirmed,
        ]);

        $this->patchJson(route('api.appointments.cancel', $appointment->id))
            ->assertStatus(422)
            ->assertJsonPath('error.code', 'BUSINESS_RULE_VIOLATION')
            ->assertJsonPath('error.message', 'Appointments can only be cancelled more than 4 hours before the scheduled time.');

    }

    public function test_it_returns_a_consistent_json_validation_error_payload(): void
    {
        $response = $this->postJson(route('api.appointments.store'), []);

        $response->assertStatus(422)
            ->assertJsonPath('error.code', 'VALIDATION_FAILED')
            ->assertJsonPath('error.message', 'The given data was invalid.')
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                    'details' => ['patient_id', 'slot_id'],
                ],
            ]);
    }

    public function test_it_returns_a_consistent_json_not_found_payload(): void
    {
        $this->getJson('/api/appointments/999999')
            ->assertStatus(404);
    }

    public function test_it_shows_a_specific_appointment(): void
    {
        $patient = Patient::factory()->create(['name' => 'Jane Patient']);
        $slot = Slot::factory()->forDoctor($this->createDoctor()->id)->create([
            'date' => now()->addDay()->toDateString(),
            'status' => SlotStatus::Booked,
        ]);

        $appointment = Appointment::factory()->create([
            'patient_id' => $patient->id,
            'slot_id' => $slot->id,
            'status' => AppointmentStatus::Confirmed,
            'notes' => 'Follow-up',
        ]);

        $this->getJson(route('api.appointments.show', $appointment->id))
            ->assertOk()
            ->assertJsonPath('data.id', $appointment->id)
            ->assertJsonPath('data.patient.name', 'Jane Patient')
            ->assertJsonPath('data.slot.id', $slot->id)
            ->assertJsonPath('data.status', AppointmentStatus::Confirmed->value);
    }

    public function test_it_lists_a_patients_appointment_history_with_pagination(): void
    {
        $patient = Patient::factory()->create();
        $otherPatient = Patient::factory()->create();
        $doctor = $this->createDoctor();

        $patientSlots = Slot::factory(3)->forDoctor($doctor->id)->create([
            'date' => now()->addDays(2)->toDateString(),
            'status' => SlotStatus::Booked,
        ]);

        foreach ($patientSlots as $index => $slot) {
            Appointment::factory()->create([
                'patient_id' => $patient->id,
                'slot_id' => $slot->id,
                'status' => $index === 0 ? AppointmentStatus::Confirmed : AppointmentStatus::Pending,
            ]);
        }

        $otherSlot = Slot::factory()->forDoctor($doctor->id)->create([
            'date' => now()->addDays(3)->toDateString(),
            'status' => SlotStatus::Booked,
        ]);

        Appointment::factory()->create([
            'patient_id' => $otherPatient->id,
            'slot_id' => $otherSlot->id,
            'status' => AppointmentStatus::Confirmed,
        ]);

        $this->getJson(route('api.patients.appointments.index', [
            'id' => $patient->id,
            'per_page' => 2,
        ]))
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('meta.per_page', 2)
            ->assertJsonPath('meta.total', 3);
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