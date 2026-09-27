<?php

namespace App\Models;

use App\Enums\CampaignChannel;
use App\Enums\CampaignStatus;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Campaign extends Model
{
    use HasFactory, LogsActivity;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'channel' => CampaignChannel::class,
            'status' => CampaignStatus::class,
            'cost' => 'decimal:2',
            'scheduled_at' => 'datetime',
            'sent_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['name', 'status', 'segment_id', 'recipients_count'])->logOnlyDirty();
    }

    protected function openRate(): Attribute
    {
        return Attribute::get(fn () => $this->recipients_count > 0 ? round($this->opens / $this->recipients_count * 100, 1) : 0);
    }

    public function segment(): BelongsTo
    {
        return $this->belongsTo(Segment::class);
    }

    public function recipients(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'campaign_recipients')->withPivot('opened_at')->withTimestamps();
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
