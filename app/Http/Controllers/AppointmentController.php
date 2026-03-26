<?php

namespace App\Http\Controllers;

use App\Enums\AppointmentStatus;
use App\Http\Requests\CancelAppointmentRequest;
use App\Http\Requests\StoreAppointmentRequest;
use App\Http\Resources\AppointmentResource;
use App\Models\Appointment;
use App\Models\Patient;
use App\Services\AppointmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AppointmentController extends Controller
{
    public function show(int $id): AppointmentResource
    {
        $appointment = Appointment::query()
            ->with(['patient', 'slot'])
            ->findOrFail($id);

        return new AppointmentResource($appointment);
    }

    public function store(StoreAppointmentRequest $request, AppointmentService $appointmentService): JsonResponse
    {
        $validated = $request->validated();

        $appointment = $appointmentService->book(
            patientId: $validated['patient_id'],
            slotId: $validated['slot_id'],
            status: isset($validated['status'])
                ? AppointmentStatus::from($validated['status'])
                : AppointmentStatus::Confirmed,
            notes: $validated['notes'] ?? null,
        );

        return (new AppointmentResource($appointment))
            ->response()
            ->setStatusCode(201);
    }

    public function cancel(CancelAppointmentRequest $request, int $id, AppointmentService $appointmentService): JsonResponse
    {
        $appointment = $appointmentService->cancel($id);

        return (new AppointmentResource($appointment))
            ->response()
            ->setStatusCode(200);
    }

    public function patientAppointments(Request $request, int $id)
    {
        $validated = $request->validate([
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $patient = Patient::query()->findOrFail($id);
        $perPage = $validated['per_page'] ?? 15;

        $appointments = $patient->appointments()
            ->with(['patient', 'slot'])
            ->latest()
            ->paginate($perPage)
            ->withQueryString();

        return AppointmentResource::collection($appointments);
    }
}