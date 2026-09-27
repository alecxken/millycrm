<?php

use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Segment;
use App\Services\CampaignService;
use App\Services\SegmentService;
use Livewire\Volt\Component;

new class extends Component
{
    public Campaign $campaign;

    public string $subject = '';

    public string $body = '';

    public string $cost = '0';

    public string $segmentId = '';

    public function mount(Campaign $campaign): void
    {
        $this->authorize('view', $campaign);
        $this->campaign = $campaign;
        $this->subject = (string) $campaign->subject;
        $this->body = $campaign->body;
        $this->cost = (string) (float) $campaign->cost;
        $this->segmentId = (string) $campaign->segment_id;
    }

    public function rendering($view): void
    {
        $view->title($this->campaign->name);
    }

    public function insert(string $field): void
    {
        $this->body = rtrim($this->body).' '.$field;
    }

    public function save(): void
    {
        $this->authorize('update', $this->campaign);
        abort_unless($this->campaign->status === CampaignStatus::Draft, 403);
        $this->validate([
            'subject' => [$this->campaign->channel->value === 'email' ? 'required' : 'nullable', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:'.($this->campaign->channel->value === 'sms' ? 480 : 5000)],
            'cost' => 'required|numeric|min:0',
            'segmentId' => 'required|exists:segments,id',
        ]);
        $this->campaign->update(['subject' => $this->subject ?: null, 'body' => $this->body, 'cost' => $this->cost, 'segment_id' => $this->segmentId]);
        $this->campaign->unsetRelation('segment');
        $this->dispatch('toast', message: 'Draft saved.');
    }

    public function send(CampaignService $service): void
    {
        $this->save();
        if ($this->getErrorBag()->isNotEmpty()) {
            return;
        }
        $service->send($this->campaign->refresh());
        $this->campaign->refresh();
        $this->dispatch('toast', message: "Sent to {$this->campaign->recipients_count} opted-in customers (simulated). Each send is logged on their timeline.");
    }

    public function with(CampaignService $service, SegmentService $segments): array
    {
        $this->campaign->load('segment');
        $sent = $this->campaign->status === CampaignStatus::Sent;
        $rules = Segment::find($this->segmentId)?->rules ?? [];
        $matching = $segments->count($rules);
        $audience = $segments->query($rules)->consented();
        $sample = (clone $audience)->with(['bookings:id,customer_id,destination,start_date', 'consultant:id,name'])->first();

        return [
            'sent' => $sent,
            'matching' => $matching,
            'audienceCount' => (clone $audience)->count(),
            'previewSubject' => $sample ? $service->render($this->subject, $sample) : $this->subject,
            'previewBody' => $sample ? $service->render($this->body, $sample) : $this->body,
            'sample' => $sample,
            'results' => $service->results($this->campaign),
            'recipients' => $sent ? $this->campaign->recipients()->select('customers.id', 'first_name', 'last_name', 'company_name', 'type')->limit(12)->get() : collect(),
            'attributed' => $sent ? $this->campaign->bookings()->with('customer:id,first_name,last_name,company_name,type')->get() : collect(),
            'segmentOptions' => Segment::orderBy('name')->get(['id', 'name']),
        ];
    }
}; ?>

@php($c = $campaign)
<div>
    <nav class="mb-4 text-sm"><a href="{{ route('campaigns.index') }}" wire:navigate class="inline-flex items-center gap-1 text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white"><x-hicon name="arrow-left" class="size-4" /> Campaigns</a></nav>
    <x-page-header :title="$c->name" :subtitle="$c->segment?->name.' · '.$c->channel->label().($c->sent_at ? ' · sent '.fdate($c->sent_at, true) : '')">
        <x-slot:actions><x-badge :enum="$c->channel" /><x-badge :enum="$c->status" /></x-slot:actions>
    </x-page-header>

    @if ($sent)
        {{-- Results --}}
        <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
            <x-stat label="Recipients" :value="$c->recipients_count" icon="users" hint="Opted-in only" />
            <x-stat label="Opens (simulated)" :value="$c->opens" icon="envelope-open" tone="sky" :hint="$results['open_rate'].'% open rate'" />
            <x-stat label="Attributed bookings" :value="$results['conversions']" icon="ticket" tone="emerald" :hint="$results['conversion_rate'].'% conversion'" />
            <x-stat label="Revenue" :value="money($results['revenue'], compact: true)" icon="banknotes" tone="violet" hint="Bookings within 60 days" />
            <x-stat label="ROI" :value="$results['roi'] !== null ? $results['roi'].'%' : '—'" icon="arrow-trending-up" :tone="($results['roi'] ?? 0) >= 0 ? 'emerald' : 'rose'" :hint="'Cost '.money($c->cost).' · 12% margin'" />
        </div>
        <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-3">
            <x-card title="Funnel" class="lg:col-span-1">
                @php($steps = ['Sent' => $c->recipients_count, 'Opened' => $c->opens, 'Booked' => $results['conversions']])
                <div class="space-y-3">
                    @foreach ($steps as $label => $value)
                        <div>
                            <div class="flex justify-between text-sm"><span>{{ $label }}</span><span class="font-semibold tabular-nums">{{ $value }}</span></div>
                            <div class="mt-1 h-3 rounded-full bg-slate-100 dark:bg-slate-800"><div class="h-3 rounded-full {{ ['Sent' => 'bg-brand-700', 'Opened' => 'bg-sky-500', 'Booked' => 'bg-sand-500'][$label] }}" style="width: {{ $c->recipients_count ? max(2, round($value / $c->recipients_count * 100)) : 0 }}%"></div></div>
                        </div>
                    @endforeach
                </div>
            </x-card>
            <x-card title="Attributed bookings" :padding="false" class="lg:col-span-2">
                @forelse ($attributed as $b)
                    <a href="{{ route('bookings.show', $b) }}" wire:navigate class="flex items-center gap-3 border-b border-slate-100 px-4 py-3 text-sm last:border-0 hover:bg-slate-50 dark:border-slate-800 dark:hover:bg-slate-800/40">
                        <span class="min-w-0 flex-1 truncate"><span class="font-medium">{{ $b->customer->display_name }}</span> · {{ $b->destination }}</span>
                        <span class="text-xs text-slate-500">{{ fdate($b->created_at) }}</span>
                        <span class="font-semibold tabular-nums">{{ money($b->total_amount, $b->currency) }}</span>
                    </a>
                @empty
                    <x-empty-state icon="ticket" title="No bookings attributed yet" description="Bookings made by recipients within 60 days are credited to this campaign." class="!py-8" />
                @endforelse
            </x-card>
        </div>
        <x-card title="Message sent" class="mt-6">
            @if ($c->subject)<p class="mb-2 font-semibold">{{ $c->subject }}</p>@endif
            <p class="whitespace-pre-line text-sm text-slate-600 dark:text-slate-300">{{ $c->body }}</p>
            @if ($recipients->isNotEmpty())
                <p class="mt-4 border-t border-slate-100 pt-4 text-xs text-slate-500 dark:border-slate-800">Recipients include {{ $recipients->pluck('display_name')->implode(', ') }}{{ $c->recipients_count > 12 ? ' and '.($c->recipients_count - 12).' more' : '' }}.</p>
            @endif
        </x-card>
    @else
        {{-- Composer --}}
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
            <x-card title="Compose">
                <form wire:submit="save" class="space-y-4">
                    <x-field label="Audience" for="c-seg" error="segmentId">
                        <select id="c-seg" wire:model.live="segmentId" class="form-input">@foreach ($segmentOptions as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select>
                    </x-field>
                    <div class="flex items-start gap-3 rounded-xl border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-900 dark:border-emerald-400/30 dark:bg-emerald-400/10 dark:text-emerald-200">
                        <x-hicon name="shield-check" class="size-5" />
                        <p><strong>{{ $audienceCount }}</strong> of {{ $matching }} matching customers will receive this. {{ $matching - $audienceCount }} are excluded because they haven't given marketing consent — enforced by the system, not by the segment.</p>
                    </div>
                    @if ($c->channel->value === 'email')
                        <x-field label="Subject" for="c-sub" error="subject" required><input id="c-sub" wire:model.live.debounce.300ms="subject" class="form-input"></x-field>
                    @endif
                    <x-field label="Message" for="c-body" error="body" required :hint="$c->channel->value === 'sms' ? mb_strlen($body).' / 480 characters' : null">
                        <textarea id="c-body" wire:model.live.debounce.300ms="body" rows="9" class="form-input"></textarea>
                    </x-field>
                    <div>
                        <p class="form-label">Insert merge field</p>
                        <div class="flex flex-wrap gap-2">
                            @foreach (\App\Services\CampaignService::MERGE_FIELDS as $field => $label)
                                <button type="button" wire:click="insert('{{ $field }}')" class="rounded-lg border border-slate-200 px-2 py-1 font-mono text-xs hover:border-brand-400 dark:border-slate-700" title="{{ $label }}">{{ $field }}</button>
                            @endforeach
                        </div>
                    </div>
                    <x-field label="Campaign cost (KES)" for="c-cost" error="cost" hint="Used to calculate ROI"><input id="c-cost" type="number" min="0" step="500" wire:model="cost" class="form-input"></x-field>
                    @can('update', $c)
                        <div class="flex gap-2">
                            <x-button type="submit" variant="secondary" loading="save">Save draft</x-button>
                            <x-button variant="accent" icon="paper-airplane" wire:click="send" wire:confirm="Send to {{ $audienceCount }} opted-in customers now? (Simulated — no real messages leave the system.)" :disabled="$audienceCount === 0">Send to {{ $audienceCount }}</x-button>
                        </div>
                    @endcan
                </form>
            </x-card>
            <div class="space-y-6">
                <x-card title="Live preview" :subtitle="$sample ? 'As '.$sample->first_name.' will see it' : 'No recipients yet'">
                    @if ($c->channel->value === 'email')
                        <div class="overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700">
                            <div class="bg-brand-700 px-5 py-3 text-sm font-semibold text-white">WanderLink Travel</div>
                            <div class="bg-white p-5 text-sm text-slate-800 dark:bg-slate-950 dark:text-slate-200">
                                <p class="mb-3 font-semibold">{{ $previewSubject }}</p>
                                <p class="whitespace-pre-line">{{ $previewBody }}</p>
                                <p class="mt-6 border-t border-slate-100 pt-3 text-xs text-slate-400 dark:border-slate-800">You receive this because you opted in. Unsubscribe any time.</p>
                            </div>
                        </div>
                    @else
                        <div class="mx-auto max-w-xs rounded-3xl bg-slate-100 p-4 dark:bg-slate-800">
                            <div @class(['rounded-2xl px-4 py-3 text-sm shadow-sm', 'bg-emerald-100 text-emerald-950 dark:bg-emerald-900 dark:text-emerald-50' => $c->channel->value === 'whatsapp', 'bg-white text-slate-900 dark:bg-slate-700 dark:text-white' => $c->channel->value !== 'whatsapp'])>
                                <p class="whitespace-pre-line">{{ $previewBody }}</p>
                                <p class="mt-1 text-right text-[10px] opacity-60">{{ now()->format('H:i') }}</p>
                            </div>
                        </div>
                    @endif
                </x-card>
            </div>
        </div>
    @endif
</div>
