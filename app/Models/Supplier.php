<?php

namespace App\Models;

use App\Enums\SupplierCategory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Supplier extends Model
{
    use HasFactory, LogsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'category' => SupplierCategory::class,
            'commission_rate' => 'decimal:2',
            'rating' => 'decimal:1',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logUnguarded()->logOnlyDirty();
    }

    public function quoteItems(): HasMany
    {
        return $this->hasMany(QuoteItem::class);
    }
}
