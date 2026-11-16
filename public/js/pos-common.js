// Shared helpers for all front ends (POS, kitchen, admin, QR ordering).
window.POS = (() => {
  const token = document.querySelector('meta[name="staff-token"]')?.content || '';

  async function api(path, { method = 'GET', body } = {}) {
    const res = await fetch('/api/v1' + path, {
      method,
      headers: {
        'Accept': 'application/json',
        'Content-Type': 'application/json',
        ...(token ? { 'X-Staff-Token': token } : {}),
      },
      body: body ? JSON.stringify(body) : undefined,
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
      const msg = data.message || (data.errors && Object.values(data.errors)[0][0]) || `Error ${res.status}`;
      throw new Error(msg);
    }
    return data;
  }

  const rm = (sen) => 'RM ' + ((sen || 0) / 100).toFixed(2);
  const esc = (s) => String(s ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  function toast(msg, ok = true) {
    const el = document.createElement('div');
    el.className = `fixed bottom-4 left-1/2 -translate-x-1/2 z-50 px-4 py-2 rounded-lg shadow-lg text-white text-sm ${ok ? 'bg-emerald-600' : 'bg-rose-600'}`;
    el.textContent = msg;
    document.body.appendChild(el);
    setTimeout(() => el.remove(), 2800);
  }

  /**
   * Cart model shared by POS and QR pages.
   * Line: {key, menu_item_id, name, base_sen, quantity, modifiers:[{id,name,price_delta_sen}], notes}
   */
  function createCart(onChange) {
    let lines = [];
    const emit = () => onChange([...lines]);
    return {
      add(item, modifiers = [], quantity = 1, notes = null) {
        const key = item.id + '|' + modifiers.map((m) => m.id).sort().join(',') + '|' + (notes || '');
        const found = lines.find((l) => l.key === key);
        if (found) found.quantity += quantity;
        else lines.push({ key, menu_item_id: item.id, name: item.name, base_sen: item.price_sen, quantity, modifiers, notes });
        emit();
      },
      inc(key, d) {
        const l = lines.find((x) => x.key === key);
        if (!l) return;
        l.quantity += d;
        if (l.quantity <= 0) lines = lines.filter((x) => x.key !== key);
        emit();
      },
      clear() { lines = []; emit(); },
      lines: () => lines,
      unitSen: (l) => l.base_sen + l.modifiers.reduce((s, m) => s + m.price_delta_sen, 0),
      payload: () => lines.map((l) => ({
        menu_item_id: l.menu_item_id, quantity: l.quantity,
        modifier_ids: l.modifiers.map((m) => m.id), notes: l.notes,
      })),
    };
  }

  /** Modal to pick modifiers. Resolves with selected modifiers or null if cancelled. */
  function pickModifiers(item) {
    if (!item.modifier_groups?.length) return Promise.resolve([]);
    return new Promise((resolve) => {
      const wrap = document.createElement('div');
      wrap.className = 'fixed inset-0 bg-black/40 z-40 flex items-end sm:items-center justify-center';
      wrap.innerHTML = `
        <div class="bg-white w-full sm:max-w-md rounded-t-2xl sm:rounded-2xl p-5 max-h-[85vh] overflow-y-auto">
          <div class="flex justify-between items-start mb-3">
            <h3 class="text-lg font-bold">${esc(item.name)}</h3><span class="font-semibold">${rm(item.price_sen)}</span>
          </div>
          ${item.modifier_groups.map((g) => `
            <fieldset class="mb-4">
              <legend class="text-sm font-semibold text-slate-600 mb-1">${esc(g.name)}
                <span class="font-normal text-xs text-slate-400">${g.min_select ? 'required' : 'optional'}${g.max_select > 1 ? ', up to ' + g.max_select : ''}</span></legend>
              ${g.modifiers.map((m) => `
                <label class="flex items-center justify-between py-1.5 border-b border-slate-100">
                  <span><input type="${g.max_select > 1 ? 'checkbox' : 'radio'}" name="g${g.id}" value="${m.id}" class="mr-2" data-group="${g.id}">${esc(m.name)}</span>
                  <span class="text-sm text-slate-500">${m.price_delta_sen ? '+' + rm(m.price_delta_sen) : ''}</span>
                </label>`).join('')}
            </fieldset>`).join('')}
          <p class="text-rose-600 text-sm mb-2 hidden" data-err></p>
          <div class="flex gap-2">
            <button class="flex-1 py-3 rounded-xl border" data-cancel>Cancel</button>
            <button class="flex-1 py-3 rounded-xl bg-orange-500 text-white font-semibold" data-ok>Add</button>
          </div>
        </div>`;
      document.body.appendChild(wrap);
      const close = (v) => { wrap.remove(); resolve(v); };
      wrap.querySelector('[data-cancel]').onclick = () => close(null);
      wrap.querySelector('[data-ok]').onclick = () => {
        const picked = [];
        for (const g of item.modifier_groups) {
          const ids = [...wrap.querySelectorAll(`input[data-group="${g.id}"]:checked`)].map((i) => +i.value);
          if (ids.length < g.min_select || ids.length > g.max_select) {
            const e = wrap.querySelector('[data-err]');
            e.textContent = `Please choose ${g.name}.`; e.classList.remove('hidden');
            return;
          }
          picked.push(...g.modifiers.filter((m) => ids.includes(m.id)));
        }
        close(picked);
      };
    });
  }

  /** Browser speech-to-text (Chrome/Edge). Calls back with the transcript. */
  function listen(onText, lang = 'en-MY') {
    const SR = window.SpeechRecognition || window.webkitSpeechRecognition;
    if (!SR) { toast('Voice input not supported in this browser', false); return; }
    const r = new SR();
    r.lang = lang; r.interimResults = false;
    r.onresult = (e) => onText(e.results[0][0].transcript);
    r.onerror = () => toast('Could not hear that, try again', false);
    r.start();
    toast('Listening…');
  }

  return { api, rm, esc, toast, createCart, pickModifiers, listen };
})();
