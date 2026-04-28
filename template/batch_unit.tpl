<div class="full-line-box">
  <strong>&#128506; OSM Map Plus &mdash; G&eacute;otaggage</strong>
</div>

<div class="half-line-info-box">
  <label>Latitude</label>
  <input type="text" size="10" id="osmme-lat-{$element.ID}" name="osmlat-{$element.ID}" value="{$element.latitude}" placeholder="ex: 48.8566">
</div>
<div class="half-line-info-box">
  <label>Longitude</label>
  <input type="text" size="10" id="osmme-lon-{$element.ID}" name="osmlon-{$element.ID}" value="{$element.longitude}" placeholder="ex: 2.3522">
</div>

<div class="full-line-box">
  <!-- Barre recherche + boutons presse-papier -->
  <div style="display:flex;gap:6px;margin-bottom:6px;flex-wrap:wrap;position:relative;">
    <input type="text" id="osmme-search-{$element.ID}" placeholder="&#128269; Rechercher un lieu..." autocomplete="off"
      style="flex:1;min-width:150px;padding:5px 8px;border:1px solid #ccc;border-radius:4px;font-size:0.88rem;">
    <button type="button" id="osmme-search-btn-{$element.ID}" title="Rechercher"
      style="padding:5px 10px;background:#1a73e8;color:#fff;border:none;border-radius:4px;cursor:pointer;">&#128269;</button>
    <button type="button" id="osmme-copy-btn-{$element.ID}" title="Copier les coordonn&eacute;es"
      style="padding:5px 10px;background:#34a853;color:#fff;border:none;border-radius:4px;cursor:pointer;">&#128203; Copier</button>
    <button type="button" id="osmme-paste-btn-{$element.ID}" title="Coller les coordonn&eacute;es"
      style="padding:5px 10px;background:#ff6d00;color:#fff;border:none;border-radius:4px;cursor:pointer;">&#128203; Coller</button>
    <div id="osmme-results-{$element.ID}"
      style="display:none;position:absolute;top:34px;left:0;right:80px;background:#fff;border:1px solid #ccc;border-radius:4px;z-index:9999;box-shadow:0 2px 8px rgba(0,0,0,.15);max-height:180px;overflow-y:auto;"></div>
  </div>
  <div id="osm-unit-map-{$element.ID}" style="height:{$OSM_UNIT_HEIGHT}px;width:100%;margin:5px 0;border:1px solid #ccc;border-radius:4px;"></div>
  <p style="font-size:0.82em;color:#666;margin:4px 0 0;">
    &#128161; Cliquez sur la carte ou d&eacute;placez le marqueur pour modifier la position.
  </p>
</div>

