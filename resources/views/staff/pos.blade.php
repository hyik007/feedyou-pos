@extends('layouts.app')
@section('title', 'POS')
@section('nav', true)
@section('content')
<div class="flex h-[calc(100vh-3rem)]">
    {{-- Menu --}}
    <section class="flex-1 flex flex-col min-w-0">
        <div class="p-3 bg-white border-b flex gap-2">
            <input id="ai-text" class="flex-1 border rounded-xl px-3 py-2" placeholder='AI order: "2 kopi o kurang manis, satu nasi lemak tambah telur"'>
            <button id="ai-mic" class="px-3 rounded-xl border" title="Speak">🎤</button>
            <button id="ai-go" class="px-4 rounded-xl bg-slate-900 text-white">✨ Add</button>
        </div>
        <div id="cats" class="flex gap-2 p-3 overflow-x-auto"></div>
        <div id="items" class="flex-1 overflow-y-auto p-3 pt-0 grid grid-cols-2 md:grid-cols-3 xl:grid-cols-4 gap-3 content-start"></div>
    </section>

    {{-- Cart --}}
    <aside class="w-96 bg-white border-l flex flex-col">
        <div class="p-3 border-b flex gap-2">
            <select id="table" class="flex-1 border rounded-lg p-2"><option value="">Takeaway</option></select>
            <button id="open-btn" class="px-3 rounded-lg border text-sm">Open bills <span id="open-count" class="ml-1 bg-orange-500 text-white rounded-full px-2 text-xs">0</span></button>
        </div>
        <ul id="cart" class="flex-1 overflow-y-auto divide-y"></ul>
        <div class="p-3 border-t text-sm space-y-1" id="totals"></div>
        <div class="p-3 pt-0 flex gap-2">
            <button id="clear" class="px-4 py-3 rounded-xl border">Clear</button>
            <button id="place" class="flex-1 py-3 rounded-xl bg-orange-500 text-white font-semibold disabled:opacity-40" disabled>Send to kitchen</button>
        </div>
    </aside>
</div>

{{-- Open bills drawer --}}
<div id="drawer" class="hidden fixed inset-0 bg-black/40 z-30" data-close>
    <div class="absolute right-0 top-0 h-full w-full max-w-md bg-white p-4 overflow-y-auto">
        <div class="flex justify-between mb-3"><h2 class="font-bold text-lg">Open bills</h2><button data-close class="text-2xl leading-none">×</button></div>
        <div id="open-list" class="space-y-3"></div>
    </div>
</div>
@endsection

@push('scripts')
<script>
const { api, rm, esc, toast } = POS;
let menu = { categories: [] }, activeCat = null, quoteTimer;
const $ = (s) => document.querySelector(s);
const cart = POS.createCart(renderCart);

async function boot() {
  menu = await api('/menu');
  activeCat = menu.categories[0]?.id;
  renderMenu();
  const tables = await api('/tables');
  $('#table').innerHTML += tables.map((t) => `<option value="${esc(t.code)}">${esc(t.code)}${t.occupied ? ' • ' + rm(t.open_total_sen) : ''}</option>`).join('');
  refreshOpen();
  setInterval(refreshOpen, 10000);
}

function renderMenu() {
  $('#cats').innerHTML = menu.categories.map((c) =>
    `<button data-cat="${c.id}" class="px-4 py-2 rounded-full whitespace-nowrap ${c.id === activeCat ? 'bg-slate-900 text-white' : 'bg-white'}">${esc(c.name)}</button>`).join('');
  const cat = menu.categories.find((c) => c.id === activeCat) || { items: [] };
  $('#items').innerHTML = cat.items.map((i) => `
    <button data-item="${i.id}" ${i.is_available ? '' : 'disabled'} class="bg-white rounded-xl p-3 text-left shadow-sm hover:ring-2 ring-orange-400 disabled:opacity-40 h-24 flex flex-col justify-between">
      <span class="font-semibold leading-tight">${esc(i.name)}</span>
      <span class="text-sm text-slate-500">${i.is_available ? rm(i.price_sen) : 'Sold out'}</span>
    </button>`).join('');
}

const findItem = (id) => menu.categories.flatMap((c) => c.items).find((i) => i.id === id);

