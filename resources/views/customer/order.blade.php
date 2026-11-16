@extends('layouts.app')
@section('title', 'Order · '.$table->code)
@section('content')
<div class="max-w-md mx-auto min-h-screen bg-white pb-28">
    <header class="sticky top-0 bg-white z-10 border-b">
        <div class="px-4 pt-4 pb-2 flex justify-between items-center">
            <div><h1 class="font-bold text-lg">{{ $name }}</h1><p class="text-xs text-slate-500">Table {{ $table->code }}</p></div>
            <span class="text-xs bg-orange-100 text-orange-700 px-2 py-1 rounded-full">Dine-in</span>
        </div>
        <div class="px-4 pb-2 flex gap-2">
            <input id="ai-text" class="flex-1 border rounded-full px-4 py-2 text-sm" placeholder="Tell us what you want… “2 teh tarik, 1 roti canai”">
            <button id="ai-mic" class="w-10 rounded-full border">🎤</button>
            <button id="ai-go" class="w-10 rounded-full bg-orange-500 text-white">✨</button>
        </div>
        <nav id="cats" class="flex gap-2 px-4 pb-2 overflow-x-auto text-sm"></nav>
    </header>

    <main id="menu" class="px-4"></main>

    <section id="status" class="hidden px-4 py-10 text-center">
        <div class="text-5xl mb-3" id="status-icon">⏳</div>
        <h2 class="text-xl font-bold" id="status-title">Order received</h2>
        <p class="text-slate-500" id="status-text"></p>
        <button id="again" class="mt-6 px-5 py-2 rounded-full border">Order more</button>
    </section>

    <div id="bar" class="hidden fixed bottom-0 inset-x-0 max-w-md mx-auto p-4 bg-white border-t">
        <details class="mb-3"><summary class="text-sm text-slate-600 cursor-pointer">View cart</summary><ul id="cart" class="mt-2 text-sm divide-y"></ul>
            <textarea id="notes" rows="2" class="w-full border rounded-lg p-2 text-sm mt-2" placeholder="Notes for the kitchen (optional)"></textarea></details>
        <button id="place" class="w-full bg-orange-500 text-white rounded-full py-3 font-semibold flex justify-between px-6">
            <span>Place order · <span id="count">0</span></span><span id="total"></span></button>
    </div>
</div>
@endsection

@push('scripts')
<script>
const { api, rm, esc, toast } = POS;
const TABLE_TOKEN = @json($table->qr_token);
const $ = (s) => document.querySelector(s);
let menu, quoteTimer, tracking;
const cart = POS.createCart(renderCart);

async function boot() {
  menu = await api('/menu');
  $('#cats').innerHTML = menu.categories.map((c) => `<a href="#c${c.id}" class="px-3 py-1.5 bg-slate-100 rounded-full whitespace-nowrap">${esc(c.name)}</a>`).join('');
  $('#menu').innerHTML = menu.categories.map((c) => `
    <h2 id="c${c.id}" class="font-bold mt-5 mb-2 scroll-mt-40">${esc(c.name)}</h2>
    ${c.items.map((i) => `
      <button data-item="${i.id}" ${i.is_available ? '' : 'disabled'} class="w-full flex justify-between items-center py-3 border-b text-left disabled:opacity-40">
        <span><span class="font-medium">${esc(i.name)}</span>${i.description ? `<span class="block text-xs text-slate-500">${esc(i.description)}</span>` : ''}</span>
        <span class="flex items-center gap-3"><span class="text-sm">${i.is_available ? rm(i.price_sen) : 'Sold out'}</span>
        <span class="w-7 h-7 rounded-full bg-orange-500 text-white grid place-items-center">+</span></span>
      </button>`).join('')}`).join('');
}

const findItem = (id) => menu.categories.flatMap((c) => c.items).find((i) => i.id === id);

