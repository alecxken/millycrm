<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#0F766E">
<title>{{ isset($title) ? $title.' · ' : '' }}WanderLink CRM</title>
<link rel="icon" href="data:image/svg+xml,{{ rawurlencode('<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 40 40"><rect width="40" height="40" rx="10" fill="#0F766E"/><circle cx="20" cy="20" r="11" stroke="#F59E0B" stroke-width="3" fill="none"/><path d="M24.5 15.5 21.8 21.8 15.5 24.5l2.7-6.3 6.3-2.7Z" fill="#F59E0B"/></svg>') }}">
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet" />
@include('partials.theme-script')
@vite(['resources/css/app.css', 'resources/js/app.js'])
