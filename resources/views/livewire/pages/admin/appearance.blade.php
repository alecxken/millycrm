<?php

use App\Services\ThemeService;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('Appearance')] class extends Component
{
    public string $preset = 'wanderlink';

    public string $brand = '#0F766E';

    public string $accent = '#F59E0B';

    public string $font = 'quicksand';

    public string $corners = 'soft';

    public bool $wash = true;

    public function mount(ThemeService $theme): void
    {
        $this->fill($theme->current());
    }

    public function applyPreset(string $key): void
    {
        abort_unless(isset(ThemeService::PRESETS[$key]), 404);
        $this->preset = $key;
        $this->brand = ThemeService::PRESETS[$key]['brand'];
        $this->accent = ThemeService::PRESETS[$key]['accent'];
    }

    public function updatedBrand(): void
    {
        $this->preset = 'custom';
    }

    public function updatedAccent(): void
    {
        $this->preset = 'custom';
    }

    public function save(ThemeService $theme): void
    {
        abort_unless(auth()->user()->can('settings.manage'), 403);

        $data = $this->validate([
            'brand' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'accent' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'font' => ['required', Rule::in(array_keys(ThemeService::FONTS))],
            'corners' => ['required', Rule::in(array_keys(ThemeService::CORNERS))],
            'wash' => ['boolean'],
            'preset' => ['required', 'string', 'max:20'],
        ], ['brand.regex' => 'Use a hex colour like #0F766E.', 'accent.regex' => 'Use a hex colour like #F59E0B.']);

        $theme->save($data);
        activity()->causedBy(auth()->user())->withProperties($data)->log('updated the agency theme');

        $this->dispatch('toast', message: 'Looking good. The new theme is live for everyone.');
    }

    public function resetTheme(ThemeService $theme): void
    {
        abort_unless(auth()->user()->can('settings.manage'), 403);
        $this->fill($theme->reset());
        $this->dispatch('toast', message: 'Back to the original WanderLink look.', type: 'warning');
    }

    public function with(): array
    {
        return [
            'presets' => ThemeService::PRESETS,
            'fonts' => ThemeService::FONTS,
            'cornerOptions' => ThemeService::CORNERS,
            'brandContrast' => ThemeService::readableOn($this->brand),
        ];
    }
}; ?>

