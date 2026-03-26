<?php

use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\DoctorController;
use App\Http\Controllers\PatientController;
use Illuminate\Support\Facades\Route;

Route::get('/doctors', [DoctorController::class, 'index'])
    ->name('api.doctors.index');

Route::get('/doctors/{id}/slots', [DoctorController::class, 'showSlots'])
    ->whereNumber('id')
    ->name('api.doctors.slots.index');

Route::post('/doctors/{id}/slots/generate', [DoctorController::class, 'generateSlots'])
    ->whereNumber('id')
    ->name('api.doctors.slots.generate');

Route::post('/appointments', [AppointmentController::class, 'store'])
    ->name('api.appointments.store');

Route::get('/appointments/{id}', [AppointmentController::class, 'show'])
    ->whereNumber('id')
    ->name('api.appointments.show');

Route::patch('/appointments/{id}/cancel', [AppointmentController::class, 'cancel'])
    ->whereNumber('id')
    ->name('api.appointments.cancel');

Route::get('/patients/{id}/appointments', [AppointmentController::class, 'patientAppointments'])
    ->whereNumber('id')
    ->name('api.patients.appointments.index');

Route::post('/patients/register-or-find', [PatientController::class, 'registerOrFind'])
    ->name('api.patients.register-or-find');