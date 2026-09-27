<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\Priority;
use App\Enums\TaskType;
use App\Models\Booking;
use App\Models\Task;

/**
 * Follow-up discipline, automated:
 *  - quote sent            -> follow-up in 2 days (see QuoteService::send)
 *  - trip ended 3 days ago -> request feedback
 *  - 11 months after trip  -> suggest re-booking
 */
class TaskAutomationService
{
    public const FEEDBACK_AFTER_DAYS = 3;

    public const REBOOK_AFTER_MONTHS = 11;

    /** Keep booking statuses in step with the calendar. */
    public function syncBookingStatuses(): int
    {
        $today = now()->startOfDay();

        $travelling = Booking::where('status', BookingStatus::Confirmed)
            ->whereDate('start_date', '<=', $today)->whereDate('end_date', '>=', $today)
            ->update(['status' => BookingStatus::Travelling]);

        $completed = Booking::whereIn('status', [BookingStatus::Confirmed, BookingStatus::Travelling])
            ->whereDate('end_date', '<', $today)
            ->update(['status' => BookingStatus::Completed]);

        return $travelling + $completed;
    }

    public function createFeedbackRequests(): int
    {
        $created = 0;

        Booking::query()
            ->where('status', '!=', BookingStatus::Cancelled)
            ->whereDate('end_date', '<=', now()->subDays(self::FEEDBACK_AFTER_DAYS))
            ->whereDate('end_date', '>=', now()->subDays(self::FEEDBACK_AFTER_DAYS + 30))
            ->whereDoesntHave('feedback')
            ->whereNotExists(fn ($q) => $q->from('tasks')->whereColumn('tasks.booking_id', 'bookings.id')->where('tasks.type', TaskType::FeedbackRequest->value))
            ->with('customer:id,first_name,last_name,company_name,type,assigned_to')
            ->each(function (Booking $booking) use (&$created) {
                Task::create([
                    'customer_id' => $booking->customer_id,
                    'booking_id' => $booking->id,
                    'assigned_to' => $booking->consultant_id ?? $booking->customer->assigned_to,
                    'type' => TaskType::FeedbackRequest,
                    'title' => "Request feedback: {$booking->customer->display_name} ({$booking->destination})",
                    'description' => 'Send the feedback link from the booking page and thank them for travelling with us.',
                    'due_at' => $booking->end_date->copy()->addDays(self::FEEDBACK_AFTER_DAYS)->setTime(9, 0)->max(now()->startOfDay()->setTime(9, 0)),
                    'priority' => Priority::Normal,
                ]);
                $created++;
            });

        return $created;
    }

    public function createRebookSuggestions(): int
    {
        $created = 0;

        Booking::query()
            ->where('status', BookingStatus::Completed)
            ->whereDate('end_date', '<=', now()->subMonths(self::REBOOK_AFTER_MONTHS))
            ->whereDate('end_date', '>=', now()->subMonths(self::REBOOK_AFTER_MONTHS + 1))
            ->whereNotExists(fn ($q) => $q->from('tasks')->whereColumn('tasks.booking_id', 'bookings.id')->where('tasks.type', TaskType::Rebook->value))
            // Skip customers who have already booked again since.
            ->whereNotExists(fn ($q) => $q->from('bookings as later')->whereColumn('later.customer_id', 'bookings.customer_id')->whereColumn('later.start_date', '>', 'bookings.end_date'))
            ->with('customer:id,first_name,last_name,company_name,type,assigned_to')
            ->each(function (Booking $booking) use (&$created) {
                Task::create([
                    'customer_id' => $booking->customer_id,
                    'booking_id' => $booking->id,
                    'assigned_to' => $booking->customer->assigned_to ?? $booking->consultant_id,
                    'type' => TaskType::Rebook,
                    'title' => "Suggest a re-booking to {$booking->customer->display_name}",
                    'description' => "It's almost a year since their {$booking->destination} trip. Offer something similar for this season.",
                    'due_at' => now()->addDay()->setTime(10, 0),
                    'priority' => Priority::Normal,
                ]);
                $created++;
            });

        return $created;
    }

    /** @return array{statuses:int, feedback:int, rebook:int} */
    public function run(): array
    {
        return [
            'statuses' => $this->syncBookingStatuses(),
            'feedback' => $this->createFeedbackRequests(),
            'rebook' => $this->createRebookSuggestions(),
        ];
    }
}
