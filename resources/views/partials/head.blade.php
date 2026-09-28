<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="robots" content="noindex,nofollow">
<title>{{ isset($title) ? $title.' · ' : '' }}WanderLink CRM</title>
<link rel="icon" href="data:image/svg+xml,{{ rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40"><rect width="40" height="40" rx="12" fill="'.app(\App\Services\ThemeService::class)->current()['brand'].'"/><circle cx="20" cy="20" r="11" stroke="#fff" stroke-opacity=".9" stroke-width="3" fill="none"/><path d="M24.5 15.5 21.8 21.8 15.5 24.5l2.7-6.3 6.3-2.7Z" fill="'.app(\App\Services\ThemeService::class)->current()['accent'].'"/></svg>') }}">
<meta name="theme-color" content="{{ app(\App\Services\ThemeService::class)->current()['brand'] }}">
{{-- Agency theme from Settings → Appearance --}}
<style id="theme-vars">{!! app(\App\Services\ThemeService::class)->cssVariables() !!}</style>
@include('partials.theme-script')
@vite(['resources/css/app.css', 'resources/js/app.js'])
