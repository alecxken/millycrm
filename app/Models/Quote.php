<?php

namespace App\Models;

use App\Enums\Currency;
use App\Enums\QuoteStatus;
use App\Models\Concerns\HasReference;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Quote extends Model
{
    use HasFactory, HasReference, LogsActivity;

    public const REFERENCE_PREFIX = 'QT';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => QuoteStatus::class,
            'currency' => Currency::class,
            'total_amount' => 'decimal:2',
            'discount' => 'decimal:2',
            'valid_until' => 'date',
            'sent_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'total_amount', 'discount', 'valid_until'])->logOnlyDirty();
    }

    public function subtotal(): float
    {
        return (float) $this->items->sum('price');
    }

    public function totalCost(): float
    {
        return (float) $this->items->sum('cost');
    }

    public function margin(): float
    {
        return (float) $this->total_amount - $this->totalCost();
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
    }

    public function booking(): HasOne
    {
        return $this->hasOne(Booking::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
