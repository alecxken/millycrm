<?php

namespace App\Models;

use App\Enums\Priority;
use App\Enums\TaskType;
use App\Models\Concerns\VisibleToUser;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Task extends Model
{
    use HasFactory, VisibleToUser;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'type' => TaskType::class,
            'priority' => Priority::class,
            'due_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->whereNull('completed_at');
    }

    public function isOverdue(): bool
    {
        return $this->completed_at === null && $this->due_at !== null && $this->due_at->isPast() && ! $this->due_at->isToday();
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function enquiry(): BelongsTo
    {
        return $this->belongsTo(Enquiry::class);
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to');
    }
}
