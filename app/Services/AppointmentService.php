<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\SlotStatus;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Slot;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use RuntimeException;

class AppointmentService
{
    public function __construct(
        protected DatabaseManager $db,
    ) {
    }

    /**
     * Book a slot for a patient.
     *
     * The flow is intentionally defensive:
     * 1. Acquire a Redis lock scoped to the slot ID.
     * 2. Start a database transaction.
     * 3. Re-read the slot with a DB row lock.
     * 4. Create the appointment.
     * 5. Mark the slot as booked.
     */
    public function book(int $patientId, int $slotId, AppointmentStatus $status = AppointmentStatus::Confirmed, ?string $notes = null): Appointment
    {
        $lock = $this->lockStore()->lock($this->slotLockKey($slotId), 10);

        try {
            return $lock->block(5, function () use ($patientId, $slotId, $status, $notes) {
                return $this->db->transaction(function () use ($patientId, $slotId, $status, $notes) {
                    $patient = Patient::query()->findOrFail($patientId);

                    $slot = Slot::query()
                        ->whereKey($slotId)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if ($this->isPastSlot($slot)) {
                        throw new RuntimeException('Appointments cannot be booked for a past date or time.');
                    }

                    if ($slot->status !== SlotStatus::Available) {
                        throw new RuntimeException('The selected slot is no longer available.');
                    }

                    if ($slot->appointment()->exists()) {
                        throw new RuntimeException('The selected slot has already been booked.');
                    }

                    if ($this->hasExceededBookingLimit($patient)) {
                        throw new RuntimeException('The patient has exceeded the maximum of 3 appointments in 24 hours.');
                    }

                    $appointment = Appointment::query()->create([
                        'patient_id' => $patientId,
                        'slot_id' => $slot->id,
                        'status' => $status,
                        'notes' => $notes,
                    ]);

                    $slot->update([
                        'status' => SlotStatus::Booked,
                    ]);

                    return $appointment->fresh(['patient', 'slot']);
                });
            });
        } catch (LockTimeoutException) {
            throw new RuntimeException('Unable to acquire slot lock. Please try again.');
        }
    }

    public function cancel(int $appointmentId): Appointment
    {
        return $this->db->transaction(function () use ($appointmentId) {
            $appointment = Appointment::query()
                ->with('slot', 'patient')
                ->whereKey($appointmentId)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $appointment->status->isCancellable()) {
                throw new RuntimeException('This appointment cannot be cancelled.');
            }

            if (! $appointment->slot) {
                throw new RuntimeException('The appointment slot could not be found.');
            }

            if (! $this->isCancellableMoreThanFourHoursAway($appointment->slot)) {
                throw new RuntimeException('Appointments can only be cancelled more than 4 hours before the scheduled time.');
            }

            $appointment->update([
                'status' => AppointmentStatus::Cancelled,
            ]);

            $appointment->slot()->update([
                'status' => SlotStatus::Available,
            ]);

            return $appointment->fresh(['patient', 'slot']);
        });
    }

    protected function lockStore(): CacheRepository
    {
        $redisClient = (string) config('database.redis.client');

        if ($redisClient !== 'phpredis' || extension_loaded('redis')) {
            return Cache::store('redis');
        }

        return Cache::store(config('cache.default'));
    }

    protected function slotLockKey(int $slotId): string
    {
        return "appointments:slot:{$slotId}:lock";
    }

    protected function isPastSlot(Slot $slot): bool
    {
        $slotDateTime = Carbon::parse(sprintf(
            '%s %s',
            $slot->date->toDateString(),
            $slot->start_time,
        ));

        return $slotDateTime->isPast();
    }

    protected function hasExceededBookingLimit(Patient $patient): bool
    {
        return $patient->appointments()
            ->where('created_at', '>=', now()->subDay())
            ->count() >= 3;
    }

    protected function isCancellableMoreThanFourHoursAway(Slot $slot): bool
    {
        $slotDateTime = Carbon::parse(sprintf(
            '%s %s',
            $slot->date->toDateString(),
            $slot->start_time,
        ));

        return now()->diffInHours($slotDateTime, false) > 4;
    }
}