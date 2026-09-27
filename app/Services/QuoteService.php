<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\Direction;
use App\Enums\EnquiryStatus;
use App\Enums\InteractionType;
use App\Enums\PaymentStatus;
use App\Enums\Priority;
use App\Enums\QuoteStatus;
use App\Enums\TaskType;
use App\Models\Booking;
use App\Models\Enquiry;
use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Task;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class QuoteService
{
    public const FOLLOW_UP_AFTER_DAYS = 2;

    public function __construct(
        private readonly LifecycleService $lifecycle,
        private readonly PipelineService $pipeline,
    ) {}

    /** Selling price = cost + markup %. */
    public static function priceFor(float $cost, float $markup): float
    {
        return round($cost * (1 + $markup / 100), 2);
    }

    public function createDraft(Enquiry $enquiry, ?int $userId = null): Quote
    {
        return $enquiry->quotes()->create([
            'status' => QuoteStatus::Draft,
            'currency' => 'KES',
            'valid_until' => now()->addDays(14),
            'created_by' => $userId ?? auth()->id(),
        ]);
    }

    /** @param array{type:string, description:string, cost:float|string, markup:float|string, supplier_id?:int|null} $data */
    public function addItem(Quote $quote, array $data): QuoteItem
    {
        $item = $quote->items()->create([
            ...$data,
            'price' => self::priceFor((float) $data['cost'], (float) $data['markup']),
        ]);

        $this->recalculate($quote);

        return $item;
    }

    public function removeItem(Quote $quote, QuoteItem $item): void
    {
        abort_unless($item->quote_id === $quote->id, 404);
        $item->delete();
        $this->recalculate($quote);
    }

    public function recalculate(Quote $quote): Quote
    {
        $subtotal = (float) $quote->items()->sum('price');
        $quote->update(['total_amount' => max(0, $subtotal - (float) $quote->discount)]);

        return $quote->refresh();
    }

    /**
     * Mark the quote as sent: moves the enquiry to "Quoted", logs the email
     * and auto-creates a follow-up task two days out.
     */
    public function send(Quote $quote): Quote
    {
        if ($quote->items()->doesntExist()) {
            throw new RuntimeException('Add at least one line item before sending the quote.');
        }

        return DB::transaction(function () use ($quote) {
            $quote->update(['status' => QuoteStatus::Sent, 'sent_at' => now()]);
            $enquiry = $quote->enquiry;

            if (in_array($enquiry->status, [EnquiryStatus::New, EnquiryStatus::Contacted], true)) {
                $this->pipeline->move($enquiry, EnquiryStatus::Quoted);
            }

            $enquiry->update(['expected_value' => $quote->total_amount * $quote->currency->toKes()]);

            $enquiry->customer->interactions()->create([
                'user_id' => auth()->id() ?? $enquiry->assigned_to,
                'type' => InteractionType::Email,
                'direction' => Direction::Outbound,
                'subject' => "Quote {$quote->reference} sent",
                'body' => "Quote for {$enquiry->destination} totalling ".money($quote->total_amount, $quote->currency).' sent to customer.',
                'occurred_at' => now(),
                'related_type' => Enquiry::class,
                'related_id' => $enquiry->id,
            ]);

            Task::create([
                'customer_id' => $enquiry->customer_id,
                'enquiry_id' => $enquiry->id,
                'assigned_to' => $enquiry->assigned_to,
                'created_by' => auth()->id(),
                'type' => TaskType::FollowUp,
                'title' => "Follow up on quote {$quote->reference} ({$enquiry->destination})",
                'due_at' => now()->addDays(self::FOLLOW_UP_AFTER_DAYS)->setTime(10, 0),
                'priority' => Priority::High,
            ]);

            $enquiry->update(['next_follow_up_at' => now()->addDays(self::FOLLOW_UP_AFTER_DAYS)->setTime(10, 0)]);

            return $quote->refresh();
        });
    }

    /**
     * One-click conversion: accepted quote -> confirmed booking, enquiry won,
     * lifecycle re-evaluated (e.g. lead -> customer).
     */
    public function convertToBooking(Quote $quote): Booking
    {
        if ($quote->booking()->exists()) {
            throw new RuntimeException('This quote has already been converted to a booking.');
        }

        if ($quote->items()->doesntExist()) {
            throw new RuntimeException('A quote needs at least one line item before it can be booked.');
        }

        return DB::transaction(function () use ($quote) {
            $enquiry = $quote->enquiry;
            $customer = $enquiry->customer;

            $quote->update(['status' => QuoteStatus::Accepted]);

            $booking = Booking::create([
                'customer_id' => $customer->id,
                'quote_id' => $quote->id,
                'consultant_id' => $enquiry->assigned_to,
                'destination' => $enquiry->destination,
                'start_date' => $enquiry->departure_date ?? now()->addMonth(),
                'end_date' => $enquiry->return_date ?? ($enquiry->departure_date ?? now()->addMonth())->copy()->addWeek(),
                'total_amount' => $quote->total_amount,
                'currency' => $quote->currency,
                'amount_paid' => 0,
                'payment_status' => PaymentStatus::Pending,
                'status' => BookingStatus::Confirmed,
            ]);

            $this->pipeline->move($enquiry, EnquiryStatus::Won);

            // Close any open follow-ups for this enquiry: the deal is done.
            $enquiry->tasks()->whereNull('completed_at')->update(['completed_at' => now()]);

            $customer->interactions()->create([
                'user_id' => auth()->id() ?? $enquiry->assigned_to,
                'type' => InteractionType::Note,
                'direction' => Direction::Outbound,
                'subject' => "Booking {$booking->reference} confirmed",
                'body' => "Quote {$quote->reference} accepted and converted to booking.",
                'occurred_at' => now(),
                'related_type' => Booking::class,
                'related_id' => $booking->id,
            ]);

            $this->lifecycle->evaluate($customer->refresh());

            return $booking;
        });
    }
}
