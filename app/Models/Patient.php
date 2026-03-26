<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name',
        'email',
        'phone',
        'date_of_birth',
        'insurance_provider',
    ];

    protected $casts = [
        'date_of_birth' => 'date',
    ];

    // ── Relationships ──────────────────────────────────────────────────────

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function upcomingAppointments(): HasMany
    {
        return $this->appointments()
                    ->whereIn('status', [
                        AppointmentStatus::Pending->value,
                        AppointmentStatus::Confirmed->value,
                    ])
                    ->orderBy('created_at');
    }

    // ── Scopes ─────────────────────────────────────────────────────────────

    /** scope: Patient::search('john') */
    public function scopeSearch(Builder $query, string $term): Builder
    {
        return $query->where(function (Builder $q) use ($term) {
            $q->where('name', 'like', "%{$term}%")
              ->orWhere('email', 'like', "%{$term}%")
              ->orWhere('phone', 'like', "%{$term}%");
        });
    }

    // ── Accessors ──────────────────────────────────────────────────────────

    /** Convenience: patient age derived from date_of_birth. */
    public function getAgeAttribute(): int
    {
        return $this->date_of_birth->age;
    }
}
