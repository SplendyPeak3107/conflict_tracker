/**
 * Admin live map + alerts.
 * No API: every few seconds this page re-reads its own PHP page and swaps in the fresh feed,
 * counters and map data, so sound, map position and scroll are never lost.
 */
(function () {
  'use strict';

  var app = document.getElementById('admin-app');
  if (!app || typeof L === 'undefined') {
    var m = document.getElementById('map');
    if (m) { m.innerHTML = '<p style="padding:1rem">The map library could not load. Check your internet connection, or see README to host Leaflet locally.</p>'; }
    return;
  }

  var refreshMs = (parseInt(app.getAttribute('data-refresh'), 10) || 5) * 1000;
  var caseUrl = app.getAttribute('data-case-url');
  var COLORS = { critical: '#c8102e', medium: '#e58a00', low: '#2a8a4a' };
  var LABEL = { critical: 'Critical', medium: 'Medium', low: 'Low' };

  // ------------------------------------------------------------------ helpers
  function el(tag, cls, text) {
    var n = document.createElement(tag);
    if (cls) { n.className = cls; }
    if (text !== undefined) { n.textContent = text; }
    return n;
  }
  function lsGet(k) { try { return window.localStorage.getItem(k); } catch (e) { return null; } }
  function lsSet(k, v) { try { window.localStorage.setItem(k, String(v)); } catch (e) { /* ignore */ } }
  function readData() {
    var s = document.getElementById('pw-data');
    try { return JSON.parse(s.textContent); } catch (e) { return null; }
  }

  // ---------------------------------------------------------------------- map
  var map = L.map('map', { zoomControl: true });
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);

  var saved = null;
  try { saved = JSON.parse(sessionStorage.getItem('pw_view') || 'null'); } catch (e) { saved = null; }
  var viewSet = false;
  if (saved && typeof saved.lat === 'number') {
    map.setView([saved.lat, saved.lng], saved.zoom);
    viewSet = true;
  } else {
    map.setView([parseFloat(app.getAttribute('data-lat')), parseFloat(app.getAttribute('data-lng'))], parseInt(app.getAttribute('data-zoom'), 10) || 8);
  }
  map.on('moveend', function () {
    var c = map.getCenter();
    try { sessionStorage.setItem('pw_view', JSON.stringify({ lat: c.lat, lng: c.lng, zoom: map.getZoom() })); } catch (e) { /* ignore */ }
  });

  var layer = L.layerGroup().addTo(map);
  var markerById = {};
  var pulseUntil = {};
  var openPopupId = null;
  var redrawing = false;

  map.on('popupopen', function (e) { if (!redrawing && e.popup && e.popup._pwId) { openPopupId = e.popup._pwId; } });
  map.on('popupclose', function () { if (!redrawing) { openPopupId = null; } });

  function icon(m) {
    var pulsing = (pulseUntil[m.id] && pulseUntil[m.id] > Date.now()) || (m.sev === 'critical' && m.status === 'received');
    return L.divIcon({
      className: 'pw-pin pw-' + m.sev + (pulsing ? ' pw-pulse' : ''),
      html: '<span></span>',
      iconSize: [28, 28],
      iconAnchor: [14, 14],
      popupAnchor: [0, -14]
    });
  }

  function popupNode(m) {
    var box = el('div', 'pw-popup');
    var h = el('strong', null, '#' + m.id + ' · ' + LABEL[m.sev] + ' · ' + m.statusLabel);
    box.appendChild(h);
    box.appendChild(el('div', null, m.name + ' · ' + m.ago));
    box.appendChild(el('div', 'pw-popup-desc', m.desc || 'No description yet'));
    var a = el('a', null, 'Open case');
    a.href = caseUrl + m.id;
    a.target = '_blank';
    a.rel = 'noopener';
    box.appendChild(a);
    return box;
  }

  function drawMarkers(markers) {
    redrawing = true;
    layer.clearLayers();
    markerById = {};
    var pts = [];
    markers.forEach(function (m) {
      var mk = L.marker([m.lat, m.lng], { icon: icon(m), title: '#' + m.id });
      var popup = L.popup().setContent(popupNode(m));
      popup._pwId = m.id;
      mk.bindPopup(popup);
      mk.addTo(layer);
      markerById[m.id] = mk;
      pts.push([m.lat, m.lng]);
    });
    if (openPopupId && markerById[openPopupId]) { markerById[openPopupId].openPopup(); }
    redrawing = false;

    if (!viewSet && pts.length) {
      map.fitBounds(pts, { padding: [50, 50], maxZoom: 14 });
      viewSet = true;
    }
    lastPoints = pts;
  }
  var lastPoints = [];

  document.getElementById('fit-all').addEventListener('click', function () {
    if (lastPoints.length) { map.fitBounds(lastPoints, { padding: [50, 50], maxZoom: 14 }); }
  });

  // Click a card in the feed to fly to its marker
  document.getElementById('feed').addEventListener('click', function (e) {
    if (e.target.closest('a,button')) { return; }
    var item = e.target.closest('.feed-item');
    if (!item) { return; }
    var mk = markerById[item.getAttribute('data-id')];
    if (mk) {
      map.setView(mk.getLatLng(), Math.max(map.getZoom(), 14));
      mk.openPopup();
    }
  });

  // -------------------------------------------------------------------- sound
  var ctx = null;
  var enableBtn = document.getElementById('enable-alerts');

  function audioReady() { return ctx && ctx.state === 'running'; }
  function syncButton() {
    var notifOk = ('Notification' in window) ? Notification.permission === 'granted' : true;
    enableBtn.style.display = (audioReady() && notifOk) ? 'none' : '';
    if (audioReady() && !notifOk) { enableBtn.textContent = 'Turn on desktop alerts'; }
  }
  function ensureCtx() {
    if (!ctx) {
      var C = window.AudioContext || window.webkitAudioContext;
      if (C) {
        ctx = new C();
        ctx.onstatechange = syncButton;
      }
    }
    if (ctx && ctx.state === 'suspended') { ctx.resume(); }
    return ctx;
  }
  function tone(freq, start, dur, type, vol) {
    var o = ctx.createOscillator();
    var g = ctx.createGain();
    o.type = type || 'square';
    o.frequency.value = freq;
    var t0 = ctx.currentTime + start;
    g.gain.setValueAtTime(0.0001, t0);
    g.gain.exponentialRampToValueAtTime(vol || 0.25, t0 + 0.02);
    g.gain.exponentialRampToValueAtTime(0.0001, t0 + dur);
    o.connect(g);
    g.connect(ctx.destination);
    o.start(t0);
    o.stop(t0 + dur + 0.05);
  }
  function sound(kind) {
    ensureCtx();
    if (!audioReady()) { syncButton(); return; }
    var i;
    if (kind === 'overdue') {
      for (i = 0; i < 10; i++) { tone(i % 2 ? 620 : 960, i * 0.32, 0.3, 'sawtooth', 0.3); }
    } else if (kind === 'critical') {
      for (i = 0; i < 4; i++) { tone(988, i * 0.4, 0.16, 'square', 0.3); tone(1319, i * 0.4 + 0.18, 0.16, 'square', 0.3); }
    } else if (kind === 'update') {
      tone(660, 0, 0.18, 'sine', 0.25);
    } else {
      tone(880, 0, 0.18, 'square', 0.25);
      tone(1175, 0.22, 0.25, 'square', 0.25);
    }
  }

  enableBtn.addEventListener('click', function () {
    ensureCtx();
    if (audioReady()) { sound('update'); }
    if ('Notification' in window && Notification.permission === 'default') {
      Notification.requestPermission().then(syncButton);
    }
    setTimeout(syncButton, 300);
  });

  function desktopNotify(title, body, tag) {
    if ('Notification' in window && Notification.permission === 'granted') {
      try { new Notification(title, { body: body, tag: tag, requireInteraction: true }); } catch (e) { /* ignore */ }
    }
  }

  // ------------------------------------------------------------------- toasts
  var toastBox = document.getElementById('toasts');
  function toast(kind, title, body, reportId, sticky) {
    var t = el('div', 'toast toast-' + kind);
    var head = el('strong', null, title);
    t.appendChild(head);
    if (body) { t.appendChild(el('p', null, body)); }
    if (reportId) {
      var a = el('a', null, 'Open case');
      a.href = caseUrl + reportId;
      a.target = '_blank';
      a.rel = 'noopener';
      t.appendChild(a);
    }
    var x = el('button', 'toast-x', '×');
    x.type = 'button';
    x.setAttribute('aria-label', 'Dismiss');
    x.addEventListener('click', function () { t.remove(); });
    t.appendChild(x);
    toastBox.appendChild(t);
    while (toastBox.children.length > 6) { toastBox.removeChild(toastBox.firstChild); }
    if (!sticky) { setTimeout(function () { if (t.parentNode) { t.remove(); } }, 25000); }
  }

  // ------------------------------------------------------------------- alerts
  var baseTitle = document.title;
  var unseen = 0;
  function bumpTitle(n) {
    if (document.hidden) { unseen += n; document.title = '(' + unseen + ') NEW ALERT · ' + baseTitle; }
  }
  document.addEventListener('visibilitychange', function () {
    if (!document.hidden) { unseen = 0; document.title = baseTitle; }
  });

  function processAlerts(d) {
    var lastR = parseInt(lsGet('pw_last_report'), 10);
    var lastU = parseInt(lsGet('pw_last_update'), 10);
    var first = isNaN(lastR) || isNaN(lastU);
    if (first) { lastR = d.latest_report; lastU = d.latest_update; }

    var newR = d.recent_reports.filter(function (r) { return r.id > lastR; }).sort(function (a, b) { return a.id - b.id; });
    var newU = d.recent_updates.filter(function (u) { return u.id > lastU; }).sort(function (a, b) { return a.id - b.id; });

    var anyCritical = false;
    newR.slice(-5).forEach(function (r) {
      var crit = r.severity === 'critical';
      if (crit) { anyCritical = true; }
      pulseUntil[r.id] = Date.now() + 40000;
      toast(crit ? 'critical' : 'new', 'New report #' + r.id + ' · ' + LABEL[r.severity], r.name + (r.text ? ' — ' + r.text : ''), r.id, crit);
      desktopNotify('New ' + LABEL[r.severity] + ' report #' + r.id, r.name + (r.text ? ': ' + r.text : ''), 'r' + r.id);
    });
    newU.slice(-5).forEach(function (u) {
      toast('update', 'Update on #' + u.report_id, u.name + ': ' + u.text, u.report_id, false);
      desktopNotify('Update on report #' + u.report_id, u.name + ': ' + u.text, 'u' + u.id);
    });

    if (newR.length) { sound(anyCritical ? 'critical' : 'new'); } else if (newU.length) { sound('update'); }
    if (newR.length || newU.length) { bumpTitle(newR.length + newU.length); }

    lsSet('pw_last_report', Math.max(lastR, d.latest_report));
    lsSet('pw_last_update', Math.max(lastU, d.latest_update));

    // OVERDUE: alarm once per case
    var seen = [];
    try { seen = JSON.parse(lsGet('pw_overdue') || '[]'); } catch (e) { seen = []; }
    var fresh = d.overdue.filter(function (id) { return seen.indexOf(id) < 0; });
    if (fresh.length) {
      sound('overdue');
      fresh.forEach(function (id) {
        toast('critical', 'OVERDUE: critical report #' + id, 'Not acknowledged yet.', id, true);
        desktopNotify('OVERDUE critical report #' + id, 'Nobody has acknowledged it yet.', 'o' + id);
      });
      bumpTitle(fresh.length);
    }
    // keep only ids that are still overdue, so a re-opened case can alarm again
    lsSet('pw_overdue', JSON.stringify(d.overdue));
  }

  function apply(d) {
    if (!d) { return; }
    processAlerts(d);
    drawMarkers(d.markers);
  }

  // ------------------------------------------------------------------ refresh
  var connEl = document.getElementById('conn');
  var busy = false;
  var failures = 0;
  function setConn(ok) {
    connEl.className = 'conn ' + (ok ? 'ok' : 'bad');
    connEl.textContent = ok ? 'Live' : 'Connection lost, retrying…';
  }

  function refresh() {
    if (busy) { return; }
    busy = true;
    fetch(window.location.href, { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) {
        if (r.redirected && /login\.php/.test(r.url)) { window.location.href = r.url; throw new Error('logged out'); }
        if (!r.ok) { throw new Error('status ' + r.status); }
        return r.text();
      })
      .then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        ['overdue-banner', 'stats', 'feed'].forEach(function (id) {
          var cur = document.getElementById(id);
          var nxt = doc.getElementById(id);
          if (cur && nxt && cur.innerHTML !== nxt.innerHTML) { cur.innerHTML = nxt.innerHTML; }
        });
        var s = doc.getElementById('pw-data');
        if (s) { apply(JSON.parse(s.textContent)); }
        failures = 0;
        setConn(true);
      })
      .catch(function () { failures++; if (failures >= 2) { setConn(false); } })
      .then(function () { busy = false; });
  }

  // ---------------------------------------------------------------------- go
  ensureCtx();
  syncButton();
  setTimeout(syncButton, 800);
  apply(readData());
  setInterval(refresh, refreshMs);
})();