<script>
var osmUnitData_{$element.ID} = {
  id:  {$element.ID},
  lat: {if $element.latitude}{$element.latitude}{else}null{/if},
  lon: {if $element.longitude}{$element.longitude}{else}null{/if}
};
</script>
<script>
{literal}
(function() {
  var _d      = osmUnitData_{/literal}{$element.ID}{literal};
  var imageID = _d.id;
  var initLat = _d.lat;
  var initLon = _d.lon;
  var tileUrl  = window.OSM_BATCH_TILE_URL  || 'https://tile.openstreetmap.org/{z}/{x}/{y}.png';
  var tileAttr = window.OSM_BATCH_TILE_ATTR || '&copy; OpenStreetMap contributors';
  var tileZoom = window.OSM_BATCH_TILE_MAXZOOM || 19;
  var map, marker;

  function initMap() {
    var _L = window.OSM_L || window.L;
    if (!_L) { setTimeout(initMap, 200); return; }
    var divId = 'osm-unit-map-{/literal}{$element.ID}{literal}';
    var div = document.getElementById(divId);
    if (!div || div.getAttribute('data-osm-init')) return;
    div.setAttribute('data-osm-init', '1');
    var center = (initLat && initLon) ? [initLat, initLon] : [46.5, 2.5];
    var zoom   = (initLat && initLon) ? 13 : 5;
    map = _L.map(divId, {center: center, zoom: zoom});
    _L.tileLayer(tileUrl, {attribution: tileAttr, maxZoom: tileZoom}).addTo(map);
    if (initLat && initLon) {
      marker = _L.marker([initLat, initLon], {draggable: true})
        .bindPopup(initLat.toFixed(6) + ', ' + initLon.toFixed(6)).addTo(map);
      bindMarker(marker);
    }
    map.on('click', function(e) {
      var lat = e.latlng.lat.toFixed(6), lon = e.latlng.lng.toFixed(6);
      if (marker) { marker.setLatLng(e.latlng); }
      else { marker = _L.marker(e.latlng, {draggable: true}).addTo(map); bindMarker(marker); }
      marker.bindPopup(lat + ', ' + lon).openPopup();
      setCoords(lat, lon);
    });
  }

  function bindMarker(m) {
    m.on('dragend', function() {
      var ll = m.getLatLng();
      var lat = ll.lat.toFixed(6), lon = ll.lng.toFixed(6);
      m.bindPopup(lat + ', ' + lon).openPopup();
      setCoords(lat, lon);
    });
  }

  function setCoords(lat, lon) {
    document.getElementById('osmme-lat-{/literal}{$element.ID}{literal}').value = lat;
    document.getElementById('osmme-lon-{/literal}{$element.ID}{literal}').value = lon;
  }

  function placeMarker(lat, lon) {
    var _L = window.OSM_L || window.L;
    if (!map || !_L) return;
    map.setView([lat, lon], 13);
    if (marker) { marker.setLatLng([lat, lon]); }
    else { marker = _L.marker([lat, lon], {draggable: true}).addTo(map); bindMarker(marker); }
    marker.bindPopup(lat.toFixed(6) + ', ' + lon.toFixed(6)).openPopup();
    setCoords(lat.toFixed(6), lon.toFixed(6));
  }

  function flashBtn(btn, msg) {
    var orig = btn.textContent;
    btn.textContent = msg;
    btn.style.opacity = '0.7';
    setTimeout(function() { btn.textContent = orig; btn.style.opacity = '1'; }, 1500);
  }

  function initSearch() {
    var inp = document.getElementById('osmme-search-{/literal}{$element.ID}{literal}');
    var btn = document.getElementById('osmme-search-btn-{/literal}{$element.ID}{literal}');
    var res = document.getElementById('osmme-results-{/literal}{$element.ID}{literal}');
    var copyBtn  = document.getElementById('osmme-copy-btn-{/literal}{$element.ID}{literal}');
    var pasteBtn = document.getElementById('osmme-paste-btn-{/literal}{$element.ID}{literal}');
    if (!inp) return;

    // Recherche Nominatim
    function doSearch(q) {
      if (!q) return;
      fetch('https://nominatim.openstreetmap.org/search?format=json&limit=5&q=' + encodeURIComponent(q))
        .then(function(r) { return r.json(); })
        .then(function(data) {
          res.innerHTML = '';
          if (!data.length) {
            res.innerHTML = '<div style="padding:8px;color:#888">Aucun résultat</div>';
            res.style.display = 'block'; return;
          }
          data.forEach(function(item) {
            var d = document.createElement('div');
            d.textContent = item.display_name;
            d.style.cssText = 'padding:7px 10px;cursor:pointer;font-size:0.85rem;border-bottom:1px solid #f0f0f0;';
            d.addEventListener('mouseover', function(){ d.style.background='#f0f4ff'; });
            d.addEventListener('mouseout',  function(){ d.style.background=''; });
            d.addEventListener('click', function() {
              placeMarker(parseFloat(item.lat), parseFloat(item.lon));
              inp.value = item.display_name.split(',')[0];
              res.style.display = 'none';
            });
            res.appendChild(d);
          });
          res.style.display = 'block';
        });
    }

    btn.addEventListener('click', function() { doSearch(inp.value); });
    inp.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') { e.preventDefault(); doSearch(inp.value); }
    });
    document.addEventListener('click', function(e) {
      if (e.target !== inp && e.target !== btn) res.style.display = 'none';
    });

    // Copier coordonnées dans le presse-papier
    copyBtn.addEventListener('click', function() {
      var lat = document.getElementById('osmme-lat-{/literal}{$element.ID}{literal}').value;
      var lon = document.getElementById('osmme-lon-{/literal}{$element.ID}{literal}').value;
      if (!lat || !lon) { flashBtn(copyBtn, '✗ Vide'); return; }
      var text = lat + ',' + lon;
      navigator.clipboard.writeText(text).then(function() {
        flashBtn(copyBtn, '✓ Copié!');
      }).catch(function() {
        // Fallback
        var el = document.createElement('textarea');
        el.value = text;
        document.body.appendChild(el);
        el.select();
        document.execCommand('copy');
        document.body.removeChild(el);
        flashBtn(copyBtn, '✓ Copié!');
      });
    });

    // Coller coordonnées depuis le presse-papier
    pasteBtn.addEventListener('click', function() {
      navigator.clipboard.readText().then(function(text) {
        var parts = text.trim().split(',');
        if (parts.length >= 2) {
          var lat = parseFloat(parts[0].trim());
          var lon = parseFloat(parts[1].trim());
          if (!isNaN(lat) && !isNaN(lon) && lat >= -90 && lat <= 90 && lon >= -180 && lon <= 180) {
            placeMarker(lat, lon);
            flashBtn(pasteBtn, '✓ Collé!');
          } else {
            flashBtn(pasteBtn, '✗ Invalide');
          }
        } else {
          flashBtn(pasteBtn, '✗ Format?');
        }
      }).catch(function() {
        flashBtn(pasteBtn, '✗ Erreur');
      });
    });
  }

  window.addEventListener('load', function() {
    setTimeout(function() { initMap(); initSearch(); }, 100);
  });
})();
{/literal}
</script>
