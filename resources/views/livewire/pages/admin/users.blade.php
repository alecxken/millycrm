<?php

use App\Enums\Role;
use App\Models\User;
use App\Support\Permissions;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('Staff & roles')] class extends Component
{
    public ?string $panel = null;

    public array $form = ['name' => '', 'email' => '', 'job_title' => '', 'role' => 'consultant'];

    public ?string $tempPassword = null;

    public function changeRole(int $id, string $role): void
    {
        $user = User::findOrFail($id);
        $this->authorize('update', $user);
        $user->syncRoles([Role::from($role)->value]);
        activity()->performedOn($user)->causedBy(auth()->user())->withProperties(['role' => $role])->log('changed role');
        $this->dispatch('toast', message: "{$user->name} is now ".Role::from($role)->label().'.');
    }

    public function toggleActive(int $id): void
    {
        $user = User::findOrFail($id);
        $this->authorize('update', $user);
        $user->update(['is_active' => ! $user->is_active]);
        $this->dispatch('toast', message: $user->is_active ? "{$user->name} can sign in again." : "{$user->name} has been deactivated and can no longer sign in.", type: $user->is_active ? 'success' : 'warning');
    }

    public function create(): void
    {
        $this->authorize('viewAny', User::class);
        $data = $this->validate([
            'form.name' => 'required|string|max:120',
            'form.email' => 'required|email|unique:users,email',
            'form.job_title' => 'nullable|string|max:120',
            'form.role' => ['required', Rule::enum(Role::class)],
        ], attributes: ['form.name' => 'name', 'form.email' => 'email'])['form'];

        $this->tempPassword = Str::password(12, symbols: false);
        $user = User::create([...collect($data)->except('role')->all(), 'password' => $this->tempPassword, 'avatar_color' => collect(['teal', 'sky', 'violet', 'rose', 'indigo', 'emerald'])->random(), 'email_verified_at' => now()]);
        $user->assignRole($data['role']);
        $this->form = ['name' => '', 'email' => '', 'job_title' => '', 'role' => 'consultant'];
        $this->panel = 'created';
    }

    public function with(): array
    {
        return [
            'users' => User::with('roles')->withCount(['customers', 'enquiries' => fn ($q) => $q->open()])->orderBy('name')->get(),
            'matrix' => Permissions::matrix(),
        ];
    }
}; ?>

<div>
    <x-page-header title="Staff & roles" subtitle="Role-based access: each person sees only the modules their job needs.">
        <x-slot:actions><x-button icon="user-plus" wire:click="$set('panel', 'new')">Add staff member</x-button></x-slot:actions>
    </x-page-header>

    <div class="card mb-6 overflow-hidden">
        <div class="overflow-x-auto">
            <table class="table-base">
                <thead class="bg-slate-50 dark:bg-slate-900/60"><tr><th>Person</th><th>Role</th><th class="text-right">Customers</th><th class="text-right">Open enquiries</th><th>Status</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($users as $u)
                        <tr wire:key="u-{{ $u->id }}" @class(['opacity-60' => ! $u->is_active])>
                            <td><div class="flex items-center gap-3"><x-avatar :user="$u" size="sm" /><div><p class="font-semibold text-slate-900 dark:text-white">{{ $u->name }}</p><p class="text-xs text-slate-500">{{ $u->email }} · {{ $u->job_title }}</p></div></div></td>
                            <td>
                                @can('update', $u)
                                    <select class="form-input w-40 py-1.5" aria-label="Role for {{ $u->name }}" x-on:change="$wire.changeRole({{ $u->id }}, $event.target.value)">
                                        @foreach (Role::cases() as $r)<option value="{{ $r->value }}" @selected($u->hasRole($r->value))>{{ $r->label() }}</option>@endforeach
                                    </select>
                                @else
                                    <x-badge :enum="$u->primaryRole()" /> <span class="text-xs text-slate-500">(you)</span>
                                @endcan
                            </td>
                            <td class="text-right tabular-nums">{{ $u->customers_count }}</td>
                            <td class="text-right tabular-nums">{{ $u->enquiries_count }}</td>
                            <td>@if ($u->is_active)<x-badge color="emerald" icon="check-circle" label="Active" />@else<x-badge color="slate" icon="no-symbol" label="Deactivated" />@endif</td>
                            <td class="text-right">@can('update', $u)<x-button size="sm" variant="ghost" wire:click="toggleActive({{ $u->id }})" wire:confirm="{{ $u->is_active ? 'Deactivate '.$u->name.'? They will be unable to sign in.' : 'Reactivate '.$u->name.'?' }}">{{ $u->is_active ? 'Deactivate' : 'Reactivate' }}</x-button>@endcan</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <x-card title="Permission matrix" subtitle="Single source of truth: App\Support\Permissions" :padding="false">
        <div class="overflow-x-auto">
            <table class="table-base">
                <thead class="bg-slate-50 dark:bg-slate-900/60"><tr><th>Permission</th>@foreach (Role::cases() as $r)<th class="text-center">{{ $r->label() }}</th>@endforeach</tr></thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach (Permissions::ALL as $perm => $label)
                        <tr>
                            <td><p class="font-medium">{{ $label }}</p><p class="font-mono text-[11px] text-slate-500">{{ $perm }}</p></td>
                            @foreach (Role::cases() as $r)
                                <td class="text-center">
                                    @if (in_array($perm, $matrix[$r->value], true))<x-hicon name="check-circle" class="mx-auto size-5 text-emerald-600" solid /><span class="sr-only">Yes</span>@else<x-hicon name="minus" class="mx-auto size-4 text-slate-300 dark:text-slate-700" /><span class="sr-only">No</span>@endif
                                </td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-card>

    <x-slide-over name="new" title="Add staff member" width="max-w-md">
        <form wire:submit="create" id="user-form" class="space-y-4">
            <x-field label="Full name" for="u-name" error="form.name" required><input id="u-name" wire:model="form.name" class="form-input"></x-field>
            <x-field label="Work email" for="u-email" error="form.email" required><input id="u-email" type="email" wire:model="form.email" class="form-input"></x-field>
            <x-field label="Job title" for="u-title"><input id="u-title" wire:model="form.job_title" class="form-input"></x-field>
            <x-field label="Role" for="u-role"><select id="u-role" wire:model="form.role" class="form-input">@foreach (Role::options() as $v => $l)<option value="{{ $v }}">{{ $l }}</option>@endforeach</select></x-field>
        </form>
        <x-slot:footer><x-button variant="secondary" wire:click="$set('panel', null)">Cancel</x-button><x-button type="submit" form="user-form" loading="create">Create account</x-button></x-slot:footer>
    </x-slide-over>

    <x-slide-over name="created" title="Account created" width="max-w-md">
        <p class="text-sm">Share this one-time password securely. They should change it from their profile after first sign-in.</p>
        <p class="mt-4 rounded-xl bg-slate-900 p-4 text-center font-mono text-lg tracking-wider text-white">{{ $tempPassword }}</p>
    </x-slide-over>
</div>
