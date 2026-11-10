(() => {
  const root = document.getElementById('root'), stage = document.getElementById('stage'), conf = document.getElementById('confetti');
  const logos = document.getElementById('logos'), trans = document.getElementById('trans');
  let T = {}; // textes : voir app/texts.php
  const tx = (k, v = {}) => esc(String(T[k] ?? k).replace(/\{(\w+)\}/g, (_, n) => v[n] ?? ''));
  const qs = location.search;
  const PREVIEW = /[?&]preview=/.test(qs); // aperçu dans la régie : on y montre les emplacements de logos vides
  const GAP = 10;
  const sleep = ms => new Promise(r => setTimeout(r, ms));
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const fmt = n => Number(n).toLocaleString('fr-FR');
  const pct = n => Number(n).toLocaleString('fr-FR', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
  const f2 = n => Number(n).toLocaleString('fr-FR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  const wl = w => Math.round(w * 1000) / 10; // 0.6 -> 60
  const dec = n => (Number.isInteger(n) ? 0 : 2);
  const nth = n => n + (n === 1 ? 'er' : 'e');

  function fit() {
    const s = Math.min(innerWidth / 1920, innerHeight / 1080);
    root.style.transform = `scale(${s})`;
    root.style.left = (innerWidth - 1920 * s) / 2 + 'px';
    root.style.top = (innerHeight - 1080 * s) / 2 + 'px';
  }
  addEventListener('resize', fit); fit();
  stage.addEventListener('click', e => {
    const b = e.target.closest('.cl-pg');
    if (!b || !currentS || currentS.scene !== 'classement') return;
    clPage = +b.dataset.p || 0;
    clearTimeout(clPageTimer);
    if (patchers.classement) patchers.classement(currentS, clPage);
  });

  const photo = (c, cls = 'ph') => c.photo_url ? `<img class="${cls}" src="${esc(c.photo_url)}" alt="">` : `<div class="${cls} no">${esc(c.num)}</div>`;
  const nameOf = c => esc(c.stage || c.name);
  // Logo : image si renseigné ; sinon un emplacement pointillé, visible uniquement dans l'aperçu de la régie
  const sparks = () => Array.from({ length: 16 }, (_, i) => `<i class="sp" style="left:${8 + Math.random() * 84}%;animation-delay:${(Math.random() * 4).toFixed(2)}s;animation-duration:${3 + Math.random() * 3}s;--s:${(.5 + Math.random()).toFixed(2)}"></i>`).join('');
  const logoImg = (src, cls, label) => src ? `<img class="${cls}" src="${esc(src)}" alt="" decoding="async">` : (PREVIEW ? `<div class="${cls} ph-logo">${label}</div>` : '');
  const title = d => tx('classement_p' + d.phase);
  // Attend que les images soient chargées ET décodées (max 3 s) avant d'afficher : plus de logo qui « apparaît en retard »
  const imgsReady = (el, ms = 3000) => Promise.race([
    Promise.all([...el.querySelectorAll('img')].map(i => (i.decode ? i.decode().catch(() => {}) : Promise.resolve()))), sleep(ms)]);
  const preload = src => src ? new Promise(res => { const i = new Image(); i.onload = i.onerror = res; i.src = src; if (i.decode) i.decode().then(res, res); }) : Promise.resolve();

  const scenes = {
    accueil: (s) => `<div class="lw">${s.event.logo ? '<div class="halo"></div>' : ''}${logoImg(s.event.logo, 'logo fx', 'LOGO DU MILIEU')}${sparks()}</div>
      <h1 class="t serif fancy">${esc(s.event.name)}</h1><div class="line"></div>
      ${s.data && s.data.phase_label ? `<div class="phase-tag">${esc(s.data.phase_label)}</div>` : ''}
      <div class="sub" style="margin-top:14px">${s.event.date ? esc(new Date(s.event.date + 'T12:00').toLocaleDateString('fr-FR', { dateStyle: 'long' })) : ''}</div>
      <div class="sub" style="margin-top:10px">${tx('accueil_org', { org: s.event.org })}</div>`,

    depouillement: () => `<h1 class="t serif">${tx('depouillement_title')}</h1><div class="line"></div>
      <div class="dots"><span></span><span></span><span></span></div>
      <div class="sub">${tx('depouillement_sub')}</div>`,

    individuel: (s) => {
      const d = s.data, c = d.competitor, two = d.phase >= 2;
      const box = (cls, label) => `<div class="tb ${cls}"><label>${label}</label><div class="vv"></div><div class="ss"></div></div>`;
      // Phase 1 : la note de chaque juré, puis la moyenne et le rang (pas de photo)
      const tiles = (c.notes || []).map((n, i) => `<div class="jt" style="--i:${i}"><label>${tx('juror_label', { n: i + 1 })}</label>
        <b>${n == null ? '—' : `<span data-count="${n}" data-dec="${dec(n)}">0</span>`}</b></div>`).join('');
      const churchMeta = (c.church || c.church_org) ? `<div class="c-meta">${c.church ? `<span class="cm-tag eglise">${esc(c.church)}</span>` : ''}${c.church_org ? `<span class="cm-tag org">${esc(c.church_org)}</span>` : ''}</div>` : '';
      return `<div class="ind2 ${two ? '' : 'nophoto'}">${two ? photo(c) : ''}<div class="box">
        <div class="n">${tx('candidate')} ${esc(c.num)} · ${esc(d.phase_label)}</div><div class="nm serif">${nameOf(c)}</div>
        ${churchMeta}
        ${two ? `<div class="tri three">${box('pub', tx('col_public'))}${box('jur', tx('col_jury'))}${box('tot', tx('col_total'))}</div>`
              : `<div class="jn">${tiles}</div><div class="tri one">${box('jur', tx('col_average'))}${box('rk', tx('col_rank'))}</div>`}</div></div>`;
    },

    classement: (s) => {
      const d = s.data, two = d.phase >= 2, n = d.rows.length, jc = d.jury_count || 1, cw = jc <= 4 ? 150 : jc <= 6 ? 120 : 100;
      const numPages = Math.ceil(n / 10);
      const pageSize = numPages > 1 ? 10 : Math.max(n, 1);
      const rh = Math.max(two ? 56 : 38, Math.min(86, Math.floor((two ? 700 : 740) / pageSize) - GAP));
      const jh = Array.from({ length: jc }, (_, i) => `<span>${tx('juror_label', { n: i + 1 })}</span>`).join('');
      const head = two
        ? `<div class="ch two"><span></span><span></span><span>${tx('col_public')}</span><span>${tx('col_jury')}</span><span>${tx('col_total')}</span></div>`
        : `<div class="ch one"><span></span><span></span>${jh}<span>${tx('col_average')}</span></div>`;
      const jc1 = Array.from({ length: jc }, (_, i) => `<span class="c nj nj${i}"></span>`).join('');
      const rows = d.rows.map((r, i) => {
        const pageOfRow = Math.floor(i / 10);
        const pCls = numPages > 1 ? (pageOfRow === clPage ? 'p-show' : 'p-hide') : '';
        return `<div class="r ${two ? 'two' : 'one'} ${pCls}" data-id="${r.id}" style="height:${rh}px;--k:${i % 10}">
          <b class="rk"></b><span class="nm">${nameOf(r)}<i class="tg q"></i><i class="tg t"></i></span>
          ${two ? '<span class="c pub"></span><span class="c jur"></span><span class="c tot"></span>' : jc1 + '<span class="c jur"></span>'}</div>`;
      }).join('');
      const pageNav = numPages > 1 ? `<div class="cl-nav">${Array.from({ length: numPages }, (_, p) => `<span class="cl-pg ${p === clPage ? 'on' : ''}" data-p="${p}">Page ${p + 1} / ${numPages} (${p * 10 + 1} à ${Math.min((p + 1) * 10, n)})</span>`).join('')}</div>` : '';
      return `<h2 class="t">${title(d)}</h2>${pageNav}<div style="--n:${jc};--cw:${cw}px;width:100%">${head}<div class="cl ${rh < 64 ? 'dense' : ''}" data-rh="${rh}" style="height:${pageSize * (rh + GAP)}px">${rows}</div></div>`;
    },

    qualifies: (s) => {
      const d = s.data, n = d.rows.length, cols = n <= 4 ? n : n <= 6 ? 3 : n <= 8 ? 4 : 5;
      return `<h2 class="t">${tx('qualifies_p' + d.phase)}</h2>
        <div class="qg ${n > 8 ? 'small' : ''}" style="--cols:${cols}">${d.rows.map((r, i) => `<div class="qc" style="--i:${i}">
          ${d.phase >= 2 ? photo(r, 'ph sm') : `<div class="ph sm no">${r.rank}</div>`}<div class="nm serif">${esc(r.name)}</div>
          <div class="rk">${nth(r.rank)}</div></div>`).join('')}</div>`;
    },

    podium: (s, seen) => { const items = s.data.items;
      const order = items.slice().sort((a, b) => (a.rank === 2 ? 0 : a.rank === 1 ? 1 : 2) - (b.rank === 2 ? 0 : b.rank === 1 ? 1 : 2) || a.num - b.num);
      return `<h2 class="t">${tx('podium_title')}</h2><div class="pw">${order.length ? order.map(c => `<div class="pod k${c.rank <= 3 ? c.rank : 'x'} ${seen.has(c.id) ? '' : 'new'}">
        <div class="who">${photo(c)}</div><div class="nm serif">${esc(c.name)}</div><div class="vt">${f2(c.total)} ${tx('pts')}</div>
        <div class="blk">${c.rank}</div></div>`).join('') : '<div class="slot"></div>'}</div>`; },

    // Grand gagnant : petit suspense (anneaux qui battent, texte, points) puis éclair et révélation
    gagnant: (s) => {
      const w = s.data.winners, m = w.length > 1, ms = Math.max(0, +(T.winner_suspense_ms ?? 3600)), rd = (ms / 1000).toFixed(2);
      const sus = ms ? `<div class="sus" style="--rd:${rd}s"><div class="sus-rays"></div><div class="sus-ring"></div><div class="sus-ring r2"></div>
          <div class="sus-t serif">${tx('winner_suspense')}</div><div class="dots"><span></span><span></span><span></span></div></div><div class="flashw" style="--rd:${rd}s"></div>` : '';
      return `${sus}<div class="rv" style="--rd:${ms ? rd : 0}s"><div class="crown">♛</div>
        <h2 class="t" style="margin:0">${m ? tx('winners_tie') : tx('winner_title')}</h2>
        <div class="win-nm serif ${m ? 'm' : ''}">${w.map(x => esc(x.name)).join(' &amp; ')}</div>
        <div class="win-v"><b data-count="${w[0].total}" data-dec="2" data-delay="${ms + 900}">0</b> ${tx('points')}</div></div>`;
    },

    // Générique « chaîne de télé » : les logos défilent un à un au milieu, puis les trois ensemble, puis le texte de la phase
    jingle: (s) => {
      const e = s.event, ph = (s.data && s.data.phase) || 1, sp = Math.max(0.3, +(T.jingle && T.jingle.speed) || 1);
      const items = [[e.logo_left, 'LOGO<br>GAUCHE', 'a'], [e.logo, 'LOGO<br>MILIEU', 'b'], [e.logo_right, 'LOGO<br>DROIT', 'c']]
        .map(([src, lab, k]) => ({ k, html: logoImg(src, 'jgi', lab) })).filter(x => x.html);
      const n = items.length, f = x => (x * sp).toFixed(2), t0 = 0.25 * sp, stride = 0.85 * sp, sd = 1.2 * sp, td = 1.7 * sp, pd = 2.0 * sp;
      const ts = n ? t0 + (n - 1) * stride + sd - 0.4 * sp : 0, ps = n ? ts + td - 0.4 * sp : 0.2 * sp, tot = ps + pd + 0.15;
      const k1 = T['jingle_p' + ph] || '', k2 = T['jingle_p' + ph + '_sub'] || '';
      return `<div class="jg" data-tot="${tot.toFixed(2)}" style="--sd:${f(1.2)}s;--ts:${ts.toFixed(2)}s;--td:${f(1.7)}s;--ps:${ps.toFixed(2)}s;--pd:${f(2.0)}s;--tot:${tot.toFixed(2)}s"><div class="jg-bg"></div>
        <div class="jg-beams"><i></i><i style="left:32%;animation-delay:-1.7s"></i><i style="left:64%;animation-delay:-3.4s"></i></div><div class="jg-line"></div>
        ${items.map((x, i) => `<div class="jg-solo ${x.k}" style="--d:${(t0 + i * stride).toFixed(2)}s">${x.html}</div>`).join('')}
        ${n ? `<div class="jg-trio">${items.map((x, i) => `<div class="${x.k}" style="--i:${i}">${x.html}</div>`).join('')}</div>` : ''}
        ${k1 || k2 ? `<div class="jg-phase">${k1 ? `<div class="jp-k serif">${esc(k1)}</div>` : ''}<div class="jp-rule"></div>${k2 ? `<div class="jp-s">${esc(k2)}</div>` : ''}</div>` : ''}</div>`;
    },

    neutre: () => ''
  };

  function animate(scope = stage) {
    scope.querySelectorAll('[data-count]').forEach(el => {
      const to = parseFloat(el.dataset.count), dc = +el.dataset.dec || 0, t0 = performance.now() + (+el.dataset.delay || 0), d = +T.count_ms || 900;
      const step = t => { const k = Math.max(0, Math.min(1, (t - t0) / d)), v = to * (1 - Math.pow(1 - k, 3));
        el.textContent = dc ? v.toLocaleString('fr-FR', { minimumFractionDigits: dc, maximumFractionDigits: dc }) : Math.round(v).toLocaleString('fr-FR');
        if (k < 1) requestAnimationFrame(step); };
      requestAnimationFrame(step);
    });
    requestAnimationFrame(() => requestAnimationFrame(() => scope.querySelectorAll('[data-w]').forEach(el => el.style.width = el.dataset.w + '%')));
  }

  // Met à jour une cellule seulement si son contenu change ; la fait apparaître (et compter) à la révélation.
  function setCell(el, key, html) {
    if (!el || el.dataset.k === key) return;
    el.dataset.k = key;
    el.innerHTML = html;
    el.classList.toggle('on', !!html);
    if (html) animate(el);
  }

  // Les scènes à révélation par étapes sont mises à jour en place (pas de re-rendu) pour animer les colonnes et le tri.
  const patchers = {
    classement(s, page) {
      const d = s.data, two = d.phase >= 2, cl = stage.querySelector('.cl');
      if (!cl) return;
      const rh = +cl.dataset.rh;
      const n = d.rows.length;
      const numPages = Math.ceil(n / 10);
      if (typeof page === 'number') clPage = page;
      if (clPage >= numPages) clPage = 0;
      const nav = stage.querySelector('.cl-nav');
      if (nav) nav.querySelectorAll('.cl-pg').forEach(b => b.classList.toggle('on', +b.dataset.p === clPage));

      d.rows.forEach((r, i) => {
        const el = cl.querySelector(`.r[data-id="${r.id}"]`); if (!el) return;
        const pageOfRow = Math.floor(i / 10);
        const slotOnPage = i % 10;
        const isCurrentPage = numPages <= 1 || pageOfRow === clPage;
        el.style.setProperty('--y', (numPages > 1 ? slotOnPage : i) * (rh + GAP) + 'px');
        if (numPages > 1) {
          el.classList.toggle('p-show', isCurrentPage);
          el.classList.toggle('p-hide', !isCurrentPage);
        }
        const final = r.rank > 0;
        el.classList.toggle('g1', d.phase === 3 && final && r.rank === 1);
        el.classList.toggle('g2', d.phase === 3 && final && r.rank === 2);
        el.classList.toggle('g3', d.phase === 3 && final && r.rank === 3);
        el.classList.toggle('q', !!r.q);
        const rk = el.querySelector('.rk'); rk.textContent = final ? r.rank : r.num; rk.classList.toggle('n', !final);
        el.querySelector('.tg.q').textContent = r.q ? (d.phase === 1 ? T.badge_qualified : T.badge_finalist) : '';
        el.querySelector('.tg.t').textContent = r.tie ? T.tie : '';
        const J = r.jury == null ? '' : `<strong><span data-count="${r.jury}" data-dec="${dec(r.jury)}">0</span><small>/${d.jury_max}</small></strong>`;
        if (two) {
          setCell(el.querySelector('.pub'), 'v' + r.votes, r.votes == null ? '' : `<strong><span data-count="${r.votes}">0</span><small>${tx('votes')}</small></strong>`);
          setCell(el.querySelector('.jur'), 'j' + r.jury, J);
          setCell(el.querySelector('.tot'), 't' + r.total, r.total == null ? '' : `<strong><span data-count="${r.total}" data-dec="2">0</span></strong>`);
        } else {
          (r.notes || []).forEach((n, k) => setCell(el.querySelector('.nj' + k), 'n' + n, n == null ? '' : `<strong><span data-count="${n}" data-dec="${dec(n)}">0</span></strong>`));
          setCell(el.querySelector('.jur'), 'j' + r.jury, J);
        }
      });
    },
    individuel(s) {
      const d = s.data, c = d.competitor, two = d.phase >= 2;
      const box = (cls, key, html, sub) => {
        const el = stage.querySelector('.tb.' + cls); if (!el) return;
        if (el.dataset.k === key) return;
        el.dataset.k = key; el.classList.toggle('on', !!html);
        el.querySelector('.vv').innerHTML = html; el.querySelector('.ss').innerHTML = sub;
        if (html) animate(el);
      };
      const J = c.jury == null ? '' : `<span data-count="${c.jury}" data-dec="${dec(c.jury)}">0</span><small>/${d.jury_max}</small>`;
      if (two) {
        box('pub', 'v' + c.votes, c.votes == null ? '' : `<span data-count="${c.votes}">0</span>`, c.votes == null ? '' : tx('votes'));
        box('jur', 'j' + c.jury, J, '');
        box('tot', 't' + c.total, c.total == null ? '' : `<span data-count="${c.total}" data-dec="2">0</span>`, c.total == null ? '' : `${nth(c.rank)}${c.tie ? ' · ' + T.tie : ''}`);
      } else {
        box('jur', 'j' + c.jury, J, '');
        box('rk', 'r' + c.rank, c.rank > 0 ? `${nth(c.rank)}` : '', c.q ? `<b class="ok">✔ ${tx('badge_qualified')}</b>` : (c.tie ? tx('tie') : ''));
      }
    }
  };

  function confetti(on) {
    conf.innerHTML = '';
    if (!on) return;
    const cols = ['#D6AD60', '#fff', '#C4CAD4', '#f5d98b'];
    for (let i = 0; i < 45; i++) {
      const s = document.createElement('span');
      s.style.cssText = `left:${Math.random() * 100}%;background:${cols[i % 4]};animation-duration:${4 + Math.random() * 5}s;animation-delay:${-Math.random() * 8}s`;
      conf.appendChild(s);
    }
  }

  // Logos gauche/droite : calque fixe, présent dans toutes les scènes, jamais re-rendu (donc jamais rechargé)
  let logoKey = '';
  async function setLogos(ev) {
    const key = [ev.logo_left, ev.logo_right, ev.logo].join('|');
    if (key === logoKey) return;
    const first = !logoKey; logoKey = key;
    logos.innerHTML = `<div class="cg L">${logoImg(ev.logo_left, 'cgi', 'LOGO<br>GAUCHE')}</div><div class="cg R">${logoImg(ev.logo_right, 'cgi', 'LOGO<br>DROIT')}</div>`;
    trans.querySelector('.tl').innerHTML = logoImg(ev.logo_left, 'tgi', '');
    trans.querySelector('.tr').innerHTML = logoImg(ev.logo_right, 'tgi', '');
    trans.querySelector('.tc').innerHTML = logoImg(ev.logo, 'tgc', '');
    await Promise.all([imgsReady(logos), imgsReady(trans)]);
    logos.classList.toggle('intro', first); // entrée « depuis le bas » au premier affichage seulement
  }

  // Transition : balayage doré + les 3 logos convergent, la nouvelle scène est posée pendant que l'écran est couvert
  async function transition(swap) {
    const tr = T.transition || {}, inMs = +tr.in_ms || 400, outMs = +tr.out_ms || 400, hold = tr.hold_ms ?? 350;
    trans.style.setProperty('--in', inMs + 'ms'); trans.style.setProperty('--out', outMs + 'ms');
    logos.classList.add('pulse');
    const tt = trans.querySelector('.tt'); if (tt) tt.textContent = T.transition_text || '';
    trans.className = 'in'; await sleep(inMs);
    await swap();
    await sleep(hold);
    trans.className = 'out'; await sleep(outMs);
    trans.className = ''; logos.classList.remove('pulse');
  }

  // Préchargement : pendant le compte à rebours de la régie, la scène à venir est construite hors écran (images, polices, mise en page)
  const warmEl = document.getElementById('warm');
  let warmKey = '', lastEvent = null;
  async function warm(p) {
    const key = p ? JSON.stringify(p) : '';
    if (key === warmKey) return;
    warmKey = key;
    if (!p) { warmEl.innerHTML = ''; return; }
    warmEl.innerHTML = (scenes[p.scene] || scenes.neutre)({ scene: p.scene, data: p.data, event: lastEvent, step: 0 }, new Set());
    await imgsReady(warmEl, 4000);
  }

  let jgTimer, cfTimer, advTimer, clPageTimer, clPage = 0, currentS = null;
  async function render(s) {
    currentS = s;
    if (s.scene !== prev || s.seq !== prevSeq) { seen.clear(); clPage = 0; }
    stage.innerHTML = (scenes[s.scene] || scenes.neutre)(s, seen);
    if (s.scene === 'podium') s.data.items.forEach(c => seen.add(c.id));
    await imgsReady(stage);
    stage.classList.remove('out');
    clearTimeout(jgTimer); clearTimeout(cfTimer); clearTimeout(advTimer); clearTimeout(clPageTimer);
    logos.classList.toggle('hide', s.scene === 'jingle'); // logos des coins masqués pendant le générique, puis ils reviennent
    if (s.scene === 'jingle') {
      const jg = stage.querySelector('.jg'), tot = ((jg && +jg.dataset.tot) || 1.5) * 1000, seq = s.seq;
      jgTimer = setTimeout(() => logos.classList.remove('hide'), tot);
      if (!PREVIEW) advTimer = setTimeout(() => advance(seq), Math.max(0, tot - 450)); // la scène suivante démarre juste avant la fin du générique
    }
    confetti(false);
    if (s.scene === 'gagnant') cfTimer = setTimeout(() => confetti(true), Math.max(0, +(T.winner_suspense_ms ?? 3600)) + 250);
    if (patchers[s.scene]) { patchers[s.scene](s); stage.querySelectorAll('.jn').forEach(animate); } else animate();
    if (s.scene === 'classement' && s.data && s.data.rows && s.data.rows.length > 10) {
      const np = Math.ceil(s.data.rows.length / 10);
      const cycle = () => {
        clPageTimer = setTimeout(() => {
          clPage = (clPage + 1) % np;
          if (patchers.classement) patchers.classement(s, clPage);
          cycle();
        }, 8500);
      };
      cycle();
    }
    prev = s.scene; prevSeq = s.seq;
  }

  let last = null, prev = null, prevSeq = null; const seen = new Set();
  async function show(s) {
    // Même scène, même publication, autre étape : on met à jour en place
    if (s.scene === prev && s.seq === prevSeq && patchers[s.scene]) { patchers[s.scene](s); return; }
    const soft = s.scene === prev && s.seq === prevSeq && s.scene === 'podium';
    const useTr = !PREVIEW && T.transition && T.transition.enabled && prev !== null && !soft && s.scene !== 'neutre' && s.scene !== 'jingle' && prev !== 'jingle';
    if (useTr) { await transition(() => render(s)); return; }
    if (!soft && stage.innerHTML) { stage.classList.add('out'); await sleep(350); }
    await render(s);
  }

  // Fin du générique : le serveur passe à la scène suivante préparée dans la régie (s'il y en a une)
  async function advance(seq) {
    try { await fetch('/api/advance.php', { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ seq }) }); } catch (e) {}
    clearTimeout(tickTimer); tick();
  }
  let tickTimer, busy = false;
  async function tick() {
    if (busy) return;
    busy = true;
    try {
      const r = await fetch('/api/state.php' + qs, { cache: 'no-store' });
      const s = await r.json();
      T = s.texts || T;
      lastEvent = s.event;
      await setLogos(s.event);
      warm(s.pending);
      const key = s.scene + ':' + s.seq + ':' + s.step;
      if (key !== last) { last = key; await show(s); }
    } catch (e) { console.error(e); /* on garde l'écran actuel en cas d'erreur */ }
    busy = false;
    tickTimer = setTimeout(tick, 700);
  }
  tick();
})();
