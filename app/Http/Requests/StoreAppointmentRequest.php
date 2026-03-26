<?php

namespace App\Http\Requests;

use App\Enums\AppointmentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAppointmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'slot_id' => ['required', 'integer', 'exists:slots,id'],
            'status' => ['nullable', 'string', Rule::in(AppointmentStatus::values())],
            'notes' => ['nullable', 'string'],
        ];
    }
}