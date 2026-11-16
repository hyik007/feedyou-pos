@extends('layouts.app')
@section('title', 'Setup')
@section('nav', true)
@section('content')
<div class="max-w-lg mx-auto p-6">
    <form method="POST" action="/setup" class="bg-white p-6 rounded-2xl shadow-sm space-y-4">
        @csrf
        <h2 class="text-xl font-bold">1-Click POS Setup</h2>
        @if (session('success'))<div class="bg-emerald-100 text-emerald-700 p-2 rounded">{{ session('success') }}</div>@endif
        @if ($errors->any())<div class="bg-rose-100 text-rose-700 p-2 rounded">{{ $errors->first() }}</div>@endif

        <label class="block"><span class="text-sm font-semibold">Restaurant name</span>
            <input name="restaurant_name" value="{{ old('restaurant_name', $restaurantName) }}" class="mt-1 w-full border p-2 rounded" required></label>
        <label class="block"><span class="text-sm font-semibold">Address</span>
            <input name="address" value="{{ old('address', $outlet?->address) }}" class="mt-1 w-full border p-2 rounded"></label>
        <div class="grid grid-cols-2 gap-3">
            <label class="block"><span class="text-sm font-semibold">SST %</span>
                <input name="tax_rate" type="number" step="0.01" value="{{ old('tax_rate', ($outlet?->tax_rate_bp ?? 600) / 100) }}" class="mt-1 w-full border p-2 rounded"></label>
            <label class="block"><span class="text-sm font-semibold">Service charge % <span class="font-normal text-slate-400">(dine-in)</span></span>
                <input name="service_charge" type="number" step="0.01" value="{{ old('service_charge', ($outlet?->service_charge_bp ?? 1000) / 100) }}" class="mt-1 w-full border p-2 rounded"></label>
        </div>
        <label class="block"><span class="text-sm font-semibold">Gemini API key</span>
            <span class="text-xs ml-1 {{ $hasApiKey ? 'text-emerald-600' : 'text-slate-400' }}">{{ $hasApiKey ? '● configured' : '○ not set — AI falls back to offline rules' }}</span>
            <input name="gemini_api_key" type="password" autocomplete="off" placeholder="{{ $hasApiKey ? 'leave blank to keep current key' : 'AIza…' }}" class="mt-1 w-full border p-2 rounded"></label>
        <label class="block"><span class="text-sm font-semibold">Staff PIN</span>
            <span class="text-xs ml-1 {{ $hasPin ? 'text-emerald-600' : 'text-amber-600' }}">{{ $hasPin ? '● set' : '○ not set — staff screens are open' }}</span>
            <input name="staff_pin" type="password" inputmode="numeric" placeholder="{{ $hasPin ? 'leave blank to keep' : '4–8 digits' }}" class="mt-1 w-full border p-2 rounded"></label>
        <button class="w-full bg-orange-500 text-white font-semibold px-4 py-3 rounded-xl">Save settings</button>
    </form>
</div>
@endsection
