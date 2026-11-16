@extends('layouts.app')
@section('title', 'Kitchen')
@section('nav', true)
@section('body-class', 'bg-slate-800')
@section('content')
<div class="grid grid-cols-4 gap-3 p-3 h-[calc(100vh-3rem)]">
    @foreach (['pending' => ['New (QR)', 'bg-amber-400'], 'accepted' => ['To cook', 'bg-sky-400'], 'preparing' => ['Cooking', 'bg-orange-400'], 'ready' => ['Ready', 'bg-emerald-400']] as $s => [$label, $color])
        <section class="bg-slate-900/60 rounded-xl flex flex-col min-h-0">
            <h2 class="px-3 py-2 font-bold text-white flex items-center gap-2"><span class="w-2.5 h-2.5 rounded-full {{ $color }}"></span>{{ $label }} <span class="ml-auto text-slate-400" data-count="{{ $s }}">0</span></h2>
            <div class="flex-1 overflow-y-auto p-2 space-y-2" data-col="{{ $s }}"></div>
        </section>
    @endforeach
</div>
@endsection

@push('scripts')
<script>
const { api, esc, toast } = POS;
const ACTION = { pending: ['accepted', 'Accept'], accepted: ['preparing', 'Start'], preparing: ['ready', 'Ready'], ready: ['served', 'Served'] };
let known = new Set();

async function load() {
  const { data } = await api('/orders?scope=kitchen');
  const fresh = data.filter((o) => !known.has(o.id));
  if (known.size && fresh.length) beep();
  known = new Set(data.map((o) => o.id));

  for (const s of Object.keys(ACTION)) {
    const list = data.filter((o) => o.status === s);
    document.querySelector(`[data-count="${s}"]`).textContent = list.length;
    document.querySelector(`[data-col="${s}"]`).innerHTML = list.map(card).join('');
  }
}

function card(o) {
  const late = o.age_minutes >= 15 ? 'ring-2 ring-rose-500' : o.age_minutes >= 8 ? 'ring-2 ring-amber-400' : '';
  const [next, label] = ACTION[o.status];
  return `
    <article class="bg-white rounded-lg p-3 ${late}">
      <header class="flex justify-between text-sm font-bold mb-1">
        <span>${esc(o.table || 'Takeaway')} · #${esc(o.order_no.slice(-4))}</span>
        <span class="${o.age_minutes >= 15 ? 'text-rose-600' : 'text-slate-500'}">${o.age_minutes}m</span>
      </header>
      <ul class="text-sm mb-2">${o.items.map((i) => `
        <li><b>${i.quantity}×</b> ${esc(i.name)}
          ${i.modifiers.length ? `<div class="text-xs text-slate-500 ml-5">${i.modifiers.map(esc).join(', ')}</div>` : ''}
          ${i.notes ? `<div class="text-xs text-orange-600 ml-5">“${esc(i.notes)}”</div>` : ''}</li>`).join('')}
      </ul>
      ${o.customer_notes ? `<p class="text-xs bg-amber-50 p-1.5 rounded mb-2">📝 ${esc(o.customer_notes)}</p>` : ''}
      <div class="flex gap-2">
        <button data-go="${o.id}:${next}" class="flex-1 bg-slate-900 text-white rounded py-2 text-sm font-semibold">${label}</button>
        ${o.next_statuses.includes('cancelled') ? `<button data-go="${o.id}:cancelled" class="px-3 rounded border text-sm">✕</button>` : ''}
      </div>
      <div class="text-[10px] text-slate-400 mt-1 uppercase">${o.channel}</div>
    </article>`;
}

function beep() {
  try { const c = new AudioContext(), o = c.createOscillator(); o.connect(c.destination); o.frequency.value = 880; o.start(); setTimeout(() => o.stop(), 200); } catch {}
}

document.addEventListener('click', async (e) => {
  const b = e.target.closest('[data-go]');
  if (!b) return;
  const [id, status] = b.dataset.go.split(':');
  if (status === 'cancelled' && !confirm('Cancel this order?')) return;
  b.disabled = true;
  try { await api(`/orders/${id}/status`, { method: 'POST', body: { status } }); load(); } catch (err) { toast(err.message, false); b.disabled = false; }
});

load(); setInterval(load, 5000);   // polling; swap for Laravel Reverb broadcasting later
</script>
@endpush
