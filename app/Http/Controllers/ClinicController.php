<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Enums\SlotStatus;
use App\Http\Resources\SlotResource;
use App\Models\Appointment;
use App\Models\Clinic;
use App\Models\Slot;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;

class ClinicController extends Controller
{
    public function dashboard(int $id): JsonResponse
    {
        $clinic = Clinic::query()->findOrFail($id);

        $today = Carbon::today();

        $doctorIds = $clinic->doctors()->pluck('id');

        $todaySlots = Slot::query()
            ->whereIn('doctor_id', $doctorIds)
            ->whereDate('date', $today)
            ->count();

        $availableTodaySlots = Slot::query()
            ->whereIn('doctor_id', $doctorIds)
            ->whereDate('date', $today)
            ->where('status', SlotStatus::Available)
            ->count();

        $bookedTodaySlots = Slot::query()
            ->whereIn('doctor_id', $doctorIds)
            ->whereDate('date', $today)
            ->where('status', SlotStatus::Booked)
            ->count();

        $blockedTodaySlots = Slot::query()
            ->whereIn('doctor_id', $doctorIds)
            ->whereDate('date', $today)
            ->where('status', SlotStatus::Blocked)
            ->count();

        $todayAppointments = Appointment::query()
            ->whereHas('slot', function ($query) use ($doctorIds, $today) {
                $query->whereIn('doctor_id', $doctorIds)
                    ->whereDate('date', $today);
            })
            ->count();

        $confirmedTodayAppointments = Appointment::query()
            ->where('status', AppointmentStatus::Confirmed)
            ->whereHas('slot', function ($query) use ($doctorIds, $today) {
                $query->whereIn('doctor_id', $doctorIds)
                    ->whereDate('date', $today);
            })
            ->count();

        $upcomingSlots = Slot::query()
            ->with('doctor')
            ->whereIn('doctor_id', $doctorIds)
            ->where(function ($query) use ($today) {
                $query->whereDate('date', '>', $today)
                    ->orWhere(function ($subQuery) use ($today) {
                        $subQuery->whereDate('date', $today)
                            ->where('start_time', '>=', now()->format('H:i:s'));
                    });
            })
            ->orderBy('date')
            ->orderBy('start_time')
            ->limit(10)
            ->get();

        return response()->json([
            'data' => [
                'clinic' => [
                    'id' => $clinic->id,
                    'name' => $clinic->name,
                    'phone' => $clinic->phone,
                ],
                'today' => $today->toDateString(),
                'stats' => [
                    'doctor_count' => $doctorIds->count(),
                    'today_slots' => $todaySlots,
                    'available_today_slots' => $availableTodaySlots,
                    'booked_today_slots' => $bookedTodaySlots,
                    'blocked_today_slots' => $blockedTodaySlots,
                    'today_appointments' => $todayAppointments,
                    'confirmed_today_appointments' => $confirmedTodayAppointments,
                ],
                'upcoming_slots' => SlotResource::collection($upcomingSlots)->resolve(),
            ],
        ]);
    }
}