<?php

namespace App\Listeners;

use App\Events\AppointmentCancelled;
use App\Models\AuditLog;
use Illuminate\Support\Facades\Log;

class LogAppointmentCancelledAuditTrail
{
    public function handle(AppointmentCancelled $event): void
    {
        AuditLog::query()->create([
            'action' => 'appointment.cancelled',
            'description' => sprintf(
                'Appointment %d was cancelled for patient %d.',
                $event->appointment->id,
                $event->appointment->patient_id,
            ),
        ]);

        Log::info('Appointment cancelled audit recorded.', [
            'appointment_id' => $event->appointment->id,
        ]);
    }
}