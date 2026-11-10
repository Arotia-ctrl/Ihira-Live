// Client minimal OBS WebSocket 5.x (aucune dépendance, fonctionne hors ligne).
(() => {
  const LS = 'chorale_obs';
  const badge = document.getElementById('obs-badge');
  const b64 = async s => btoa(String.fromCharCode(...new Uint8Array(await crypto.subtle.digest('SHA-256', new TextEncoder().encode(s)))));

  const OBS = window.OBS = {
    ws: null, connected: false, scenes: [], current: null, handlers: [], pending: new Map(), n: 0, timer: null,
    cfg() { try { return JSON.parse(localStorage.getItem(LS)) || {}; } catch { return {}; } },
    save(c) { localStorage.setItem(LS, JSON.stringify({ ...this.cfg(), ...c })); },
    on(fn) { this.handlers.push(fn); fn(this); },
    emit() {
      if (badge) { badge.textContent = this.connected ? 'OBS ● connecté' : 'OBS ○ hors ligne'; badge.className = 'badge ' + (this.connected ? 'on' : 'off'); }
      this.handlers.forEach(f => f(this));
    },
    connect() {
      const c = this.cfg();
      if (!c.enabled || (this.ws && this.ws.readyState <= 1)) return;
      clearTimeout(this.timer);
      let ws;
      try { ws = this.ws = new WebSocket(`ws://${c.host || '127.0.0.1'}:${c.port || 4455}`); } catch { return this.retry(); }
      ws.onmessage = async ev => {
        const m = JSON.parse(ev.data), d = m.d;
        if (m.op === 0) {
          const id = { rpcVersion: 1, eventSubscriptions: 4 };
          if (d.authentication) {
            const secret = await b64((c.password || '') + d.authentication.salt);
            id.authentication = await b64(secret + d.authentication.challenge);
          }
          ws.send(JSON.stringify({ op: 1, d: id }));
        } else if (m.op === 2) {
          this.connected = true; this.error = null;
          try { await this.refresh(); } catch { this.emit(); }
        } else if (m.op === 5 && d.eventType === 'CurrentProgramSceneChanged') {
          this.current = d.eventData.sceneName; this.emit();
        } else if (m.op === 7) {
          const p = this.pending.get(d.requestId); if (!p) return;
          this.pending.delete(d.requestId);
          d.requestStatus.result ? p.res(d.responseData || {}) : p.rej(new Error(d.requestStatus.comment || 'Erreur OBS'));
        }
      };
      ws.onclose = ev => { this.connected = false; this.error = ev.code === 4009 ? 'Mot de passe OBS incorrect' : null; this.emit(); this.retry(); };
      ws.onerror = () => {};
    },
    retry() { clearTimeout(this.timer); if (this.cfg().enabled) this.timer = setTimeout(() => this.connect(), 4000); },
    disconnect() { this.save({ enabled: false }); clearTimeout(this.timer); if (this.ws) this.ws.close(); this.connected = false; this.emit(); },
    req(type, data = {}) {
      return new Promise((res, rej) => {
        if (!this.connected) return rej(new Error('OBS non connecté'));
        const requestId = String(++this.n);
        this.pending.set(requestId, { res, rej });
        this.ws.send(JSON.stringify({ op: 6, d: { requestType: type, requestId, requestData: data } }));
        setTimeout(() => { if (this.pending.delete(requestId)) rej(new Error('Délai dépassé')); }, 5000);
      });
    },
    setScene(name) { return this.req('SetCurrentProgramScene', { sceneName: name }); },
    async refresh() {
      const r = await this.req('GetSceneList');
      this.scenes = r.scenes.map(s => s.sceneName).reverse();
      this.current = r.currentProgramSceneName;
      this.emit();
    }
  };
  OBS.emit();
  OBS.connect();
})();
