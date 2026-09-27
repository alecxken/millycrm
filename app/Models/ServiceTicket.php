<?php

namespace App\Models;

use App\Enums\Priority;
use App\Enums\TicketCategory;
use App\Enums\TicketStatus;
use App\Models\Concerns\HasReference;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ServiceTicket extends Model
{
    use HasFactory, HasReference, LogsActivity;

    public const REFERENCE_PREFIX = 'TK';


    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'category' => TicketCategory::class,
            'priority' => Priority::class,
            'status' => TicketStatus::class,
            'sla_due_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'priority', 'assigned_to', 'resolution'])->logOnlyDirty();
    }

    public function scopeUnresolved(Builder $query): Builder
    {
        return $query->whereIn('status', [TicketStatus::Open, TicketStatus::InProgress]);
    }

    public function isOpen(): bool
    {
        return in_array($this->status, [TicketStatus::Open, TicketStatus::InProgress], true);
    }

    public function isBreached(): bool
    {
        return $this->sla_due_at !== null && (
            $this->isOpen() ? $this->sla_due_at->isPast() : ($this->resolved_at?->gt($this->sla_due_at) ?? false)
        );
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }

    public function notes(): HasMany
    {
        return $this->hasMany(TicketNote::class);
    }
}
