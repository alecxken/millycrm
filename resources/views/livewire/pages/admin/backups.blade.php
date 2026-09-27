<?php

use App\Services\BackupService;
use Livewire\Attributes\Title;
use Livewire\Volt\Component;

new #[Title('Backups')] class extends Component
{
    public function backup(BackupService $backups): void
    {
        abort_unless(auth()->user()->can('admin.backup'), 403);
        $path = $backups->run();
        activity()->causedBy(auth()->user())->log('database backup created: '.basename($path));
        $this->dispatch('toast', message: 'Backup created: '.basename($path));
    }

    public function with(BackupService $backups): array
    {
        return ['backups' => $backups->list()];
    }
}; ?>

<div>
    <x-page-header title="Backups" subtitle="Point-in-time copies of the CRM database. A nightly backup runs automatically at 02:00.">
        <x-slot:actions><x-button icon="circle-stack" wire:click="backup">Back up now</x-button></x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <x-stat label="Backups on disk" :value="$backups->count()" icon="circle-stack" />
        <x-stat label="Latest" :value="$backups->first() ? $backups->first()['created_at']->diffForHumans() : 'Never'" icon="clock" tone="sky" />
        <x-stat label="Location" value="storage/app/backups" icon="folder" tone="violet" hint="Copy off-site for disaster recovery" />
    </div>

    <div class="card overflow-hidden">
        @if ($backups->isEmpty())
            <x-empty-state icon="circle-stack" title="No backups yet" description="Create the first one now — or run php artisan crm:backup.">
                <x-button icon="circle-stack" wire:click="backup">Create first backup →</x-button>
            </x-empty-state>
        @else
            <table class="table-base">
                <thead class="bg-slate-50 dark:bg-slate-900/60"><tr><th>File</th><th>Created</th><th class="text-right">Size</th><th></th></tr></thead>
                <tbody class="divide-y divide-slate-100 dark:divide-slate-800">
                    @foreach ($backups as $b)
                        <tr>
                            <td class="font-mono text-xs">{{ $b['name'] }}</td>
                            <td class="text-xs">{{ fdate($b['created_at'], true) }}</td>
                            <td class="text-right text-xs tabular-nums">{{ number_format($b['size'] / 1024 / 1024, 2) }} MB</td>
                            <td class="text-right"><x-button size="sm" variant="secondary" icon="arrow-down-tray" :href="route('admin.backups.download', $b['name'])">Download</x-button></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>
</div>
