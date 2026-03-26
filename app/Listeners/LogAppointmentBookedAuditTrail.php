<?php

namespace App\Listeners;

use App\Events\AppointmentBooked;
use App\Models\AuditLog;

class LogAppointmentBookedAuditTrail
{
    public function handle(AppointmentBooked $event): void
    {
        AuditLog::query()->create([
            'action' => 'appointment.booked',
            'description' => sprintf(
                'Appointment %d was created for patient %d on slot %d.',
                $event->appointment->id,
                $event->appointment->patient_id,
                $event->appointment->slot_id,
            ),
        ]);
    }
}