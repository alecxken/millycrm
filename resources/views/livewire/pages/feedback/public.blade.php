<?php

use App\Enums\Direction;
use App\Enums\InteractionType;
use App\Enums\TaskType;
use App\Models\Booking;
use App\Models\Feedback;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Layout('layouts.public')] #[Title('Share your feedback')] class extends Component
{
    #[Locked]
    public int $bookingId;

    public ?int $nps = null;

    public int $rating = 0;

    public string $comment = '';

    public bool $submitted = false;

    public function mount(Booking $booking): void
    {
        $this->bookingId = $booking->id;
        $this->submitted = $booking->feedback()->exists();
    }

    public function submit(): void
    {
        $this->validate([
            'nps' => 'required|integer|between:0,10',
            'rating' => 'required|integer|between:1,5',
            'comment' => 'nullable|string|max:2000',
        ], ['nps.required' => 'Please pick a score from 0 to 10.', 'rating.between' => 'Please rate your trip from 1 to 5 stars.']);

        $booking = Booking::with('customer')->findOrFail($this->bookingId);
        abort_if($booking->feedback()->exists(), 409, 'Feedback already received for this trip.');

        Feedback::create(['customer_id' => $booking->customer_id, 'booking_id' => $booking->id, 'nps_score' => $this->nps, 'rating' => $this->rating, 'comment' => $this->comment ?: null, 'submitted_at' => now()]);

        $booking->customer->interactions()->create([
            'type' => InteractionType::Note, 'direction' => Direction::Inbound,
            'subject' => "Feedback received: NPS {$this->nps}", 'body' => $this->comment ?: null, 'occurred_at' => now(),
            'related_type' => Booking::class, 'related_id' => $booking->id,
        ]);

        // Close the loop on the automated "request feedback" task.
        $booking->customer->tasks()->where('booking_id', $booking->id)->where('type', TaskType::FeedbackRequest)->whereNull('completed_at')->update(['completed_at' => now()]);

        $this->submitted = true;
    }

    public function with(): array
    {
        return ['booking' => Booking::with('customer:id,first_name')->findOrFail($this->bookingId)];
    }
}; ?>

<div class="card p-6 sm:p-8">
    @if ($submitted)
        <div class="py-6 text-center">
            <span class="mx-auto mb-4 flex size-14 items-center justify-center rounded-full bg-emerald-100 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300"><x-hicon name="heart" class="size-7" solid /></span>
            <h1 class="text-xl font-bold">Asante sana, {{ $booking->customer->first_name }}!</h1>
            <p class="mt-2 text-slate-600 dark:text-slate-400">Your feedback reached our team. We can't wait to plan your next adventure.</p>
        </div>
    @else
        <h1 class="text-xl font-bold">How was {{ $booking->destination }}, {{ $booking->customer->first_name }}?</h1>
        <p class="mt-1 text-sm text-slate-500">{{ fdate($booking->start_date) }} – {{ fdate($booking->end_date) }} · takes 30 seconds</p>

        <form wire:submit="submit" class="mt-8 space-y-8">
            <fieldset>
                <legend class="text-sm font-semibold">How likely are you to recommend WanderLink to a friend?</legend>
                <div class="mt-3 grid grid-cols-6 gap-1.5 sm:grid-cols-11">
                    @foreach (range(0, 10) as $n)
                        <label @class(['flex h-11 cursor-pointer items-center justify-center rounded-lg border text-sm font-semibold transition',
                            'border-rose-500 bg-rose-500 text-white' => $nps === $n && $n <= 6,
                            'border-amber-500 bg-amber-500 text-amber-950' => $nps === $n && $n >= 7 && $n <= 8,
                            'border-emerald-600 bg-emerald-600 text-white' => $nps === $n && $n >= 9,
                            'border-slate-200 hover:border-brand-400 dark:border-slate-700' => $nps !== $n])>
                            <input type="radio" wire:model.live="nps" value="{{ $n }}" class="sr-only">{{ $n }}
                        </label>
                    @endforeach
                </div>
                <div class="mt-1 flex justify-between text-xs text-slate-500"><span>Not likely</span><span>Extremely likely</span></div>
                @error('nps')<p class="mt-1 text-xs font-medium text-rose-700">{{ $message }}</p>@enderror
            </fieldset>

            <fieldset>
                <legend class="text-sm font-semibold">Rate your trip overall</legend>
                <div class="mt-2 flex gap-1" x-data="{ hover: 0 }">
                    @foreach (range(1, 5) as $s)
                        <button type="button" wire:click="$set('rating', {{ $s }})" x-on:mouseenter="hover = {{ $s }}" x-on:mouseleave="hover = 0" aria-label="{{ $s }} star{{ $s > 1 ? 's' : '' }}" aria-pressed="{{ $rating >= $s ? 'true' : 'false' }}"
                                class="rounded p-1 text-slate-300 transition dark:text-slate-600" :class="(hover || {{ $rating }}) >= {{ $s }} && '!text-sand-500'">
                            <x-hicon name="star" class="size-9" solid />
                        </button>
                    @endforeach
                </div>
                @error('rating')<p class="mt-1 text-xs font-medium text-rose-700">{{ $message }}</p>@enderror
            </fieldset>

            <div>
                <label for="comment" class="text-sm font-semibold">Anything we should know? <span class="font-normal text-slate-500">(optional)</span></label>
                <textarea id="comment" wire:model="comment" rows="4" class="form-input mt-2" placeholder="What made the trip special — or what could we do better?"></textarea>
            </div>

            <x-button type="submit" size="lg" class="w-full" loading="submit">Send feedback</x-button>
        </form>
    @endif
</div>