function renderCart(lines) {
  $('#cart').innerHTML = lines.length ? lines.map((l) => `
    <li class="p-3 flex gap-2">
      <div class="flex-1 min-w-0">
        <div class="font-medium">${esc(l.name)}</div>
        ${l.modifiers.length ? `<div class="text-xs text-slate-500">${l.modifiers.map((m) => esc(m.name)).join(', ')}</div>` : ''}
        ${l.notes ? `<div class="text-xs text-orange-600">“${esc(l.notes)}”</div>` : ''}
      </div>
      <div class="flex items-center gap-2">
        <button data-dec="${esc(l.key)}" class="w-7 h-7 rounded-full border">−</button><span class="w-5 text-center">${l.quantity}</span>
        <button data-inc="${esc(l.key)}" class="w-7 h-7 rounded-full border">+</button>
      </div>
      <div class="w-20 text-right">${rm(cart.unitSen(l) * l.quantity)}</div>
    </li>`).join('') : '<li class="p-6 text-center text-slate-400">Cart is empty</li>';
  $('#place').disabled = !lines.length;
  clearTimeout(quoteTimer);
  quoteTimer = setTimeout(updateQuote, 250);
}

async function updateQuote() {
  if (!cart.lines().length) { $('#totals').innerHTML = ''; return; }
  try {
    const q = await api('/orders/quote', { method: 'POST', body: { type: orderType(), items: cart.payload() } });
    const row = (k, v, b = '') => `<div class="flex justify-between ${b}"><span>${k}</span><span>${v}</span></div>`;
    $('#totals').innerHTML = row('Subtotal', rm(q.subtotal_sen))
      + (q.service_charge_sen ? row('Service charge', rm(q.service_charge_sen)) : '')
      + row('SST', rm(q.tax_sen)) + (q.rounding_sen ? row('Rounding', rm(q.rounding_sen)) : '')
      + row('Total', rm(q.total_sen), 'font-bold text-lg pt-1');
  } catch (e) { $('#totals').innerHTML = `<div class="text-rose-600">${esc(e.message)}</div>`; }
}

const orderType = () => ($('#table').value ? 'dine_in' : 'takeaway');

async function aiAdd(text) {
  if (!text.trim()) return;
  $('#ai-go').disabled = true;
  try {
    const r = await api('/ai/parse-order', { method: 'POST', body: { text } });
    r.items.forEach((p) => { const item = findItem(p.menu_item_id); if (item) cart.add(item, [], p.quantity, p.notes); });
    toast(`Added ${r.items.length} item(s) via ${r.source}` + (r.unmatched.length ? ` · didn't get: ${r.unmatched.join(', ')}` : ''), !r.unmatched.length);
    $('#ai-text').value = '';
  } catch (e) { toast(e.message, false); }
  $('#ai-go').disabled = false;
}

async function placeOrder() {
  $('#place').disabled = true;
  try {
    const o = await api('/orders', { method: 'POST', body: {
      channel: 'pos', type: orderType(), table_code: $('#table').value || null, items: cart.payload(),
    } });
    toast(`Order ${o.data.order_no} sent · ${rm(o.data.total_sen)}`);
    cart.clear();
    refreshOpen();
    if (o.data.type === 'takeaway') payDialog(o.data);
  } catch (e) { toast(e.message, false); $('#place').disabled = false; }
}

async function refreshOpen() {
  const { data } = await api('/orders?scope=open');
  $('#open-count').textContent = data.length;
  $('#open-list').innerHTML = data.map((o) => `
    <div class="border rounded-xl p-3">
      <div class="flex justify-between font-semibold"><span>#${esc(o.order_no.slice(-4))} · ${esc(o.table || 'Takeaway')}</span><span>${rm(o.total_sen)}</span></div>
      <div class="text-xs text-slate-500 mb-2">${esc(o.status)} · ${esc(o.payment_status)} · ${o.channel.toUpperCase()}</div>
      <div class="text-sm mb-2">${o.items.map((i) => `${i.quantity}× ${esc(i.name)}`).join(', ')}</div>
      <div class="flex gap-2 flex-wrap">
        ${o.balance_sen ? `<button data-pay="${o.id}" class="px-3 py-1.5 rounded-lg bg-emerald-600 text-white text-sm">Pay ${rm(o.balance_sen)}</button>` : ''}
        ${o.next_statuses.filter((s) => s !== 'completed' || !o.balance_sen).map((s) => `<button data-status="${o.id}:${s}" class="px-3 py-1.5 rounded-lg border text-sm capitalize">${s}</button>`).join('')}
      </div>
    </div>`).join('') || '<p class="text-slate-400">No open bills.</p>';
  window._open = data;
}

