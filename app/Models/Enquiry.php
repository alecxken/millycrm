<?php

namespace App\Models;

use App\Enums\CustomerSource;
use App\Enums\EnquiryStatus;
use App\Enums\TravelStyle;
use App\Models\Concerns\HasReference;
use App\Models\Concerns\VisibleToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Enquiry extends Model
{
    use HasFactory, HasReference, LogsActivity, VisibleToUser;

    public const REFERENCE_PREFIX = 'ENQ';

    /** Days without activity before a card gets a "stale" nudge. */
    public const STALE_AFTER_DAYS = 3;

    protected $table = 'enquiries';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => EnquiryStatus::class,
            'channel' => CustomerSource::class,
            'trip_type' => TravelStyle::class,
            'departure_date' => 'date',
            'return_date' => 'date',
            'budget' => 'decimal:2',
            'expected_value' => 'decimal:2',
            'next_follow_up_at' => 'datetime',
            'stage_changed_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['status', 'lost_reason', 'assigned_to', 'expected_value', 'probability', 'destination'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNotIn('status', [EnquiryStatus::Won, EnquiryStatus::Lost]);
    }

    public function isOpen(): bool
    {
        return $this->status->isOpen();
    }

    public function daysInStage(): int
    {
        return (int) ($this->stage_changed_at ?? $this->created_at)->diffInDays(now());
    }

    public function isStale(): bool
    {
        return $this->isOpen()
            && ($this->last_activity_at ?? $this->created_at)->lte(now()->subDays(self::STALE_AFTER_DAYS));
    }

    public function travellers(): int
    {
        return $this->travellers_adults + $this->travellers_children;
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function consultant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(Quote::class);
    }

    public function latestQuote(): HasOne
    {
        return $this->hasOne(Quote::class)->latestOfMany();
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function interactions(): MorphMany
    {
        return $this->morphMany(Interaction::class, 'related');
    }
}
