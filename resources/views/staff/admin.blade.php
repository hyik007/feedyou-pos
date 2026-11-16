@extends('layouts.app')
@section('title', 'Dashboard')
@section('nav', true)
@push('head')<script src="{{ asset('vendor/qrcode.js') }}"></script>@endpush
@section('content')
<div class="max-w-6xl mx-auto p-4 space-y-4">
    <div class="flex items-center gap-3">
        <h1 class="text-2xl font-bold">Today</h1>
        <input type="date" id="date" class="border rounded-lg p-1.5 text-sm" value="{{ now()->toDateString() }}">
    </div>

    <div class="grid grid-cols-2 md:grid-cols-4 gap-3" id="kpis"></div>

    <div class="grid md:grid-cols-3 gap-4">
        <section class="bg-white rounded-2xl p-4 md:col-span-2">
            <h2 class="font-semibold mb-3">Sales by hour</h2>
            <div id="hours" class="flex items-end gap-1 h-40"></div>
        </section>
        <section class="bg-white rounded-2xl p-4">
            <h2 class="font-semibold mb-3">Best sellers</h2>
            <ol id="top" class="space-y-2 text-sm"></ol>
        </section>
    </div>

    <section class="bg-gradient-to-r from-slate-900 to-slate-700 text-white rounded-2xl p-4">
        <div class="flex justify-between items-center mb-2">
            <h2 class="font-semibold">✨ AI insights</h2>
            <button id="insight-btn" class="bg-white/15 hover:bg-white/25 rounded-lg px-3 py-1.5 text-sm">Generate</button>
        </div>
        <pre id="insights" class="whitespace-pre-wrap font-sans text-sm text-slate-200">Click Generate for tips based on today's sales.</pre>
    </section>

    <div class="grid md:grid-cols-2 gap-4">
        <section class="bg-white rounded-2xl p-4">
            <h2 class="font-semibold mb-3">Menu availability</h2>
            <div id="menu" class="divide-y text-sm max-h-96 overflow-y-auto"></div>
        </section>
        <section class="bg-white rounded-2xl p-4">
            <div class="flex justify-between mb-3"><h2 class="font-semibold">Table QR codes</h2><button onclick="window.print()" class="text-sm underline">Print</button></div>
            <div class="grid grid-cols-3 sm:grid-cols-4 gap-3">
                @foreach ($tables as $t)
                    <a href="{{ $t->qrUrl() }}" target="_blank" class="text-center border rounded-xl p-2">
                        <div class="qr mx-auto" data-url="{{ $t->qrUrl() }}"></div>
                        <div class="font-bold mt-1">{{ $t->code }}</div>
                    </a>
                @endforeach
            </div>
        </section>
    </div>
</div>
@endsection

@push('scripts')
<script>
const { api, rm, esc, toast } = POS;
const $ = (s) => document.querySelector(s);

async function loadReport() {
  const r = await api('/reports/daily?date=' + $('#date').value);
  const kpi = (label, value) => `<div class="bg-white rounded-2xl p-4"><div class="text-xs text-slate-500">${label}</div><div class="text-2xl font-bold">${value}</div></div>`;
  $('#kpis').innerHTML = kpi('Gross sales', rm(r.gross_sen)) + kpi('Orders', r.orders) + kpi('Avg ticket', rm(r.avg_ticket_sen))
    + kpi('Open orders', r.open_orders);
  const hours = Object.entries(r.by_hour);
  const max = Math.max(1, ...hours.map(([, v]) => v.revenue_sen));
  $('#hours').innerHTML = hours.length ? hours.map(([h, v]) => `
    <div class="flex-1 flex flex-col items-center justify-end h-full" title="${rm(v.revenue_sen)} · ${v.orders} orders">
      <div class="w-full bg-orange-400 rounded-t" style="height:${Math.max(4, v.revenue_sen / max * 100)}%"></div>
      <div class="text-[10px] text-slate-500 mt-1">${h}</div></div>`).join('') : '<p class="text-slate-400 self-center">No sales yet.</p>';
  $('#top').innerHTML = r.top_items.map((t) => `<li class="flex justify-between"><span>${esc(t.name)}</span><span class="text-slate-500">${t.qty} · ${rm(+t.revenue_sen)}</span></li>`).join('') || '<li class="text-slate-400">—</li>';
}

async function loadMenu() {
  const m = await api('/menu');
  $('#menu').innerHTML = m.categories.flatMap((c) => c.items).map((i) => `
    <label class="flex items-center justify-between py-2">
      <span>${esc(i.name)} <span class="text-slate-400">${rm(i.price_sen)}</span></span>
      <span class="flex items-center gap-2 text-xs ${i.is_available ? 'text-emerald-600' : 'text-rose-600'}">${i.is_available ? 'Available' : 'Sold out'}
        <input type="checkbox" data-avail="${i.id}" ${i.is_available ? 'checked' : ''} class="w-5 h-5 accent-emerald-600"></span>
    </label>`).join('');
}

document.addEventListener('change', async (e) => {
  if (e.target.dataset.avail) {
    try { await api(`/menu/items/${e.target.dataset.avail}/availability`, { method: 'PATCH', body: { is_available: e.target.checked } }); loadMenu(); }
    catch (err) { toast(err.message, false); }
  }
  if (e.target.id === 'date') loadReport();
});

$('#insight-btn').onclick = async () => {
  $('#insights').textContent = 'Thinking…';
  try { const r = await api('/ai/insights?date=' + $('#date').value); $('#insights').textContent = r.text + `\n\n— ${r.source}`; }
  catch (e) { $('#insights').textContent = e.message; }
};

document.querySelectorAll('.qr').forEach((el) => {
  if (!window.qrcode) return;
  const qr = qrcode(0, 'M'); qr.addData(el.dataset.url); qr.make();
  el.innerHTML = qr.createSvgTag({ cellSize: 3, margin: 0 });
});
loadReport(); loadMenu(); setInterval(loadReport, 30000);
</script>
@endpush
