<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasRoles, Notifiable;

    protected $fillable = [
        'opd_id',
        'name',
        'nip',
        'email',
        'password',
        'position',
        'phone_number',
        'is_active',
        'must_change_password',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    /**
     * Staf adalah pengguna yang boleh melihat seluruh RFC (Admin, Change Manager, Super Admin).
     */
    public function isStaff(): bool
    {
        return $this->can('rfc.view_all');
    }

    /**
     * Akun Tim Rilis hanya memperoleh akses ke RFC yang ditugaskan kepadanya.
     */
    public function isReleaseTeam(): bool
    {
        return $this->hasRole('Tim Rilis');
    }

    public function opd(): BelongsTo
    {
        return $this->belongsTo(Opd::class);
    }

    public function releaseTeamMember(): HasOne
    {
        return $this->hasOne(ReleaseTeamMember::class);
    }

    public function submittedChangeRequests(): HasMany
    {
        return $this->hasMany(ChangeRequest::class, 'submitted_by_id');
    }

    public function createdChangeRequests(): HasMany
    {
        return $this->hasMany(ChangeRequest::class, 'created_by_id');
    }
}