function payDialog(order) {
  const wrap = document.createElement('div');
  wrap.className = 'fixed inset-0 bg-black/40 z-50 flex items-center justify-center';
  wrap.innerHTML = `
    <div class="bg-white rounded-2xl p-5 w-full max-w-sm">
      <h3 class="font-bold text-lg mb-1">Payment · #${esc(order.order_no.slice(-4))}</h3>
      <p class="text-3xl font-bold mb-4">${rm(order.balance_sen)}</p>
      <div class="grid grid-cols-4 gap-2 mb-3">${['cash', 'card', 'duitnow', 'ewallet'].map((m, i) =>
        `<label class="border rounded-lg p-2 text-center text-sm cursor-pointer has-[:checked]:bg-slate-900 has-[:checked]:text-white"><input type="radio" name="m" value="${m}" class="hidden" ${i ? '' : 'checked'}>${m === 'duitnow' ? 'DuitNow' : m === 'ewallet' ? 'e-Wallet' : m[0].toUpperCase() + m.slice(1)}</label>`).join('')}</div>
      <input id="tendered" type="number" step="0.05" placeholder="Cash received (RM) — blank = exact" class="w-full border rounded-lg p-2 mb-3">
      <div class="flex gap-2"><button data-x class="flex-1 py-3 border rounded-xl">Later</button><button data-ok class="flex-1 py-3 bg-emerald-600 text-white rounded-xl font-semibold">Confirm</button></div>
    </div>`;
  document.body.appendChild(wrap);
  wrap.querySelector('[data-x]').onclick = () => wrap.remove();
  wrap.querySelector('[data-ok]').onclick = async () => {
    const method = wrap.querySelector('input[name=m]:checked').value;
    const t = parseFloat(wrap.querySelector('#tendered').value);
    try {
      const r = await api(`/orders/${order.id}/pay`, { method: 'POST', body: { method, tendered_sen: t ? Math.round(t * 100) : null } });
      toast(r.change_sen ? `Paid · change ${rm(r.change_sen)}` : 'Paid');
      wrap.remove(); refreshOpen();
    } catch (e) { toast(e.message, false); }
  };
}

document.addEventListener('click', async (e) => {
  const t = e.target.closest('button,[data-close]');
  if (!t) return;
  if (t.dataset.cat) { activeCat = +t.dataset.cat; renderMenu(); }
  if (t.dataset.item) { const item = findItem(+t.dataset.item); const mods = await POS.pickModifiers(item); if (mods) cart.add(item, mods); }
  if (t.dataset.inc) cart.inc(t.dataset.inc, 1);
  if (t.dataset.dec) cart.inc(t.dataset.dec, -1);
  if (t.dataset.pay) payDialog(window._open.find((o) => o.id === +t.dataset.pay));
  if (t.dataset.status) {
    const [id, s] = t.dataset.status.split(':');
    try { await api(`/orders/${id}/status`, { method: 'POST', body: { status: s } }); refreshOpen(); } catch (err) { toast(err.message, false); }
  }
  if (t.id === 'open-btn') $('#drawer').classList.remove('hidden');
  if (t.dataset.close !== undefined && (e.target === t)) $('#drawer').classList.add('hidden');
});
$('#clear').onclick = () => cart.clear();
$('#place').onclick = placeOrder;
$('#table').onchange = () => updateQuote();
$('#ai-go').onclick = () => aiAdd($('#ai-text').value);
$('#ai-text').onkeydown = (e) => { if (e.key === 'Enter') aiAdd(e.target.value); };
$('#ai-mic').onclick = () => POS.listen((txt) => { $('#ai-text').value = txt; aiAdd(txt); });
boot().catch((e) => toast(e.message, false));
</script>
@endpush
