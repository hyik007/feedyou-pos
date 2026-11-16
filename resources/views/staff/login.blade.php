@extends('layouts.app')
@section('title', 'Staff login')
@section('content')
<div class="min-h-screen flex items-center justify-center p-6">
    <form method="POST" action="/login" class="bg-white rounded-2xl shadow p-6 w-full max-w-xs text-center">
        @csrf
        <div class="text-3xl mb-2">🔒</div>
        <h1 class="font-bold text-lg mb-4">Staff PIN</h1>
        <input name="pin" type="password" inputmode="numeric" autofocus autocomplete="off"
               class="w-full text-center text-2xl tracking-widest border rounded-xl p-3 mb-3" placeholder="••••">
        @error('pin')<p class="text-rose-600 text-sm mb-3">{{ $message }}</p>@enderror
        <button class="w-full bg-orange-500 text-white font-semibold rounded-xl py-3">Unlock</button>
    </form>
</div>
@endsection
