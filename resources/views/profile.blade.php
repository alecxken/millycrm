<x-app-layout>
    <x-page-header title="Profile" subtitle="Your details and password." />
    <div class="max-w-2xl space-y-6">
        <div class="card p-6"><livewire:profile.update-profile-information-form /></div>
        <div class="card p-6"><livewire:profile.update-password-form /></div>
    </div>
</x-app-layout>
