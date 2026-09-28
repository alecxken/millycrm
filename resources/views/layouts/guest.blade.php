@php($media = config('wanderlink.login'))
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="h-full">
<head>
    @include('partials.head')
</head>
<body class="h-full font-sans antialiased">
<div class="flex min-h-full">
    {{-- Photo side. Gradient is painted on the container; the photo is alpha-masked on top,
         so a blocked image or CDN simply leaves a clean gradient. --}}
    <aside class="relative hidden w-0 flex-1 overflow-hidden bg-gradient-to-br from-brand-950 via-brand-900 to-brand-700 lg:block">
        <div class="pointer-events-none absolute inset-0 select-none [mask-image:linear-gradient(100deg,rgba(0,0,0,.35)_0%,#000_45%)]" aria-hidden="true">
            @if ($media['image'])
                <img src="{{ $media['image'] }}" alt="" class="size-full object-cover" loading="eager" decoding="async" referrerpolicy="no-referrer" onerror="this.remove()">
            @endif
            @if ($media['video'])
                <video x-data x-init="window.matchMedia('(prefers-reduced-motion: reduce)').matches ? $el.remove() : $el.play().catch(() => {})"
                       class="absolute inset-0 size-full object-cover" muted loop playsinline preload="metadata" @if($media['image']) poster="{{ $media['image'] }}" @endif>
                    <source src="{{ $media['video'] }}" type="video/mp4">
                </video>
            @endif
            {{-- Brand tint inside the mask keeps white type readable. --}}
            <div class="absolute inset-0 bg-gradient-to-t from-brand-950/85 via-brand-900/35 to-brand-900/10"></div>
        </div>

        <div class="relative flex h-full flex-col justify-between p-12 text-white xl:p-16">
            <div class="flex items-center gap-3">
                <x-application-logo class="size-11" />
                <div class="leading-tight"><p class="text-lg font-bold">WanderLink Travel</p><p class="text-[12px] font-normal text-white/70">Kimathi Street, Nairobi</p></div>
            </div>

            <div class="max-w-lg">
                <figure class="rounded-3xl border border-white/20 bg-white/10 p-6 backdrop-blur-md">
                    <blockquote class="text-[19px] leading-snug font-semibold">“Achieng remembered I don't eat meat before I'd even mentioned it. That's why we booked Diani with them again.”</blockquote>
                    <figcaption class="mt-4 flex items-center gap-3 text-[13px]">
                        <span class="flex size-9 items-center justify-center rounded-full bg-sand-500 text-[12px] font-bold text-[var(--accent-ink)]">WM</span>
                        <span><span class="block font-bold">Wanjiku M.</span><span class="block font-light text-white/70">Travelled with her family, April</span></span>
                    </figcaption>
                </figure>
                <p class="mt-8 text-[13.5px] font-light text-white/80">Every enquiry, quote, trip and thank-you note, kept together so nobody has to remember it all.</p>
            </div>

            <p class="text-[11px] font-normal text-white/50">{{ $media['credit'] }}</p>
        </div>
    </aside>

    <main class="relative flex flex-1 flex-col justify-center px-5 py-10 sm:px-8 lg:flex-none lg:px-16 xl:px-24">
        {{-- Mobile: the same photo as a soft band at the top --}}
        <div class="pointer-events-none absolute inset-x-0 top-0 h-56 overflow-hidden bg-gradient-to-br from-brand-900 to-brand-700 lg:hidden [mask-image:linear-gradient(180deg,#000_30%,transparent)]" aria-hidden="true">
            @if ($media['image'])<img src="{{ $media['image'] }}" alt="" class="size-full object-cover opacity-70" decoding="async" referrerpolicy="no-referrer" onerror="this.remove()">@endif
        </div>
        <div class="relative mx-auto w-full max-w-sm lg:w-[380px]">
            <div class="mb-8 flex items-center gap-3 lg:hidden">
                <x-application-logo class="size-10" />
                <span class="text-lg font-bold text-white drop-shadow">WanderLink</span>
            </div>
            <div class="card p-6 sm:p-8 lg:border-0 lg:bg-transparent lg:p-0 lg:shadow-none dark:lg:bg-transparent">
                {{ $slot }}
            </div>
        </div>
    </main>
</div>
</body>
</html>
