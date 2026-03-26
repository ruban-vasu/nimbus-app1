<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Enums\SlotStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOneThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Appointment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'slot_id',
        'status',
        'notes',
    ];

    protected $casts = [
        'status' => AppointmentStatus::class,
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function slot(): BelongsTo
    {
        return $this->belongsTo(Slot::class);
    }

    /** Shortcut: appointment->doctor without loading the slot first. */
    public function doctor(): HasOneThrough
    {
        return $this->hasOneThrough(
            Doctor::class,
            Slot::class,
            'id',         // slots.id
            'id',         // doctors.id
            'slot_id',    // appointments.slot_id
            'doctor_id',  // slots.doctor_id
        );
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', AppointmentStatus::Pending);
    }

    public function scopeConfirmed(Builder $query): Builder
    {
        return $query->where('status', AppointmentStatus::Confirmed);
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->whereIn('status', [
            AppointmentStatus::Pending->value,
            AppointmentStatus::Confirmed->value,
        ]);
    }

    // ── Actions ────────────────────────────────────────────────────────────

    /**
     * Cancel the appointment and release the slot back to available.
     * Returns false if the current status doesn't allow cancellation.
     */
    public function cancel(): bool
    {
        if (! $this->status->isCancellable()) {
            return false;
        }

        $this->update(['status' => AppointmentStatus::Cancelled]);
        $this->slot()->update(['status' => SlotStatus::Available]);

        return true;
    }
}
