@extends('layouts.app')
@section('title', 'Home')
@section('content')
<div class="min-h-screen flex items-center justify-center p-6">
    <div class="max-w-3xl w-full">
        <h1 class="text-3xl font-bold mb-1">{{ $name }}</h1>
        <p class="text-slate-500 mb-8">AI-powered restaurant POS — counter, QR table ordering, kitchen display and owner dashboard.</p>
        @unless ($ready)
            <div class="bg-amber-100 text-amber-800 p-4 rounded-xl mb-6">No outlet yet. Run <code>php artisan migrate --seed</code> or open <a class="underline" href="{{ route('setup') }}">Setup</a>.</div>
        @endunless
        <div class="grid sm:grid-cols-2 gap-4">
            @foreach ([
                ['pos', 'POS Counter', 'Take orders fast — tap, type or speak (“2 kopi o, satu nasi lemak”).', '🧾'],
                ['kitchen', 'Kitchen Display', 'Live tickets by status. Tap to move Pending → Preparing → Ready.', '🍳'],
                ['admin', 'Owner Dashboard', 'Today’s sales, best sellers, AI insights, sold-out toggles, table QR codes.', '📊'],
                ['setup', '1-Click Setup', 'Restaurant name, SST & service charge, Gemini key, staff PIN.', '⚙️'],
            ] as [$r, $t, $d, $i])
                <a href="{{ route($r) }}" class="bg-white rounded-2xl p-5 shadow-sm hover:shadow-md transition">
                    <div class="text-3xl mb-2">{{ $i }}</div>
                    <div class="font-semibold text-lg">{{ $t }}</div>
                    <div class="text-sm text-slate-500">{{ $d }}</div>
                </a>
            @endforeach
        </div>
        <p class="text-xs text-slate-400 mt-6">Customers order from the QR code on their table (print them from the Dashboard).</p>
    </div>
</div>
@endsection
