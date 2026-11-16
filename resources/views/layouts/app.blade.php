<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="staff-token" content="{{ $staffToken ?? '' }}">
    <title>@yield('title', 'FeedYou') · {{ $name ?? config('app.name') }}</title>
    {{-- Served locally so the POS keeps working when the shop's internet drops. --}}
    <script src="{{ asset('vendor/tailwind-browser.js') }}"></script>
    <script src="{{ asset('js/pos-common.js') }}"></script>
    @stack('head')
</head>
<body class="bg-slate-100 text-slate-800 antialiased @yield('body-class')">
    @hasSection('nav')
        <nav class="bg-slate-900 text-white px-4 h-12 flex items-center gap-1 text-sm">
            <a href="/" class="font-bold mr-4 text-orange-400">{{ $name ?? 'FeedYou' }}</a>
            @foreach (['pos' => 'POS', 'kitchen' => 'Kitchen', 'admin' => 'Dashboard', 'setup' => 'Setup'] as $route => $label)
                <a href="{{ route($route) }}" class="px-3 py-1.5 rounded {{ request()->routeIs($route) ? 'bg-white/15' : 'hover:bg-white/10' }}">{{ $label }}</a>
            @endforeach
            <form method="POST" action="{{ route('staff.logout') }}" class="ml-auto">@csrf<button class="px-3 py-1.5 hover:bg-white/10 rounded">Lock</button></form>
        </nav>
    @endif
    @yield('content')
    @stack('scripts')
</body>
</html>
