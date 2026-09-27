<?php

namespace App\Models;

use App\Enums\ContactChannel;
use App\Enums\CustomerSource;
use App\Enums\CustomerType;
use App\Enums\LifecycleStage;
use App\Models\Concerns\VisibleToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Customer extends Model
{
    use HasFactory, LogsActivity, SoftDeletes, VisibleToUser;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => CustomerType::class,
            'source' => CustomerSource::class,
            'lifecycle_stage' => LifecycleStage::class,
            'preferred_contact_channel' => ContactChannel::class,
            'date_of_birth' => 'date',
            'passport_number' => 'encrypted',
            'passport_expiry' => 'date',
            'marketing_consent' => 'boolean',
            'consent_at' => 'datetime',
            'last_contacted_at' => 'datetime',
            'anonymised_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Never write the passport number into the audit log.
        return LogOptions::defaults()
            ->logOnly(['first_name', 'last_name', 'email', 'phone', 'lifecycle_stage', 'assigned_to', 'marketing_consent', 'source'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    protected function fullName(): Attribute
    {
        return Attribute::get(fn () => trim("{$this->first_name} {$this->last_name}"));
    }

    /** Company for corporate/group accounts, person name otherwise. */
    protected function displayName(): Attribute
    {
        return Attribute::get(fn () => $this->type !== CustomerType::Individual && $this->company_name
            ? $this->company_name
            : $this->full_name);
    }

    protected function initials(): Attribute
    {
        return Attribute::get(function () {
            $words = preg_split('/\s+/', $this->display_name) ?: [];

            return mb_strtoupper(collect($words)->take(2)->map(fn ($w) => mb_substr($w, 0, 1))->implode(''));
        });
    }

    public function passportExpiresSoon(): bool
    {
        return $this->passport_expiry !== null && $this->passport_expiry->lte(now()->addMonths(6));
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (blank($term)) {
            return $query;
        }

        $like = '%'.trim($term).'%';

        return $query->where(fn (Builder $q) => $q
            ->where('first_name', 'like', $like)
            ->orWhere('last_name', 'like', $like)
            ->orWhere('company_name', 'like', $like)
            ->orWhere('email', 'like', $like)
            ->orWhere('phone', 'like', $like)
            ->orWhereRaw("(first_name || ' ' || last_name) like ?", [$like]));
    }

    public function scopeConsented(Builder $query): Builder
    {
        return $query->where('marketing_consent', true)->whereNull('anonymised_at');
    }

    public function consultant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function preference(): HasOne
    {
        return $this->hasOne(CustomerPreference::class);
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function enquiries(): HasMany
    {
        return $this->hasMany(Enquiry::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function interactions(): HasMany
    {
        return $this->hasMany(Interaction::class);
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(ServiceTicket::class);
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(Feedback::class);
    }
}
