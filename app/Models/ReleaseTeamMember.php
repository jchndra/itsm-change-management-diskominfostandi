<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ReleaseTeamMember extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'name',
        'nip_or_nik',
        'role_title',
        'phone_number',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeLinked(Builder $query): Builder
    {
        return $query->whereNotNull('user_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function releaseTasks(): HasMany
    {
        return $this->hasMany(ReleaseTask::class);
    }

    /**
     * Anggota hanya layak ditugaskan jika aktif, sudah tertaut ke akun aktif,
     * dan akun tersebut benar-benar memiliki role Tim Rilis.
     */
    public function isEligibleForAssignment(): bool
    {
        if (! $this->is_active || $this->user_id === null) {
            return false;
        }

        $user = $this->user;

        return $user !== null
            && $user->is_active
            && $user->hasRole('Tim Rilis');
    }
}
