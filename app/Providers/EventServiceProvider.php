<?php

namespace App\Providers;

use App\Events\AppointmentBooked;
use App\Events\AppointmentCancelled;
use App\Listeners\LogAppointmentBookedAuditTrail;
use App\Listeners\LogAppointmentCancelledAuditTrail;
use App\Listeners\SendBookingConfirmationEmail;
use Illuminate\Foundation\Support\Providers\EventServiceProvider as ServiceProvider;

class EventServiceProvider extends ServiceProvider
{
    protected $listen = [
        AppointmentBooked::class => [
            SendBookingConfirmationEmail::class,
            LogAppointmentBookedAuditTrail::class,
        ],
        AppointmentCancelled::class => [
            LogAppointmentCancelledAuditTrail::class,
        ],
    ];
}