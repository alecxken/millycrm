<?php

use App\Livewire\Forms\LoginForm;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    public LoginForm $form;

    public function login(): void
    {
        $this->validate();

        $this->form->authenticate();

        Session::regenerate();

        $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);
    }

    /** Demo convenience: fill the form with a seeded account. */
    public function useDemo(string $email): void
    {
        $this->form->email = $email;
        $this->form->password = 'password';
    }
}; ?>

<div>
    <p class="eyebrow">Karibu tena</p>
    <h1 class="mt-1 text-[27px] leading-tight font-bold text-brand-900 dark:text-white">Good to see you</h1>
    <p class="mt-1.5 text-[13.5px] font-light text-slate-500 dark:text-slate-400">Sign in to pick up where you left off.</p>

    <x-auth-session-status class="mt-4" :status="session('status')" />

    <form wire:submit="login" class="mt-8 space-y-5">
        <x-field label="Email" for="email" error="form.email">
            <input wire:model="form.email" id="email" type="email" name="email" required autofocus autocomplete="username" class="form-input" placeholder="you@wanderlink.test">
        </x-field>

        <x-field label="Password" for="password" error="form.password">
            <input wire:model="form.password" id="password" type="password" name="password" required autocomplete="current-password" class="form-input">
        </x-field>

        <div class="flex items-center justify-between">
            <label for="remember" class="inline-flex items-center gap-2 text-sm text-slate-600 dark:text-slate-400">
                <input wire:model="form.remember" id="remember" type="checkbox" class="rounded border-slate-300 text-brand-700 focus:ring-brand-600 dark:border-slate-700 dark:bg-slate-900">
                Remember me
            </label>
            @if (Route::has('password.request'))
                <a class="link text-sm" href="{{ route('password.request') }}" wire:navigate>Forgot password?</a>
            @endif
        </div>

        <x-button type="submit" size="lg" class="w-full" loading="login" pill>Sign in</x-button>
    </form>

    <div class="mt-10">
        <div class="flex items-center gap-3 text-xs font-semibold tracking-wider text-slate-400 uppercase">
            <span class="h-px flex-1 bg-slate-200 dark:bg-slate-800"></span>Try it as<span class="h-px flex-1 bg-slate-200 dark:bg-slate-800"></span>
        </div>
        <div class="mt-4 grid grid-cols-1 gap-2 sm:grid-cols-2">
            @foreach ([
                ['owner@wanderlink.test', 'David', 'Owner', 'amber', 'key'],
                ['manager@wanderlink.test', 'Faith', 'Manager', 'violet', 'briefcase'],
                ['consultant@wanderlink.test', 'Achieng', 'Consultant', 'teal', 'user'],
                ['marketing@wanderlink.test', 'Zawadi', 'Marketing', 'rose', 'megaphone'],
                ['support@wanderlink.test', 'Grace', 'Support', 'sky', 'lifebuoy'],
            ] as [$email, $name, $role, $color, $icon])
                <button type="button" wire:click="useDemo('{{ $email }}')"
                        class="flex items-center gap-3 rounded-xl border-[1.5px] border-slate-200 bg-white px-3 py-2 text-left transition hover:-translate-y-px hover:border-brand-400 hover:bg-[var(--hover-wash)] dark:border-slate-800 dark:bg-slate-900 dark:hover:border-brand-700">
                    <x-badge :color="$color" :icon="$icon" :label="$role" size="xs" />
                    <span class="text-sm font-medium">{{ $name }}</span>
                </button>
            @endforeach
        </div>
        <p class="mt-3 text-[11.5px] font-normal text-slate-500 dark:text-slate-400">Tap a person to fill in their details. Demo password: <code class="rounded bg-slate-100 px-1 font-semibold dark:bg-slate-800">password</code></p>
    </div>
</div>
