/** Admin case page: small location map and quick-reply buttons. */
(function () {
  'use strict';

  // Quick replies fill the message box
  var box = document.getElementById('msg-body');
  document.addEventListener('click', function (e) {
    var chip = e.target.closest ? e.target.closest('.chip') : null;
    if (chip && box) {
      box.value = chip.getAttribute('data-fill') || '';
      box.focus();
    }
  });

  // Location map
  var holder = document.getElementById('case-map');
  if (!holder || typeof L === 'undefined') { return; }
  var lat = parseFloat(holder.getAttribute('data-lat'));
  var lng = parseFloat(holder.getAttribute('data-lng'));
  var colors = { critical: '#c8102e', medium: '#e58a00', low: '#2a8a4a' };
  var map = L.map(holder).setView([lat, lng], 15);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);
  L.circleMarker([lat, lng], {
    radius: 11, color: '#fff', weight: 3, fillColor: colors[holder.getAttribute('data-sev')] || '#c8102e', fillOpacity: 1
  }).addTo(map);
})();
