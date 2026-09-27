<?php

namespace App\Models;

use App\Enums\BudgetBand;
use App\Enums\TravelStyle;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomerPreference extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'budget_band' => BudgetBand::class,
            'travel_style' => TravelStyle::class,
            'preferred_airlines' => 'array',
            'preferred_destinations' => 'array',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }
}
