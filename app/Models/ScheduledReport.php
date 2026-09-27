<?php

namespace App\Models;

use App\Enums\ReportFrequency;
use App\Enums\ReportType;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ScheduledReport extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'report_type' => ReportType::class,
            'frequency' => ReportFrequency::class,
            'recipients' => 'array',
            'parameters' => 'array',
            'last_run_at' => 'datetime',
        ];
    }

    /** Is this report due to run at the given moment? */
    public function isDue(?\DateTimeInterface $at = null): bool
    {
        $at = $at ? \Illuminate\Support\Carbon::instance($at) : now();

        if ($this->last_run_at === null) {
            return true;
        }

        return match ($this->frequency) {
            ReportFrequency::Daily => $this->last_run_at->lte($at->copy()->subDay()),
            ReportFrequency::Weekly => $this->last_run_at->lte($at->copy()->subWeek()),
            ReportFrequency::Monthly => $this->last_run_at->lte($at->copy()->subMonth()),
        };
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
