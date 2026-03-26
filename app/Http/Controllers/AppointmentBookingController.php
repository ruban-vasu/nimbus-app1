<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Services\AppointmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use RuntimeException;

class AppointmentBookingController extends Controller
{
    public function __invoke(Request $request, AppointmentService $appointmentService): JsonResponse
    {
        $validated = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'slot_id' => ['required', 'integer', 'exists:slots,id'],
            'status' => ['nullable', 'string', Rule::in(AppointmentStatus::values())],
            'notes' => ['nullable', 'string'],
        ]);

        try {
            $appointment = $appointmentService->book(
                patientId: $validated['patient_id'],
                slotId: $validated['slot_id'],
                status: isset($validated['status'])
                    ? AppointmentStatus::from($validated['status'])
                    : AppointmentStatus::Confirmed,
                notes: $validated['notes'] ?? null,
            );

            return response()->json([
                'message' => 'Appointment booked successfully.',
                'data' => $appointment,
            ], 201);
        } catch (RuntimeException $exception) {
            return response()->json([
                'message' => $exception->getMessage(),
            ], 422);
        }
    }
}