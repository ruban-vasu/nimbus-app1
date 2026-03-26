<?php

namespace App\Models;

use App\Enums\SlotStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Slot extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'doctor_id',
        'date',
        'start_time',
        'end_time',
        'duration',
        'status',
    ];

    protected $casts = [
        'status' => SlotStatus::class,
        'date'   => 'date',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function appointment(): HasOne
    {
        return $this->hasOne(Appointment::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    /** Slot::available()->get() */
    public function scopeAvailable(Builder $query): Builder
    {
        return $query->where('status', SlotStatus::Available);
    }

    /** Slot::forDoctor($id)->get() */
    public function scopeForDoctor(Builder $query, int $doctorId): Builder
    {
        return $query->where('doctor_id', $doctorId);
    }

    /** Slot::onDate('2026-04-01')->get() */
    public function scopeOnDate(Builder $query, string $date): Builder
    {
        return $query->whereDate('date', $date);
    }

    /**
     * Chainable convenience: available slots for a doctor on a specific date.
     *
     * Usage: Slot::availableFor($doctorId, '2026-04-01')->get()
     */
    public function scopeAvailableFor(Builder $query, int $doctorId, string $date): Builder
    {
        return $query->forDoctor($doctorId)->onDate($date)->available();
    }

    public function scopeOverlapping(Builder $query, int $doctorId, string $date, string $startTime, string $endTime): Builder
    {
        return $query
            ->where('doctor_id', $doctorId)
            ->whereDate('date', $date)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime);
    }

    // ── Helpers ────────────────────────────────────────────────────────────

    public function isAvailable(): bool
    {
        return $this->status->isBookable();
    }
}
