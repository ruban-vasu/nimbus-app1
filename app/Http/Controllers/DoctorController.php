<?php

namespace App\Http\Controllers;

use App\Http\Requests\GenerateDoctorSlotsRequest;
use App\Http\Resources\DoctorResource;
use App\Http\Resources\SlotResource;
use App\Models\Doctor;
use App\Models\Slot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DoctorController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'clinic_id' => ['nullable', 'integer', 'exists:clinics,id'],
            'specialization' => ['nullable', 'string', 'max:255'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $perPage = $validated['per_page'] ?? 15;

        $doctors = Doctor::query()
            ->with('clinic')
            ->when(
                isset($validated['clinic_id']),
                fn ($query) => $query->forClinic((int) $validated['clinic_id'])
            )
            ->when(
                isset($validated['specialization']),
                fn ($query) => $query->specialization($validated['specialization'])
            )
            ->orderBy('name')
            ->paginate($perPage)
            ->withQueryString();

        return DoctorResource::collection($doctors);
    }

    public function showSlots(Request $request, int $id)
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $doctor = Doctor::query()->findOrFail($id);
        $perPage = $validated['per_page'] ?? 15;

        $slots = $doctor->slots()
            ->with('doctor')
            ->available()
            ->when(
                isset($validated['start_date']),
                fn ($query) => $query->whereDate('date', '>=', $validated['start_date'])
            )
            ->when(
                isset($validated['end_date']),
                fn ($query) => $query->whereDate('date', '<=', $validated['end_date'])
            )
            ->orderBy('date')
            ->orderBy('start_time')
            ->paginate($perPage)
            ->withQueryString();

        return SlotResource::collection($slots);
    }

    public function generateSlots(GenerateDoctorSlotsRequest $request, int $id): JsonResponse
    {
        $validated = $request->validated();

        $doctor = Doctor::query()->findOrFail($id);
        $slotDuration = $validated['slot_duration'] ?? 30;
        $windows = [
            [
                'start' => $validated['morning_start'] ?? '08:00',
                'end' => $validated['morning_end'] ?? '12:00',
            ],
            [
                'start' => $validated['afternoon_start'] ?? '13:00',
                'end' => $validated['afternoon_end'] ?? '17:00',
            ],
        ];

        $startDate = Carbon::parse($validated['start_date'])->startOfDay();
        $endDate = Carbon::parse($validated['end_date'])->startOfDay();
        $createdSlots = [];

        for ($date = $startDate->copy(); $date->lte($endDate); $date->addDay()) {
            foreach ($windows as $window) {
                $cursor = Carbon::parse($date->toDateString() . ' ' . $window['start']);
                $limit = Carbon::parse($date->toDateString() . ' ' . $window['end']);

                while ($cursor->copy()->addMinutes($slotDuration)->lte($limit)) {
                    $startTime = $cursor->format('H:i:s');
                    $endTime = $cursor->copy()->addMinutes($slotDuration)->format('H:i:s');

                    $hasOverlap = Slot::query()
                        ->overlapping($doctor->id, $date->toDateString(), $startTime, $endTime)
                        ->exists();

                    if ($hasOverlap) {
                        $cursor->addMinutes($slotDuration);

                        continue;
                    }

                    $slot = Slot::query()->firstOrCreate([
                        'doctor_id' => $doctor->id,
                        'date' => $date->toDateString(),
                        'start_time' => $startTime,
                    ], [
                        'end_time' => $endTime,
                        'duration' => $slotDuration,
                        'status' => 'available',
                    ]);

                    if ($slot->wasRecentlyCreated) {
                        $createdSlots[] = $slot->load('doctor');
                    }

                    $cursor->addMinutes($slotDuration);
                }
            }
        }

        return response()->json([
            'data' => SlotResource::collection(collect($createdSlots))->resolve(),
            'meta' => [
                'message' => 'Slots generated successfully.',
                'count' => count($createdSlots),
            ],
        ], 201);
    }
}