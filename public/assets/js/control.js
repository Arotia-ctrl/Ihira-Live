(() => {
  const C = window.CTRL, $ = id => document.getElementById(id);
  let sel = localStorage.getItem('ch_sel');
  if (!C.scenes[sel]) sel = 'accueil';
  let liveScene = '';
  let liveCompId = null;

  function updateCompButtons() {
    const locked = isLocked('individuel');
    const opts = $('comp').options;
    const cur = $('comp').selectedIndex;
    if ($('btn-comp-prev')) $('btn-comp-prev').disabled = locked || cur <= 0;
    if ($('btn-comp-next')) $('btn-comp-next').disabled = locked || (liveScene === 'individuel' && cur >= opts.length - 1);
  }

  function buildScenes() {
    updateCompButtons();
    $('scenes').innerHTML = Object.entries(C.scenes).map(([k, l]) => {
      const na = C.unavailable.includes(k);
      const locked = na || (C.resultScenes.includes(k) && !C.validated);
      return `<button class="scene ${k === sel ? 'sel' : ''} ${k === liveScene ? 'live' : ''} ${locked ? 'locked' : ''}" data-s="${k}" ${na ? 'disabled' : ''}>${l}${na ? ' (hors phase)' : locked ? ' 🔒' : ''}</button>`;
    }).join('');
  }
  function preview() {
    $('comp-wrap').style.display = sel === 'individuel' ? '' : 'none';
    updateCompButtons();
    $('pv').src = '/display/?' + new URLSearchParams({ preview: 1, scene: sel, competitor: $('comp').value || 0 });
  }
  function scale() { $('pv').style.transform = `scale(${$('pvbox').clientWidth / 1920})`; }
  const msg = (t, err) => { $('msg').textContent = t; $('msg').style.color = err ? '#f87171' : ''; };

  async function api(body) {
    const r = await fetch('/api/action.php', { method: 'POST', headers: { 'Content-Type': 'application/json', 'X-CSRF': C.csrf }, body: JSON.stringify(body) });
    const j = await r.json().catch(() => ({ error: 'Réponse invalide' }));
    if (!r.ok) throw new Error(j.error || 'Erreur');
    return j;
  }
  const isLocked = k => C.unavailable.includes(k) || (C.resultScenes.includes(k) && !C.validated);
  const compLabel = id => { const o = [...$('comp').options].find(x => +x.value === +id); return o ? o.textContent : ''; };
  const labelOf = (scene, comp) => C.scenes[scene] + (scene === 'individuel' && comp ? ' — ' + compLabel(comp) : '');

  // Publication immédiate (la scène suivante est déjà chargée en coulisses : aucun compte à rebours nécessaire)
  async function launch(scene, comp) {
    if (isLocked(scene)) return msg('Scène verrouillée (phase non validée ou hors phase).', true);
    try { await api({ action: 'publish', scene, competitor_id: comp || null }); } catch (e) { return msg(e.message, true); }
    msg('Publié : ' + labelOf(scene, comp));
    const o = $('obs-scene').value;
    if ($('obs-auto').checked && OBS.connected && o) OBS.setScene(o).catch(e => msg(e.message, true));
    poll(true);
  }

  // Scène suivante : choisie ici, chargée aussitôt par l'écran ; la transition (08) y passe toute seule à sa fin
  const nextScene = () => $('next-scene').value, nextComp = () => (nextScene() === 'individuel' ? (+$('next-comp').value || null) : null);
  function buildNext() {
    $('next-scene').innerHTML = '<option value="">— aucune —</option>' + Object.entries(C.scenes).filter(([k]) => k !== 'jingle').map(([k, l]) => {
      const na = C.unavailable.includes(k), locked = na || (C.resultScenes.includes(k) && !C.validated);
      return `<option value="${k}" ${locked ? 'disabled' : ''}>${l}${na ? ' (hors phase)' : locked ? ' 🔒' : ''}</option>`;
    }).join('');
    syncNext();
  }
  function syncNext() { $('next-comp-wrap').style.display = nextScene() === 'individuel' ? '' : 'none'; $('btn-next').disabled = !nextScene(); }
  async function saveNext() {
    syncNext();
    try {
      if (!nextScene()) { await api({ action: 'cancel_prepare' }); return msg('Aucune scène suivante.'); }
      await api({ action: 'prepare', scene: nextScene(), competitor_id: nextComp() });
      msg('Scène suivante prête : ' + labelOf(nextScene(), nextComp()));
    } catch (e) { msg(e.message, true); }
  }
  $('next-scene').onchange = saveNext;
  $('next-comp').onchange = saveNext;
  $('btn-next').onclick = async () => { const sc = nextScene(), cp = nextComp(); if (!sc) return; await launch(sc, cp); };

  async function reveal(delta) { try { await api({ action: 'reveal', delta }); poll(true); } catch (e) { msg(e.message, true); } }

  $('scenes').onclick = e => {
    const b = e.target.closest('[data-s]'); if (!b) return;
    sel = b.dataset.s; localStorage.setItem('ch_sel', sel); buildScenes(); preview();
  };
  function stepComp(dir = 1) {
    const opts = $('comp').options;
    if (!opts.length) return;
    let curIdx = $('comp').selectedIndex;
    if (curIdx === -1) curIdx = 0;
    let nextIdx = curIdx;
    if (liveScene === 'individuel') {
      if (dir > 0 && curIdx >= opts.length - 1) {
        msg('Dernier compétiteur atteint.');
        updateCompButtons();
        return;
      }
      if (dir < 0 && curIdx <= 0) {
        msg('Premier compétiteur atteint.');
        updateCompButtons();
        return;
      }
      nextIdx = curIdx + dir;
    }
    $('comp').selectedIndex = nextIdx;
    updateCompButtons();
    preview();
    sel = 'individuel';
    localStorage.setItem('ch_sel', sel);
    buildScenes();
    launch('individuel', +opts[nextIdx].value);
  }

  $('comp').onchange = () => { updateCompButtons(); preview(); };
  if ($('btn-comp-next')) $('btn-comp-next').onclick = () => stepComp(1);
  if ($('btn-comp-prev')) $('btn-comp-prev').onclick = () => stepComp(-1);
  $('btn-publish').onclick = () => launch(sel, sel === 'individuel' ? (+$('comp').value || null) : null);
  $('btn-hide').onclick = () => launch('neutre');
  $('btn-wait').onclick = () => launch('accueil');
  $('rev-minus').onclick = () => reveal(-1);
  $('rev-plus').onclick = () => reveal(1);

  let timer;
  async function poll(once) {
    clearTimeout(timer);
    try {
      const s = await (await fetch('/api/state.php', { cache: 'no-store' })).json();
      if (s.scene !== liveScene) { liveScene = s.scene; buildScenes(); }
      $('live').textContent = C.scenes[s.scene] || s.scene;
      if (s.scene === 'individuel' && s.data && s.data.competitor) {
        liveCompId = s.data.competitor.id;
        if (document.activeElement !== $('comp')) {
          $('comp').value = liveCompId;
          updateCompButtons();
        }
      } else {
        liveCompId = null;
        updateCompButtons();
      }
      const rev = s.max_step > 0;
      $('rev-info').textContent = rev ? `étape ${s.step}/${s.max_step}` : 'aucune révélation sur cette scène';
      // La scène suivante reflète l'état du serveur (elle se vide quand elle est passée à l'antenne), sauf pendant que vous la choisissez
      if (document.activeElement !== $('next-scene') && document.activeElement !== $('next-comp')) {
        const want = s.next ? s.next.scene : '';
        if ($('next-scene').value !== want) { $('next-scene').value = want; syncNext(); }
        if (s.next && s.next.competitor) $('next-comp').value = s.next.competitor;
      }
      $('rev-minus').disabled = $('rev-plus').disabled = !rev;
    } catch (e) {}
    timer = setTimeout(poll, 1200);
  }

  // OBS
  const cfg = OBS.cfg();
  $('o-host').value = cfg.host || '127.0.0.1'; $('o-port').value = cfg.port || 4455; $('o-pw').value = cfg.password || '';
  $('obs-auto').checked = !!cfg.auto;
  $('obs-auto').onchange = () => OBS.save({ auto: $('obs-auto').checked });
  $('o-conn').onclick = () => { OBS.save({ host: $('o-host').value.trim(), port: +$('o-port').value, password: $('o-pw').value, enabled: true }); OBS.ws && OBS.ws.close(); OBS.connect(); };
  $('o-disc').onclick = () => OBS.disconnect();
  $('o-switch').onclick = () => { const o = $('obs-scene').value; if (o) OBS.setScene(o).catch(e => msg(e.message, true)); };
  $('obs-scene').onchange = () => OBS.save({ scene: $('obs-scene').value });
  OBS.on(o => {
    $('o-state').textContent = o.connected ? 'Connecté — scène active : ' + (o.current || '?') : (o.error || (o.cfg().enabled ? 'Connexion en cours… (nouvelle tentative automatique)' : 'Non connecté'));
    const keep = o.cfg().scene || $('obs-scene').value;
    $('obs-scene').innerHTML = o.scenes.map(n => `<option ${n === keep ? 'selected' : ''}>${n.replace(/</g, '&lt;')}</option>`).join('');
  });

  addEventListener('resize', scale);
  buildScenes(); buildNext(); preview(); scale(); poll();
})();
