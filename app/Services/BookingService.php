<?php

namespace App\Services;

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Payment;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;

class BookingService
{
    public function recordPayment(Booking $booking, array $data): Payment
    {
        return DB::transaction(function () use ($booking, $data) {
            $payment = $booking->payments()->create([...$data, 'paid_at' => $data['paid_at'] ?? now()]);
            $this->syncPaymentStatus($booking);

            return $payment;
        });
    }

    public function syncPaymentStatus(Booking $booking): Booking
    {
        $paid = (float) $booking->payments()->sum('amount');
        $status = match (true) {
            $booking->status === BookingStatus::Cancelled && $paid > 0 => PaymentStatus::Refunded,
            $paid <= 0 => PaymentStatus::Pending,
            $paid + 0.01 >= (float) $booking->total_amount => PaymentStatus::Paid,
            default => PaymentStatus::Partial,
        };

        $booking->update(['amount_paid' => $paid, 'payment_status' => $status]);

        return $booking;
    }

    public function cancel(Booking $booking): Booking
    {
        $booking->update(['status' => BookingStatus::Cancelled]);

        return $this->syncPaymentStatus($booking);
    }

    /** Signed, expiring link travellers use to leave feedback without logging in. */
    public function feedbackUrl(Booking $booking): string
    {
        return URL::temporarySignedRoute('feedback.public', now()->addDays(30), ['booking' => $booking->id]);
    }
}
