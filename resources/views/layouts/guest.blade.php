<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    @include('partials.head')
</head>
<body class="h-full bg-white font-sans text-slate-900 antialiased dark:bg-slate-950 dark:text-slate-100">
<div class="flex min-h-full">
    <div class="relative hidden w-0 flex-1 overflow-hidden bg-brand-900 lg:block">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_20%,rgba(245,158,11,0.25),transparent_40%),radial-gradient(circle_at_80%_70%,rgba(45,212,191,0.25),transparent_45%)]"></div>
        <div class="relative flex h-full flex-col justify-between p-12 text-white">
            <div class="flex items-center gap-3">
                <x-application-logo class="size-11" />
                <span class="text-xl font-bold">WanderLink Travel</span>
            </div>
            <div class="max-w-md">
                <h2 class="text-4xl font-bold leading-tight tracking-tight">Every traveller, every conversation, one place.</h2>
                <p class="mt-4 text-lg text-brand-100">The customer relationship system for our consultants, managers, marketing and support teams — from first WhatsApp enquiry to the next re-booking.</p>
                <dl class="mt-10 grid grid-cols-3 gap-6 text-sm">
                    <div><dt class="text-brand-200">Acquire</dt><dd class="mt-1 font-semibold">Capture every lead</dd></div>
                    <div><dt class="text-brand-200">Serve</dt><dd class="mt-1 font-semibold">Resolve within SLA</dd></div>
                    <div><dt class="text-brand-200">Retain</dt><dd class="mt-1 font-semibold">Bring them back</dd></div>
                </dl>
            </div>
            <p class="text-sm text-brand-200">BIS541 prototype · Nairobi, Kenya</p>
        </div>
    </div>
    <div class="flex flex-1 flex-col justify-center px-4 py-12 sm:px-6 lg:flex-none lg:px-20 xl:px-24">
        <div class="mx-auto w-full max-w-sm lg:w-96">
            <div class="mb-8 flex items-center gap-3 lg:hidden">
                <x-application-logo class="size-10" />
                <span class="text-lg font-bold">WanderLink CRM</span>
            </div>
            {{ $slot }}
        </div>
    </div>
</div>
</body>
</html>
