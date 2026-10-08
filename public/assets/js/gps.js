/**
 * One-tap report: read the phone's GPS, fill the hidden fields, submit the form.
 * If the location cannot be read in time, the report is still sent (without coordinates).
 */
(function () {
  'use strict';
  var form = document.getElementById('report-form');
  if (!form) { return; }
  var btn = document.getElementById('report-btn');
  var statusEl = document.getElementById('gps-status');
  var latEl = document.getElementById('lat');
  var lngEl = document.getElementById('lng');
  var accEl = document.getElementById('acc');
  var busy = false;
  var HARD_LIMIT_MS = 10000; // never make someone in danger wait longer than this

  function say(t) { if (statusEl) { statusEl.textContent = t; } }

  form.addEventListener('submit', function (ev) {
    ev.preventDefault();
    if (busy) { return; }
    busy = true;
    btn.disabled = true;
    btn.classList.add('is-sending');
    say('Getting your location…');

    var finished = false;
    function send(pos) {
      if (finished) { return; }
      finished = true;
      if (pos && pos.coords) {
        latEl.value = pos.coords.latitude;
        lngEl.value = pos.coords.longitude;
        accEl.value = pos.coords.accuracy;
        say('Sending your report…');
      } else {
        say('Location not available. Sending your report anyway…');
      }
      form.submit();
    }

    var timer = setTimeout(function () { send(null); }, HARD_LIMIT_MS);

    if (!navigator.geolocation) { send(null); return; }

    navigator.geolocation.getCurrentPosition(
      function (pos) { clearTimeout(timer); send(pos); },
      function () {
        // High accuracy failed: quickly accept any position the phone already knows
        navigator.geolocation.getCurrentPosition(
          function (pos) { clearTimeout(timer); send(pos); },
          function () { clearTimeout(timer); send(null); },
          { enableHighAccuracy: false, timeout: 3000, maximumAge: 600000 }
        );
      },
      { enableHighAccuracy: true, timeout: 6000, maximumAge: 30000 }
    );
  });
})();
