<?php

namespace App\Models;

use App\Enums\Role;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, LogsActivity, Notifiable;

    protected $fillable = [
        'name', 'email', 'password', 'job_title', 'phone', 'avatar_color', 'is_active',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'email', 'job_title', 'is_active'])->logOnlyDirty();
    }

    public function initials(): string
    {
        return collect(explode(' ', $this->name))
            ->map(fn ($part) => mb_substr($part, 0, 1))
            ->take(2)
            ->implode('');
    }

    public function firstName(): string
    {
        return explode(' ', $this->name)[0];
    }

    public function primaryRole(): ?Role
    {
        $name = $this->getRoleNames()->first();

        return $name ? Role::tryFrom($name) : null;
    }

    /** Owners and managers see everyone's records. */
    public function isManagerOrAbove(): bool
    {
        return $this->hasAnyRole([Role::Owner->value, Role::Manager->value]);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class, 'assigned_to');
    }

    public function enquiries(): HasMany
    {
        return $this->hasMany(Enquiry::class, 'assigned_to');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'assigned_to');
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class, 'consultant_id');
    }
}
