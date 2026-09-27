<?php

namespace App\Models;

use App\Enums\Direction;
use App\Enums\InteractionType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Interaction extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => InteractionType::class,
            'direction' => Direction::class,
            'occurred_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        // Keep the customer's "last contact" fresh for lists and lifecycle rules.
        static::created(function (Interaction $interaction) {
            Customer::withoutEvents(fn () => Customer::whereKey($interaction->customer_id)
                ->where(fn ($q) => $q->whereNull('last_contacted_at')->orWhere('last_contacted_at', '<', $interaction->occurred_at))
                ->update(['last_contacted_at' => $interaction->occurred_at]));

            if ($interaction->related_type === Enquiry::class) {
                Enquiry::whereKey($interaction->related_id)->update(['last_activity_at' => $interaction->occurred_at]);
            }
        });
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function related(): MorphTo
    {
        return $this->morphTo();
    }
}