function renderCart(lines) {
  $('#bar').classList.toggle('hidden', !lines.length);
  $('#count').textContent = lines.reduce((s, l) => s + l.quantity, 0);
  $('#cart').innerHTML = lines.map((l) => `
    <li class="py-2 flex items-center gap-2"><span class="flex-1">${esc(l.name)}${l.modifiers.length ? `<span class="block text-xs text-slate-500">${l.modifiers.map((m) => esc(m.name)).join(', ')}</span>` : ''}${l.notes ? `<span class="block text-xs text-orange-600">${esc(l.notes)}</span>` : ''}</span>
      <button data-dec="${esc(l.key)}" class="w-6 h-6 rounded-full border">−</button>${l.quantity}<button data-inc="${esc(l.key)}" class="w-6 h-6 rounded-full border">+</button></li>`).join('');
  clearTimeout(quoteTimer);
  quoteTimer = setTimeout(async () => {
    if (!cart.lines().length) return;
    try { const q = await api('/orders/quote', { method: 'POST', body: { type: 'dine_in', items: cart.payload() } }); $('#total').textContent = rm(q.total_sen); }
    catch (e) { toast(e.message, false); }
  }, 250);
}

async function aiAdd(text) {
  if (!text.trim()) return;
  try {
    const r = await api('/ai/parse-order', { method: 'POST', body: { text } });
    r.items.forEach((p) => { const i = findItem(p.menu_item_id); if (i) cart.add(i, [], p.quantity, p.notes); });
    toast(r.items.length ? `Added ${r.items.length} item(s) — check your cart` : "Sorry, couldn't find that on the menu", !!r.items.length);
    $('#ai-text').value = '';
  } catch (e) { toast(e.message, false); }
}

async function place() {
  $('#place').disabled = true;
  try {
    const { data } = await api('/orders', { method: 'POST', body: {
      table_token: TABLE_TOKEN, type: 'dine_in', items: cart.payload(), customer_notes: $('#notes').value || null,
    } });
    cart.clear(); $('#notes').value = '';
    showStatus(data.order_no);
  } catch (e) { toast(e.message, false); }
  $('#place').disabled = false;
}

const STATES = {
  pending: ['⏳', 'Order received', 'Waiting for the restaurant to confirm…'],
  accepted: ['👍', 'Confirmed', 'Your order is in the queue.'],
  preparing: ['🍳', 'Cooking', 'The kitchen is preparing your food.'],
  ready: ['🛎️', 'Ready!', 'Your order is on its way to your table.'],
  served: ['😋', 'Enjoy your meal', 'Pay at the counter when you are done.'],
  completed: ['✅', 'Paid — thank you!', 'Terima kasih, see you again.'],
  cancelled: ['❌', 'Cancelled', 'Please ask our staff for help.'],
};

function showStatus(orderNo) {
  $('#menu').classList.add('hidden'); $('#status').classList.remove('hidden');
  clearInterval(tracking);
  const poll = async () => {
    const s = await api(`/orders/${orderNo}/track?t=${encodeURIComponent(TABLE_TOKEN)}`);
    const [icon, title, text] = STATES[s.status];
    $('#status-icon').textContent = icon; $('#status-title').textContent = title;
    $('#status-text').textContent = `#${orderNo.slice(-4)} · ${s.total} · ${text}`;
  };
  poll(); tracking = setInterval(poll, 5000);
}

document.addEventListener('click', async (e) => {
  const b = e.target.closest('button');
  if (!b) return;
  if (b.dataset.item) { const i = findItem(+b.dataset.item); const mods = await POS.pickModifiers(i); if (mods) { cart.add(i, mods); toast(`${i.name} added`); } }
  if (b.dataset.inc) cart.inc(b.dataset.inc, 1);
  if (b.dataset.dec) cart.inc(b.dataset.dec, -1);
});
$('#place').onclick = place;
$('#again').onclick = () => { clearInterval(tracking); $('#status').classList.add('hidden'); $('#menu').classList.remove('hidden'); };
$('#ai-go').onclick = () => aiAdd($('#ai-text').value);
$('#ai-text').onkeydown = (e) => { if (e.key === 'Enter') aiAdd(e.target.value); };
$('#ai-mic').onclick = () => POS.listen((t) => { $('#ai-text').value = t; aiAdd(t); });
boot().catch((e) => toast(e.message, false));
</script>
@endpush
