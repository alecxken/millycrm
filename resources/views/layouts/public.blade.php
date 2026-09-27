<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    @include('partials.head')
</head>
<body class="min-h-full bg-gradient-to-b from-brand-50 to-white font-sans text-slate-900 antialiased dark:from-slate-900 dark:to-slate-950 dark:text-slate-100">
    <div class="mx-auto flex min-h-full max-w-xl flex-col px-4 py-10">
        <div class="mb-8 flex items-center justify-center gap-3">
            <x-application-logo class="size-10" />
            <span class="text-lg font-bold">WanderLink Travel</span>
        </div>
        {{ $slot }}
        <p class="mt-10 text-center text-xs text-slate-500">Your feedback is stored securely and used only to improve our service (Kenya Data Protection Act 2019).</p>
    </div>
</body>
</html>
