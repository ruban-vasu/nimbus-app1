<?php

namespace App\Listeners;

use App\Events\AppointmentBooked;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

class SendBookingConfirmationEmail implements ShouldQueue
{
    use InteractsWithQueue;

    public function handle(AppointmentBooked $event): void
    {
        Log::info('Queued booking confirmation email.', [
            'appointment_id' => $event->appointment->id,
            'patient_id' => $event->appointment->patient_id,
        ]);
    }
}