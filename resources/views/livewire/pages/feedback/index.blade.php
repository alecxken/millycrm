<?php

use App\Models\Feedback;
use App\Services\ReportService;
use App\Support\ChartPalette;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Volt\Component;
use Livewire\WithPagination;

new #[Title('Feedback')] class extends Component
{
    use WithPagination;

    #[Url]
    public string $group = '';

    public function updatedGroup(): void
    {
        $this->resetPage();
    }

    public function with(ReportService $reports): array
    {
        $query = Feedback::with(['customer:id,first_name,last_name,company_name,type', 'booking:id,reference,destination'])
            ->when($this->group === 'promoter', fn ($q) => $q->where('nps_score', '>=', 9))
            ->when($this->group === 'passive', fn ($q) => $q->whereBetween('nps_score', [7, 8]))
            ->when($this->group === 'detractor', fn ($q) => $q->where('nps_score', '<=', 6))
            ->latest('submitted_at');

        $sentiment = $reports->feedbackSentiment();
        $monthly = Feedback::where('submitted_at', '>=', now()->subMonths(11)->startOfMonth())->get(['nps_score', 'submitted_at'])
            ->groupBy(fn ($f) => $f->submitted_at->format('Y-m'))->sortKeys()
            ->mapWithKeys(fn ($g, $k) => [\Illuminate\Support\Carbon::createFromFormat('Y-m', $k)->format('M y') => (int) round(($g->where('nps_score', '>=', 9)->count() - $g->where('nps_score', '<=', 6)->count()) / $g->count() * 100)])->all();

        return [
            'feedback' => $query->paginate(12),
            'nps' => $reports->nps(),
            'sentiment' => $sentiment,
            'avgRating' => round((float) Feedback::avg('rating'), 1),
            'trendChart' => ChartPalette::line($monthly, 'NPS', currency: false),
            'sentimentChart' => ChartPalette::doughnut($sentiment, ['#10B981', '#94A3B8', '#E11D48']),
        ];
    }
}; ?>

<div>
    <x-page-header title="Customer feedback" subtitle="What travellers told us after they got home." />

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <x-stat label="Net Promoter Score" :value="$nps !== null ? ($nps > 0 ? '+' : '').$nps : '—'" icon="heart" tone="amber" hint="Last 12 months" />
        <x-stat label="Average rating" :value="$avgRating.' / 5'" icon="star" hint="All trips" />
        <x-stat label="Promoters" :value="$sentiment['Promoters']" icon="face-smile" tone="emerald" hint="Scored 9–10" />
        <x-stat label="Detractors" :value="$sentiment['Detractors']" icon="face-frown" tone="rose" hint="Scored 0–6 — call them back" />
    </div>

    <div class="mb-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
        <x-card title="NPS by month" class="lg:col-span-2"><x-chart :config="$trendChart" label="NPS by month" height="h-56" /></x-card>
        <x-card title="Sentiment"><x-chart :config="$sentimentChart" label="Sentiment" height="h-56" /></x-card>
    </div>

    <div class="mb-4 inline-flex rounded-xl bg-slate-100 p-1 text-sm dark:bg-slate-800" role="tablist">
        @foreach (['' => 'All', 'promoter' => 'Promoters', 'passive' => 'Passives', 'detractor' => 'Detractors'] as $key => $label)
            <button type="button" role="tab" wire:click="$set('group', '{{ $key }}')" aria-selected="{{ $group === $key ? 'true' : 'false' }}" @class(['shrink-0 rounded-full px-4 py-1.5 font-bold', 'bg-brand-800 text-white shadow-[0_4px_12px_color-mix(in_srgb,var(--brand)_25%,transparent)]' => $group === $key, 'text-slate-500 hover:text-brand-800 dark:text-slate-400 dark:hover:text-white' => $group !== $key])>{{ $label }}</button>
        @endforeach
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3" wire:loading.class="opacity-60">
        @forelse ($feedback as $f)
            @php($g = $f->npsGroup())
            <article class="card p-4" wire:key="fb-{{ $f->id }}">
                <div class="flex items-center justify-between">
                    <x-badge :color="['promoter' => 'emerald', 'passive' => 'slate', 'detractor' => 'rose'][$g]" :icon="['promoter' => 'face-smile', 'passive' => 'minus-circle', 'detractor' => 'face-frown'][$g]" :label="'NPS '.$f->nps_score.' · '.ucfirst($g)" />
                    <span class="text-sm text-sand-500" aria-label="{{ $f->rating }} out of 5 stars">{{ str_repeat('★', $f->rating) }}<span class="text-slate-300 dark:text-slate-700">{{ str_repeat('★', 5 - $f->rating) }}</span></span>
                </div>
                <p class="mt-3 text-sm text-slate-700 dark:text-slate-300">{{ $f->comment ? '“'.$f->comment.'”' : 'No comment left.' }}</p>
                <p class="mt-3 text-xs text-slate-500"><a href="{{ route('customers.show', $f->customer) }}" wire:navigate class="link">{{ $f->customer->display_name }}</a> · {{ $f->booking?->destination }} · {{ fdate($f->submitted_at) }}</p>
            </article>
        @empty
            <div class="card md:col-span-2 xl:col-span-3"><x-empty-state icon="chat-bubble-bottom-center-text" title="No feedback in this group" description="Feedback links are sent automatically 3 days after each trip ends." /></div>
        @endforelse
    </div>
    <div class="mt-4">{{ $feedback->links() }}</div>
</div>