{{-- Live preview: every change is applied to the page's CSS variables immediately. --}}
<div x-data="{
        fonts: @js(collect($fonts)->map(fn ($f) => $f['stack'])),
        scales: @js(collect($cornerOptions)->map(fn ($c) => $c['scale'])),
        ink(hex) {
            const c = hex.replace('#', '').match(/.{2}/g).map(v => parseInt(v, 16) / 255).map(v => v <= 0.03928 ? v / 12.92 : ((v + 0.055) / 1.055) ** 2.4);
            return (0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2]) > 0.45 ? '#1F2937' : '#FFFFFF';
        },
        valid(hex) { return /^#[0-9a-fA-F]{6}$/.test(hex) },
        apply() {
            const r = document.documentElement.style;
            if (this.valid($wire.brand)) { r.setProperty('--brand', $wire.brand); r.setProperty('--brand-ink', this.ink($wire.brand)); }
            if (this.valid($wire.accent)) { r.setProperty('--accent', $wire.accent); r.setProperty('--accent-ink', this.ink($wire.accent)); }
            r.setProperty('--app-font', this.fonts[$wire.font] ?? this.fonts.quicksand);
            r.setProperty('--radius-scale', this.scales[$wire.corners] ?? 1);
            r.setProperty('--wash-opacity', $wire.wash ? 1 : 0);
        },
     }"
     x-effect="apply()">
    <x-page-header title="Appearance" eyebrow="Settings" subtitle="Make the CRM feel like your agency. Changes preview instantly and go live for everyone when you save.">
        <x-slot:actions>
            <x-button variant="ghost" wire:click="resetTheme" wire:confirm="Go back to the original WanderLink theme?">Reset</x-button>
            <x-button icon="check" wire:click="save" loading="save">Save theme</x-button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid grid-cols-1 items-start gap-6 lg:grid-cols-[1fr_360px]">
        <div class="min-w-0 space-y-6">
            <x-card title="Start from a palette" icon="swatch">
                <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
                    @foreach ($presets as $key => $p)
                        <button type="button" wire:click="applyPreset('{{ $key }}')" aria-pressed="{{ $preset === $key ? 'true' : 'false' }}"
                                @class(['group rounded-2xl border-[1.5px] p-3 text-left transition-all hover:-translate-y-0.5', 'border-brand-600 bg-brand-50/60 dark:bg-brand-400/10' => $preset === $key, 'border-slate-200 hover:border-slate-300 dark:border-slate-700' => $preset !== $key])>
                            <div class="flex h-14 overflow-hidden rounded-xl">
                                <span class="flex-[3]" style="background: {{ $p['brand'] }}"></span>
                                <span class="flex-1" style="background: {{ $p['accent'] }}"></span>
                            </div>
                            <p class="mt-2.5 flex items-center justify-between text-[13px] font-bold text-slate-700 dark:text-white">{{ $p['label'] }} @if ($preset === $key)<x-hicon name="check-circle" class="size-4 text-brand-700" solid />@endif</p>
                            <p class="text-[11px] font-normal text-slate-500">{{ $p['note'] }}</p>
                        </button>
                    @endforeach
                </div>
            </x-card>

            <x-card title="Colours" subtitle="Brand is for actions, links and “you are here”. Accent is used sparingly, as punctuation." icon="paint-brush">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    @foreach (['brand' => 'Brand colour', 'accent' => 'Accent colour'] as $field => $label)
                        <x-field :label="$label" :for="'c-'.$field" :error="$field">
                            <div class="flex items-center gap-3">
                                <label class="relative size-12 shrink-0 cursor-pointer overflow-hidden rounded-xl border border-slate-200 dark:border-slate-700" style="background: {{ ${$field} }}">
                                    <input type="color" wire:model.live="{{ $field }}" class="absolute inset-0 size-full cursor-pointer opacity-0" aria-label="Pick {{ strtolower($label) }}">
                                </label>
                                <input id="c-{{ $field }}" wire:model.live.debounce.400ms="{{ $field }}" class="form-input font-mono uppercase" maxlength="7">
                            </div>
                        </x-field>
                    @endforeach
                </div>
                @if ($brandContrast === '#1F2937')
                    <p class="mt-4 flex items-start gap-2 rounded-xl bg-[#FFF3E0] px-3 py-2.5 text-[12px] font-semibold text-[#8a4d00]"><x-hicon name="light-bulb" class="size-4 shrink-0" />That's a light brand colour, so buttons will use dark text to stay readable. A deeper shade usually looks better for links.</p>
                @endif
            </x-card>

            <x-card title="Typeface" subtitle="Self-hosted, so it loads even on a locked-down office network." icon="language">
                <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    @foreach ($fonts as $key => $f)
                        <label @class(['flex cursor-pointer items-start gap-3 rounded-2xl border-[1.5px] p-4 transition', 'border-brand-600 bg-brand-50/60 dark:bg-brand-400/10' => $font === $key, 'border-slate-200 hover:border-slate-300 dark:border-slate-700' => $font !== $key])>
                            <input type="radio" wire:model.live="font" value="{{ $key }}" class="mt-1 text-brand-700 focus:ring-brand-600">
                            <span class="min-w-0">
                                <span class="block text-[22px] leading-tight font-bold text-slate-700 dark:text-white" style="font-family: {{ $f['stack'] }}">Karibu, Achieng</span>
                                <span class="mt-1 block text-[12px] font-semibold text-slate-600 dark:text-slate-300" style="font-family: {{ $f['stack'] }}">{{ $f['label'] }} · <span class="font-light">{{ $f['note'] }}</span></span>
                            </span>
                        </label>
                    @endforeach
                </div>
            </x-card>

            <x-card title="Shape & background" icon="squares-2x2">
                <div class="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <fieldset>
                        <legend class="form-label">Corners</legend>
                        <div class="flex gap-2">
                            @foreach ($cornerOptions as $key => $c)
                                <label @class(['flex flex-1 cursor-pointer flex-col items-center gap-2 border-[1.5px] p-3 text-[12px] font-bold transition', 'border-brand-600 bg-brand-50/60 text-brand-800 dark:bg-brand-400/10 dark:text-brand-200' => $corners === $key, 'border-slate-200 text-slate-500 dark:border-slate-700' => $corners !== $key]) style="border-radius: {{ 16 * $c['scale'] }}px">
                                    <input type="radio" wire:model.live="corners" value="{{ $key }}" class="sr-only">
                                    <span class="block size-8 border-2 border-current" style="border-radius: {{ 12 * $c['scale'] }}px"></span>
                                    {{ $c['label'] }}
                                </label>
                            @endforeach
                        </div>
                    </fieldset>
                    <div>
                        <p class="form-label">Background</p>
                        <label class="flex cursor-pointer items-start gap-3 rounded-2xl border-[1.5px] border-slate-200 p-4 dark:border-slate-700">
                            <input type="checkbox" wire:model.live="wash" class="mt-0.5 rounded border-slate-300 text-brand-700 focus:ring-brand-600">
                            <span class="text-[13px]"><span class="font-bold text-slate-700 dark:text-white">Soft light wash</span><br><span class="font-light text-slate-500">A gentle glow of your brand colours behind every screen. Turn off for a plain background.</span></span>
                        </label>
                    </div>
                </div>
            </x-card>
        </div>

        {{-- Preview rail --}}
        <aside class="space-y-4 lg:sticky lg:top-6">
            <p class="eyebrow">Preview</p>
            <div class="card overflow-hidden">
                <div class="bg-gradient-to-br from-brand-900 via-brand-800 to-brand-700 p-5 text-white">
                    <p class="text-[10px] font-bold tracking-[0.16em] text-white/60 uppercase">Next departure</p>
                    <p class="mt-1 text-[22px] font-bold">Zanzibar, 4 nights</p>
                    <p class="text-[12.5px] font-normal text-white/75">Wanjiku & family · leaves Friday</p>
                    <span class="mt-3 inline-flex items-center gap-1.5 rounded-full border border-white/25 bg-white/15 px-3 py-1 text-[11px] font-semibold"><x-hicon name="check-circle" class="size-3.5" /> Paid in full</span>
                </div>
                <div class="space-y-4 p-5">
                    <div class="flex flex-wrap gap-2">
                        <x-button size="sm" icon="plus">New enquiry</x-button>
                        <x-button size="sm" variant="accent">Send quote</x-button>
                        <x-button size="sm" variant="secondary">Later</x-button>
                    </div>
                    <div class="flex flex-wrap gap-1.5">
                        <x-badge :enum="\App\Enums\LifecycleStage::Vip" />
                        <x-badge :enum="\App\Enums\EnquiryStatus::Quoted" />
                        <x-badge :enum="\App\Enums\PaymentStatus::Paid" />
                    </div>
                    <div class="flex items-center gap-3 rounded-2xl border border-[var(--hairline)] p-3">
                        <span class="flex size-10 items-center justify-center rounded-xl bg-brand-50 text-brand-700 dark:bg-brand-400/15 dark:text-brand-200"><x-hicon name="banknotes" /></span>
                        <div><p class="text-xl leading-none font-bold text-brand-900 dark:text-white">KES 1.2M</p><p class="mt-1 text-[11px] font-semibold text-slate-500">Booked this month</p></div>
                    </div>
                    <p class="text-[13px] text-slate-600 dark:text-slate-300">Achieng followed up with <a href="#" class="link" x-on:click.prevent>Otieno Kamau</a> about the Mara in July.</p>
                </div>
            </div>
            <p class="text-[11.5px] font-normal text-slate-500">Everyone's light/dark preference is still their own; this sets the agency's colours, type and shape.</p>
        </aside>
    </div>
</div>
