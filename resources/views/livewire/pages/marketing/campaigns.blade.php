<?php

use App\Enums\CampaignChannel;
use App\Enums\CampaignStatus;
use App\Models\Campaign;
use App\Models\Segment;
use App\Services\CampaignService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('Campaigns')] class extends Component
{
    public ?string $panel = null;

    public array $form = ['name' => '', 'segment_id' => '', 'channel' => 'email'];

    public function mount(): void
    {
        if (request()->boolean('new')) {
            $this->form['segment_id'] = (string) request('segment', '');
            $this->panel = 'new-campaign';
        }
    }

    public function create(): void
    {
        $this->authorize('create', Campaign::class);
        $data = $this->validate([
            'form.name' => 'required|string|max:120',
            'form.segment_id' => 'required|exists:segments,id',
            'form.channel' => ['required', Rule::enum(CampaignChannel::class)],
        ], attributes: ['form.name' => 'name', 'form.segment_id' => 'segment'])['form'];

        $campaign = Campaign::create([...$data, 'status' => CampaignStatus::Draft, 'body' => "Hi {{first_name}},\n\n", 'subject' => $data['channel'] === 'email' ? 'A little travel inspiration, {{first_name}}' : null, 'created_by' => auth()->id()]);
        $this->redirectRoute('campaigns.show', $campaign, navigate: true);
    }

    public function with(CampaignService $service): array
    {
        return [
            'campaigns' => Campaign::with('segment:id,name')->orderByRaw("case status when 'draft' then 0 when 'scheduled' then 1 else 2 end")->latest('sent_at')->get()
                ->map(fn ($c) => ['campaign' => $c, 'results' => $service->results($c), 'audience' => $c->status === CampaignStatus::Sent ? $c->recipients_count : $service->audienceCount($c)]),
            'segments' => Segment::orderBy('name')->get(['id', 'name']),
        ];
    }
}; ?>

<div>
    <x-page-header title="Campaigns" subtitle="Personal notes to the right people, and only to those who said yes to hearing from us.">
        <x-slot:actions>@can('marketing.manage')<x-button icon="plus" wire:click="$set('panel', 'new-campaign')">New campaign</x-button>@endcan</x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($campaigns as $row)
            @php($c = $row['campaign'])
            <a href="{{ route('campaigns.show', $c) }}" wire:navigate class="card group flex flex-col p-5 transition hover:border-brand-300 hover:shadow-md dark:hover:border-brand-700">
                <div class="flex items-center justify-between gap-2"><x-badge :enum="$c->channel" /><x-badge :enum="$c->status" /></div>
                <h2 class="mt-3 font-semibold text-slate-900 group-hover:text-brand-700 dark:text-white dark:group-hover:text-brand-400">{{ $c->name }}</h2>
                <p class="text-sm text-slate-500">{{ $c->segment?->name }}</p>
                <dl class="mt-4 grid grid-cols-3 gap-2 border-t border-slate-100 pt-4 text-center dark:border-slate-800">
                    <div><dt class="text-xs text-slate-500">{{ $c->status === CampaignStatus::Sent ? 'Sent to' : 'Audience' }}</dt><dd class="font-bold tabular-nums">{{ $row['audience'] }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Opened</dt><dd class="font-bold tabular-nums">{{ $c->status === CampaignStatus::Sent ? $row['results']['open_rate'].'%' : '—' }}</dd></div>
                    <div><dt class="text-xs text-slate-500">Bookings</dt><dd class="font-bold tabular-nums">{{ $c->status === CampaignStatus::Sent ? $row['results']['conversions'] : '—' }}</dd></div>
                </dl>
                <p class="mt-3 text-xs text-slate-500">{{ $c->sent_at ? 'Sent '.fdate($c->sent_at) : 'Draft · created '.$c->created_at->diffForHumans() }}</p>
            </a>
        @empty
            <div class="card md:col-span-2 xl:col-span-3"><x-empty-state icon="megaphone" title="No campaigns yet" description="Start from a segment — e.g. lapsed travellers — and bring them back.">@can('marketing.manage')<x-button icon="plus" wire:click="$set('panel', 'new-campaign')">Create your first campaign →</x-button>@endcan</x-empty-state></div>
        @endforelse
    </div>

    <x-slide-over name="new-campaign" title="New campaign" description="Pick who it's for and how to reach them. You'll write the message next." width="max-w-md">
        <form wire:submit="create" id="camp-form" class="space-y-4">
            <x-field label="Campaign name" for="cf-name" error="form.name" required><input id="cf-name" wire:model="form.name" class="form-input" placeholder="e.g. Mara migration early bird"></x-field>
            <x-field label="Audience (segment)" for="cf-seg" error="form.segment_id" required>
                <select id="cf-seg" wire:model="form.segment_id" class="form-input"><option value="">Choose a segment…</option>@foreach ($segments as $s)<option value="{{ $s->id }}">{{ $s->name }}</option>@endforeach</select>
            </x-field>
            <x-field label="Channel">
                <div class="grid grid-cols-3 gap-2">
                    @foreach (CampaignChannel::cases() as $ch)
                        <label @class(['flex cursor-pointer flex-col items-center gap-1 rounded-xl border p-3 text-sm font-medium', 'border-brand-600 bg-brand-50 dark:bg-brand-400/10' => $form['channel'] === $ch->value, 'border-slate-200 dark:border-slate-700' => $form['channel'] !== $ch->value])>
                            <input type="radio" wire:model.live="form.channel" value="{{ $ch->value }}" class="sr-only"><x-hicon :name="$ch->icon()" />{{ $ch->label() }}
                        </label>
                    @endforeach
                </div>
            </x-field>
        </form>
        <x-slot:footer><x-button variant="secondary" wire:click="$set('panel', null)">Cancel</x-button><x-button type="submit" form="camp-form" loading="create">Continue to message</x-button></x-slot:footer>
    </x-slide-over>
</div>
