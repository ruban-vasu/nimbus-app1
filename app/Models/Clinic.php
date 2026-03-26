<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\SoftDeletes;

class Clinic extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'address',
        'phone',
        'operating_hours',
        'is_active',
    ];

    protected $casts = [
        'operating_hours' => 'array',   // JSON column → PHP array
        'is_active'       => 'boolean',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function doctors(): HasMany
    {
        return $this->hasMany(Doctor::class);
    }

    /** All appointment slots across all doctors in this clinic. */
    public function slots(): HasManyThrough
    {
        return $this->hasManyThrough(Slot::class, Doctor::class);
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
