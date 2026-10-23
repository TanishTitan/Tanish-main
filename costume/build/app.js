(() => {
  const $ = (s, r = document) => r.querySelector(s);
  const $$ = (s, r = document) => [...r.querySelectorAll(s)];
  const fa = n => Number(n).toLocaleString('fa-IR');
  const money = k => k >= 1000 ? fa(Math.round(k / 100) / 10) + ' میلیون' : fa(Math.round(k)) + ' هزار';
  const esc = s => String(s).replace(/[&<>"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));

  // ---------- state: db (synced across devices) with a local fallback ----------
  const LKEY = 'tanish-progress-v1';
  let S = { c: {}, p: {}, n: '' };
  try { const raw = localStorage.getItem(LKEY); if (raw) S = Object.assign(S, JSON.parse(raw)); } catch (e) {}
  let docRef = null, saveTimer = null, writing = false, again = false, readOnly = false;
  const syncEl = $('#sync');
  const setSync = (txt, ok) => { syncEl.innerHTML = 'ذخیره: <b style="color:' + (ok ? 'var(--ok)' : 'var(--muted)') + '">' + txt + '</b>'; };

  function saveLocal() { try { localStorage.setItem(LKEY, JSON.stringify(S)); } catch (e) {} }
  function schedule() {
    saveLocal();
    if (!docRef || readOnly) return;
    clearTimeout(saveTimer);
    setSync('در حال ذخیره…', false);
    saveTimer = setTimeout(push, 700);
  }
  async function push() {
    saveTimer = null;
    if (writing) { again = true; return; }
    writing = true;
    try { await docRef.set(JSON.parse(JSON.stringify(S))); setSync('همگام روی همه‌ی دستگاه‌ها', true); }
    catch (e) {
      if (e && e.code === 'invalid_argument') { readOnly = true; setSync('فقط این دستگاه', false); }
      else setSync('فقط این دستگاه (بعداً دوباره)', false);
    }
    writing = false;
    if (again) { again = false; push(); }
  }
  (async () => {
    try {
      const db = window.claude && await window.claude.use('db');
      if (!db) return;
      docRef = db.doc('progress/main');
      setSync('در حال اتصال…', false);
      docRef.onSnapshot(snap => {
        if (saveTimer || writing) return;                 // local edits in flight win
        if (snap.exists) {
          if (snap.metadata && snap.metadata.hasPendingWrites) return;
          const d = snap.data() || {};
          S = { c: Object.assign({}, d.c), p: Object.assign({}, d.p), n: d.n || '' };
          saveLocal(); applyState();
          setSync('همگام روی همه‌ی دستگاه‌ها', true);
        } else if (Object.keys(S.c).length || Object.keys(S.p).length || S.n) {
          push();                                          // first run: upload what this device has
        } else setSync('همگام روی همه‌ی دستگاه‌ها', true);
      }, () => setSync('فقط این دستگاه', false));
    } catch (e) { /* stays local */ }
  })();

  // ---------- inventory ----------
  $('#inv').innerHTML = HAVE.map(h => `<div class="card"><img src="${h.img}" alt="" loading="lazy"><div><div style="font-weight:800">${esc(h.name)}</div><div class="muted" style="font-size:.88rem">${esc(h.use)}</div></div></div>`).join('');

  // ---------- shopping ----------
  const PRI = { must: ['ضروری', 'must'], should: ['بهتره', ''], could: ['اختیاری', 'could'] };
  let filter = 'all';
  function renderShop() {
    const byStore = {};
    SHOP.forEach(it => (byStore[it.s] = byStore[it.s] || []).push(it));
    $('#shop').innerHTML = Object.keys(STORES).filter(k => byStore[k]).map((k, i) => {
      const items = byStore[k].filter(it => filter === 'all' || (filter === 'left' && !S.c['buy-' + it.id]) || (filter === 'must' && it.pri === 'must'));
      if (!items.length) return '';
      return `<div class="store"><h3><span class="mono" style="color:var(--accent)">${fa(i + 1)}</span>${esc(STORES[k].name)}</h3><p class="how">${esc(STORES[k].how)}</p>` +
        items.map(it => {
          const done = !!S.c['buy-' + it.id];
          const pr = PRI[it.pri];
          return `<div class="item${done ? ' done' : ''}" data-id="${it.id}">
            <input type="checkbox" id="buy-${it.id}" ${done ? 'checked' : ''} aria-label="خریدم: ${esc(it.name)}">
            <label for="buy-${it.id}" style="cursor:pointer"><span class="iname">${esc(it.name)}</span> <span class="en">${esc(it.en)}</span>
              <div class="meta"><span class="tag ${pr[1] === 'must' ? 'must' : ''}" ${pr[1] === 'could' ? 'style="border-style:dashed"' : ''}>${pr[0]}</span> <span class="mono">${it.id}</span> · ${esc(it.qty)}${it.note ? ' · ' + esc(it.note) : ''}</div></label>
            <div class="price">${fa(it.min)}–${fa(it.max)}<br><input inputmode="numeric" id="price-${it.id}" placeholder="واقعی" value="${S.p[it.id] != null ? esc(S.p[it.id]) : ''}" aria-label="قیمت واقعی ${esc(it.name)} به هزار تومان"></div>
          </div>`;
        }).join('') + '</div>';
    }).join('');
    totals();
  }
  function totals() {
    let mn = 0, mx = 0, spent = 0, bought = 0;
    SHOP.forEach(it => {
      mn += it.min; mx += it.max;
      const v = parseFloat(String(S.p[it.id] ?? '').replace(/[^\d.]/g, ''));
      if (!isNaN(v)) spent += v;
      if (S.c['buy-' + it.id]) bought++;
    });
    $('#estT').textContent = money(mn) + ' تا ' + money(mx);
    $('#budget').textContent = money((mn + mx) / 2);
    $('#spentT').textContent = spent ? money(spent) : '—';
    $('#boughtT').textContent = fa(bought) + ' از ' + fa(SHOP.length);
  }
  const toEn = s => s.replace(/[۰-۹]/g, d => '۰۱۲۳۴۵۶۷۸۹'.indexOf(d)).replace(/[٠-٩]/g, d => '٠١٢٣٤٥٦٧٨٩'.indexOf(d));
  $('#shop').addEventListener('change', e => {
    const t = e.target;
    if (t.type === 'checkbox') { S.c[t.id] = t.checked; t.closest('.item').classList.toggle('done', t.checked); totals(); schedule(); if (filter === 'left') renderShop(); }
  });
  $('#shop').addEventListener('input', e => {
    const t = e.target;
    if (t.id && t.id.startsWith('price-')) { const v = toEn(t.value).replace(/[^\d.]/g, ''); if (v) S.p[t.id.slice(6)] = v; else delete S.p[t.id.slice(6)]; totals(); schedule(); }
  });
  [['fAll', 'all'], ['fLeft', 'left'], ['fMust', 'must']].forEach(([id, f]) => $('#' + id).addEventListener('click', () => {
    filter = f; $$('.seg button').forEach(b => b.setAttribute('aria-pressed', String(b.id === id))); renderShop();
  }));

  // ---------- plan ----------
  // Tehran is UTC+3:30 (no DST). Day 1 = 2026-10-23 (1 Aban 1405).
  const tehranNow = () => new Date(Date.now() + 3.5 * 3600e3);
  const dayIndex = () => { const t = tehranNow(); const d0 = Date.UTC(2026, 9, 23); return Math.floor((Date.UTC(t.getUTCFullYear(), t.getUTCMonth(), t.getUTCDate()) - d0) / 864e5) + 1; };
  function renderPlan() {
    const di = dayIndex();
    $('#plan').innerHTML = PLAN.map(p => {
      const today = p.d === di;
      return `<article class="day${today ? ' today' : ''}${p.d === 14 ? ' party' : ''}" id="day${p.d}">${today ? '<span class="todaybadge">امروز</span>' : ''}
        <header><span class="dn">${fa(p.d)}</span><span class="dd">${esc(p.date)}</span><span class="dh">${esc(p.h || '')}</span></header>
        <div class="tt">${esc(p.title)}</div>${p.note ? `<div class="dnote">${esc(p.note)}</div>` : ''}
        <div style="margin-top:6px">${p.t.map((t, i) => {
          const id = `plan-${p.d}-${i}`; const done = !!S.c[id];
          return `<label class="task${done ? ' done' : ''}"><input type="checkbox" id="${id}" ${done ? 'checked' : ''}><span class="who${t[0] === 'مامان' ? ' mom' : ''}">${esc(t[0])}</span><span>${esc(t[1])}</span></label>`;
        }).join('')}</div></article>`;
    }).join('');
    planProgress();
  }
  function planProgress() {
    let n = 0, d = 0;
    PLAN.forEach(p => p.t.forEach((t, i) => { n++; if (S.c[`plan-${p.d}-${i}`]) d++; }));
    const pc = Math.round(d / n * 100);
    $('#planp').textContent = fa(pc) + '٪';
    $('#planbar').style.width = pc + '%';
  }
  $('#plan').addEventListener('change', e => {
    const t = e.target; if (t.type !== 'checkbox') return;
    S.c[t.id] = t.checked; t.closest('.task').classList.toggle('done', t.checked); planProgress(); schedule();
  });

  // ---------- dressing + kit ----------
  function renderChecklist(el, list, prefix, withWhere) {
    el.innerHTML = list.map((x, i) => {
      const id = `${prefix}-${i}`; const done = !!S.c[id];
      const txt = withWhere ? x[1] : x;
      return `<label class="${done ? 'done' : ''}"><input type="checkbox" id="${id}" ${done ? 'checked' : ''}><span class="i">${fa(i + 1)}</span><span>${withWhere ? `<span class="where">${esc(x[0])}</span> ` : ''}${esc(txt)}</span></label>`;
    }).join('');
  }
  function renderLists() { renderChecklist($('#dress'), DRESS, 'dress', true); renderChecklist($('#kit'), KIT, 'kit', false); }
  ['#dress', '#kit'].forEach(sel => $(sel).addEventListener('change', e => {
    const t = e.target; if (t.type !== 'checkbox') return;
    S.c[t.id] = t.checked; t.closest('label').classList.toggle('done', t.checked); schedule();
  }));
  $('#resetDress').addEventListener('click', () => { DRESS.forEach((x, i) => delete S.c['dress-' + i]); renderLists(); schedule(); });

  // ---------- notes ----------
  const notes = $('#notesTxt');
  notes.value = S.n || '';
  notes.addEventListener('input', () => { S.n = notes.value; schedule(); });

  function applyState() { renderShop(); renderPlan(); renderLists(); if (document.activeElement !== notes) notes.value = S.n || ''; }

  // ---------- countdown / today ----------
  const PARTY = Date.UTC(2026, 10, 5, 14, 30); // 14 Aban 18:00 Tehran
  const START = Date.UTC(2026, 9, 22, 20, 30); // 1 Aban 00:00 Tehran
  function tick() {
    const ms = PARTY - Date.now();
    if (ms <= 0) { $('#cd').textContent = 'شروع شده!'; $('#cdbar').style.width = '100%'; }
    else {
      const d = Math.floor(ms / 864e5), h = Math.floor(ms % 864e5 / 36e5);
      $('#cd').innerHTML = `${fa(d)}<small> روز </small>${fa(h)}<small> ساعت</small>`;
      $('#cdbar').style.width = Math.min(100, Math.max(0, (Date.now() - START) / (PARTY - START) * 100)) + '%';
    }
    const di = dayIndex();
    const p = PLAN.find(x => x.d === di);
    if (p) { $('#today').innerHTML = `روز ${fa(di)}<small> از ۱۴</small>`; $('#todayt').innerHTML = `<a href="#day${di}">${esc(p.title)}</a>`; }
    else if (di < 1) { $('#today').textContent = 'هنوز شروع نشده'; }
    else { $('#today').textContent = 'تموم شد'; $('#todayt').textContent = 'امیدوارم شب خوبی بوده باشه'; }
  }
  tick(); setInterval(tick, 60e3);

  // ---------- copy ----------
  async function copyText(text, btn, srcEl) {
    try { await navigator.clipboard.writeText(text); flash(btn, 'کپی شد'); }
    catch (e) {
      if (srcEl) { const r = document.createRange(); r.selectNodeContents(srcEl); const s = getSelection(); s.removeAllRanges(); s.addRange(r); }
      flash(btn, 'انتخاب شد؛ کپی کن');
    }
  }
  function flash(btn, txt) { const o = btn.dataset.label || btn.textContent; btn.dataset.label = o; btn.textContent = txt; setTimeout(() => btn.textContent = o, 1600); }
  document.addEventListener('click', e => {
    const b = e.target.closest('[data-copy]'); if (!b) return;
    const el = document.getElementById(b.dataset.copy); copyText(el.textContent, b, el);
  });
  $('#copyShop').addEventListener('click', e => {
    const lines = []; let cur = '';
    SHOP.filter(it => !S.c['buy-' + it.id]).forEach(it => {
      if (it.s !== cur) { cur = it.s; lines.push('\n■ ' + STORES[it.s].name); }
      lines.push(`☐ ${it.name} — ${it.qty} (${fa(it.min)}–${fa(it.max)} هزار)`);
    });
    copyText('لیست خرید کاستوم تانیش' + lines.join('\n'), e.currentTarget, null);
  });

  // ---------- downloads ----------
  let dlNs;
  const dlReady = (async () => { try { dlNs = window.claude && await window.claude.use('downloads'); } catch (e) { dlNs = null; } })();
  document.addEventListener('click', async e => {
    const b = e.target.closest('[data-dl]'); if (!b) return;
    const path = b.dataset.dl;
    await dlReady;
    if (!dlNs) { window.open(path, '_blank', 'noopener'); return; }
    try {
      flash(b, '…');
      const blob = await (await fetch(path)).blob();
      await dlNs.save({ filename: path.split('/').pop(), data: blob });
      flash(b, 'ذخیره شد');
    } catch (err) {
      if (err && err.code === 'declined') flash(b, 'لغو شد');
      else { flash(b, 'باز کردن…'); window.open(path, '_blank', 'noopener'); }
    }
  });

  applyState();
})();
