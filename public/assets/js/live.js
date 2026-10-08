/**
 * Keeps a page current without any API: it re-reads the same PHP page,
 * then swaps the parts marked data-live="..." with the fresh HTML.
 * If the part marked data-reload-key changes, the page reloads (once nobody is typing).
 */
(function () {
  'use strict';
  var me = document.currentScript;
  var interval = (parseInt(me && me.getAttribute('data-interval'), 10) || 10) * 1000;
  var busy = false;

  function typing() {
    var boxes = document.querySelectorAll('main textarea');
    for (var i = 0; i < boxes.length; i++) {
      if (boxes[i].value.trim() !== '' && boxes[i].value !== boxes[i].defaultValue) { return true; }
    }
    return false;
  }

  function tick() {
    if (busy || document.hidden) { return; }
    busy = true;
    fetch(window.location.href, { credentials: 'same-origin', cache: 'no-store' })
      .then(function (r) {
        if (!r.ok) { throw new Error('bad status'); }
        if (r.redirected && /login\.php/.test(r.url)) { window.location.href = r.url; throw new Error('logged out'); }
        return r.text();
      })
      .then(function (html) {
        var doc = new DOMParser().parseFromString(html, 'text/html');
        var live = document.querySelectorAll('[data-live]');
        for (var i = 0; i < live.length; i++) {
          var key = live[i].getAttribute('data-live');
          var fresh = doc.querySelector('[data-live="' + key + '"]');
          if (fresh && fresh.innerHTML !== live[i].innerHTML) { live[i].innerHTML = fresh.innerHTML; }
        }
        var cur = document.querySelector('[data-reload-key]');
        var nxt = doc.querySelector('[data-reload-key]');
        if (cur && nxt && cur.getAttribute('data-reload-key') !== nxt.getAttribute('data-reload-key') && !typing()) {
          window.location.href = window.location.pathname;
        }
      })
      .catch(function () { /* weak network: try again next time */ })
      .then(function () { busy = false; });
  }

  setInterval(tick, interval);
  document.addEventListener('visibilitychange', function () { if (!document.hidden) { tick(); } });
})();
