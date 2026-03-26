<?php

namespace App\Http\Controllers;

use App\Models\Patient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PatientController extends Controller
{
    public function registerOrFind(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'date_of_birth' => ['required', 'date'],
            'insurance_provider' => ['nullable', 'string', 'max:255'],
        ]);

        $patient = Patient::query()->firstOrCreate(
            ['email' => $validated['email']],
            [
                'name' => $validated['name'],
                'phone' => $validated['phone'],
                'date_of_birth' => $validated['date_of_birth'],
                'insurance_provider' => $validated['insurance_provider'] ?? null,
            ]
        );

        return response()->json([
            'data' => [
                'id' => $patient->id,
                'name' => $patient->name,
                'email' => $patient->email,
            ],
        ]);
    }
}