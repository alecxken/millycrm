<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Feedback extends Model
{
    use HasFactory;

    protected $table = 'feedback';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['submitted_at' => 'datetime'];
    }

    /** promoter (9-10), passive (7-8), detractor (0-6) */
    public function npsGroup(): string
    {
        return match (true) {
            $this->nps_score >= 9 => 'promoter',
            $this->nps_score >= 7 => 'passive',
            default => 'detractor',
        };
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
