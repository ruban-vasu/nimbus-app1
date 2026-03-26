<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Doctor extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'clinic_id',
        'name',
        'specialization',
        'consultation_fee',
        'is_active',
    ];

    protected $casts = [
        'consultation_fee' => 'decimal:2',
        'is_active'        => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function clinic(): BelongsTo
    {
        return $this->belongsTo(Clinic::class);
    }

    public function slots(): HasMany
    {
        return $this->hasMany(Slot::class);
    }

    public function appointments(): HasManyThrough
    {
        return $this->hasManyThrough(
            Appointment::class,
            Slot::class,
            'doctor_id',
            'slot_id',
            'id',
            'id',
        );
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeSpecialization(Builder $query, string $specialization): Builder
    {
        return $query->where('specialization', $specialization);
    }

    public function scopeForClinic(Builder $query, int $clinicId): Builder
    {
        return $query->where('clinic_id', $clinicId);
    }
}
