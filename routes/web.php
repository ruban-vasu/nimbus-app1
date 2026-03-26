<?php

use App\Http\Controllers\AppointmentBookingController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::post('/appointments/book', AppointmentBookingController::class)
    ->name('appointments.book');
