<?php

namespace App\Models;

use App\Enums\BookingStatus;
use App\Enums\Currency;
use App\Enums\PaymentStatus;
use App\Models\Concerns\HasReference;
use App\Services\LifecycleService;
use App\Models\Concerns\VisibleToUser;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Booking extends Model
{
    use HasFactory, HasReference, LogsActivity, VisibleToUser;

    public const REFERENCE_PREFIX = 'BK';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => BookingStatus::class,
            'payment_status' => PaymentStatus::class,
            'currency' => Currency::class,
            'start_date' => 'date',
            'end_date' => 'date',
            'total_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'payment_status', 'amount_paid', 'total_amount', 'start_date', 'end_date'])->logOnlyDirty();
    }

    protected static function booted(): void
    {
        // Lifecycle promotion (lead -> customer -> repeat -> VIP) follows every booking change.
        static::saved(function (Booking $booking) {
            if ($booking->wasRecentlyCreated || $booking->wasChanged(['status', 'total_amount'])) {
                app(LifecycleService::class)->evaluate(Customer::findOrFail($booking->customer_id));
            }
        });
    }

    public function ownerColumn(): string
    {
        return 'consultant_id';
    }

    public function balance(): float
    {
        return max(0, (float) $this->total_amount - (float) $this->amount_paid);
    }

    public function nights(): int
    {
        return (int) $this->start_date->diffInDays($this->end_date);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function quote(): BelongsTo
    {
        return $this->belongsTo(Quote::class);
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    public function consultant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'consultant_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function feedback(): HasOne
    {
        return $this->hasOne(Feedback::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(ServiceTicket::class);
    }
}
