<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\SlotStatus;
use App\Events\AppointmentBooked;
use App\Events\AppointmentCancelled;
use App\Exceptions\BusinessRuleException;
use App\Models\Appointment;
use App\Models\Patient;
use App\Models\Slot;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class AppointmentService
{
    public function __construct(
        protected DatabaseManager $db,
    ) {
    }

    /**
     * Book a slot for a patient.
     *
        * Locking strategy:
        * We combine a Redis cache lock with a database transaction and row-level lock.
        * The Redis lock prevents two app nodes from processing the same slot at once,
        * while lockForUpdate() protects correctness at the database level inside the
        * transaction. This costs a little more latency and operational complexity than
        * using only one mechanism, but it gives strong protection against double-booking
        * under concurrent requests and across horizontally scaled workers.
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
                        throw new BusinessRuleException('Appointments cannot be booked for a past date or time.');
                    }

                    if ($slot->status !== SlotStatus::Available) {
                        throw new BusinessRuleException('The selected slot is no longer available.');
                    }

                    if ($slot->appointment()->exists()) {
                        throw new BusinessRuleException('The selected slot has already been booked.');
                    }

                    if ($this->hasExceededBookingLimit($patient)) {
                        throw new BusinessRuleException('The patient already has the maximum of 3 upcoming appointments.');
                    }

                    if ($this->hasOverlappingAppointment($patient, $slot)) {
                        throw new BusinessRuleException('The patient already has an overlapping appointment.');
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

                    $appointment = $appointment->fresh(['patient', 'slot.doctor']);

                    AppointmentBooked::dispatch($appointment);

                    return $appointment;
                });
            });
        } catch (LockTimeoutException) {
            throw new BusinessRuleException('Unable to acquire slot lock. Please try again.');
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
                throw new BusinessRuleException('This appointment cannot be cancelled.');
            }

            if (! $appointment->slot) {
                throw new BusinessRuleException('The appointment slot could not be found.');
            }

            if (! $this->isCancellableMoreThanFourHoursAway($appointment->slot)) {
                throw new BusinessRuleException('Appointments can only be cancelled more than 4 hours before the scheduled time.');
            }

            $appointment->update([
                'status' => AppointmentStatus::Cancelled,
            ]);

            $appointment->slot()->update([
                'status' => SlotStatus::Available,
            ]);

            $appointment = $appointment->fresh(['patient', 'slot.doctor']);

            AppointmentCancelled::dispatch($appointment);

            return $appointment;
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
            ->whereIn('status', [
                AppointmentStatus::Pending,
                AppointmentStatus::Confirmed,
            ])
            ->whereHas('slot', function ($query) {
                $query->where(function ($slotQuery) {
                    $slotQuery->whereDate('date', '>', now()->toDateString())
                        ->orWhere(function ($sameDayQuery) {
                            $sameDayQuery->whereDate('date', now()->toDateString())
                                ->where('start_time', '>=', now()->format('H:i:s'));
                        });
                });
            })
            ->count() >= 3;
    }

    protected function hasOverlappingAppointment(Patient $patient, Slot $slot): bool
    {
        return $patient->appointments()
            ->whereIn('status', [
                AppointmentStatus::Pending,
                AppointmentStatus::Confirmed,
            ])
            ->whereHas('slot', function ($query) use ($slot) {
                $query->whereDate('date', $slot->date->toDateString())
                    ->where('start_time', '<', $slot->end_time)
                    ->where('end_time', '>', $slot->start_time);
            })
            ->exists();
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