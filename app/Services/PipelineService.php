<?php

namespace App\Services;

use App\Enums\EnquiryStatus;
use App\Models\Enquiry;
use Illuminate\Validation\ValidationException;

class PipelineService
{
    /**
     * Move an enquiry to a new stage. Losing a deal requires a reason so the
     * agency learns why it loses business.
     */
    public function move(Enquiry $enquiry, EnquiryStatus $status, ?string $lostReason = null, ?int $position = null): Enquiry
    {
        if ($status === EnquiryStatus::Lost && blank($lostReason ?? $enquiry->lost_reason)) {
            throw ValidationException::withMessages(['lost_reason' => 'Please tell us why this enquiry was lost.']);
        }

        $changed = $enquiry->status !== $status;

        $enquiry->fill([
            'status' => $status,
            'lost_reason' => $status === EnquiryStatus::Lost ? ($lostReason ?? $enquiry->lost_reason) : null,
            'probability' => $status->probability(),
            'last_activity_at' => now(),
        ]);

        if ($changed) {
            $enquiry->stage_changed_at = now();
        }

        if ($position !== null) {
            $enquiry->position = $position;
        }

        $enquiry->save();

        return $enquiry;
    }

    /** Weighted pipeline value (expected value x probability) for open deals. */
    public function weightedValue($query = null): float
    {
        $query ??= Enquiry::query();

        return (float) (clone $query)->open()->get(['expected_value', 'probability'])
            ->sum(fn ($e) => $e->expected_value * $e->probability / 100);
    }
}
