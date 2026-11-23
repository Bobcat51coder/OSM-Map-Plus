<div class="osmme-g" id="osmme-g">
  <p style="margin:0 0 8px;"><strong>&#127760; OSM Map Plus &mdash; G&eacute;otaggage par lot</strong></p>
  <div style="display:flex;gap:6px;margin-bottom:6px;flex-wrap:wrap;position:relative;">
    <input type="text" id="osmme-g-search" placeholder="&#128269; Rechercher un lieu..." autocomplete="off"
      style="flex:1;min-width:200px;padding:6px 8px;border:1px solid #ccc;border-radius:4px;font-size:0.9rem;">
    <button type="button" id="osmme-g-search-btn" title="Rechercher"
      style="padding:6px 12px;background:#1a73e8;color:#fff;border:none;border-radius:4px;cursor:pointer;">&#128269;</button>
    <select id="osmme-g-places" title="Lieux favoris"
      style="padding:6px;border:1px solid #ccc;border-radius:4px;font-size:0.85rem;max-width:200px;"><option value="">&#11088; Lieux favoris</option></select>
    <select id="osmme-g-basemap" title="Fond de carte"
      style="padding:6px;border:1px solid #ccc;border-radius:4px;font-size:0.85rem;"></select>
    <button type="button" id="osmme-g-fav" title="Enregistrer la position du marqueur comme lieu favori"
      style="padding:6px 10px;border:1px solid #ccc;border-radius:4px;background:#fff;cursor:pointer;">&#11088; D&eacute;finir comme lieu favori</button>
    <button type="button" id="osmme-g-3d" title="Ouvrir la carte en relief 3D dans un nouvel onglet"
      style="padding:6px 10px;border:1px solid #ccc;border-radius:4px;background:#fff;cursor:pointer;">&#127956;&#65039; 3D</button>
    <button type="button" id="osmme-g-copy" title="Copier les coordonn&eacute;es"
      style="padding:6px 10px;background:#34a853;color:#fff;border:none;border-radius:4px;cursor:pointer;">&#128203; Copier</button>
    <button type="button" id="osmme-g-paste" title="Coller les coordonn&eacute;es"
      style="padding:6px 10px;background:#ff6d00;color:#fff;border:none;border-radius:4px;cursor:pointer;">&#128203; Coller</button>
    <div id="osmme-g-results"
      style="display:none;position:absolute;top:36px;left:0;right:80px;background:#fff;border:1px solid #ccc;border-radius:4px;z-index:9999;box-shadow:0 2px 8px rgba(0,0,0,.15);max-height:200px;overflow-y:auto;"></div>
  </div>

  <div style="display:flex;gap:14px;align-items:center;flex-wrap:wrap;margin:6px 0;">
    <label>Latitude <input type="text" size="11" id="osmme-g-lat" name="osmme_g_lat" value="" placeholder="ex: 48.8566"></label>
    <label>Longitude <input type="text" size="11" id="osmme-g-lon" name="osmme_g_lon" value="" placeholder="ex: 2.3522"></label>
    <label style="color:#b3261e;"><input type="checkbox" id="osmme-g-clear" name="osmme_g_clear" value="1"> Effacer le GPS des photos s&eacute;lectionn&eacute;es</label>
  </div>

  <div id="osmme-g-map" style="width:calc(100% - 60px);min-width:480px;max-width:1100px;aspect-ratio:16/9;height:auto;min-height:{$OSMME_G_HEIGHT}px;max-height:640px;margin:5px 30px;border:1px solid #ccc;border-radius:4px;"></div>
  <p style="font-size:0.82em;color:#666;margin:4px 0 0;">
    &#128161; Cliquez sur la carte, d&eacute;placez le marqueur ou cherchez un lieu, puis cliquez sur &laquo;&nbsp;Appliquer l'action&nbsp;&raquo; : la position sera enregistr&eacute;e pour <strong>toutes</strong> les photos s&eacute;lectionn&eacute;es.
  </p>
</div>

{literal}
<script>
(function() {
  if (window.__osmmeGlobalInit) return;
  window.__osmmeGlobalInit = true;
  var map = null, marker = null, ACTION = 'osmmap_plus';
  var $ = function(id) { return document.getElementById(id); };

  function L_() { return window.OSM_L || window.L; }

  function setCoords(lat, lon) {
    $('osmme-g-lat').value = lat;
    $('osmme-g-lon').value = lon;
    $('osmme-g-clear').checked = false;
  }

  function bindMarker(m) {
    m.on('dragend', function() {
      var ll = m.getLatLng(), lat = ll.lat.toFixed(6), lon = ll.lng.toFixed(6);
      m.bindPopup(lat + ', ' + lon).openPopup();
      setCoords(lat, lon);
    });
  }

  function placeMarker(lat, lon, zoom) {
    var _L = L_();
    if (!map || !_L) return;
    map.setView([lat, lon], zoom || 13);
    if (marker) { marker.setLatLng([lat, lon]); }
    else { marker = _L.marker([lat, lon], {draggable: true}).addTo(map); bindMarker(marker); }
    marker.bindPopup(lat.toFixed(6) + ', ' + lon.toFixed(6)).openPopup();
    setCoords(lat.toFixed(6), lon.toFixed(6));
  }

  function flashBtn(btn, msg) {
    var orig = btn.textContent;
    btn.textContent = msg; btn.style.opacity = '0.7';
    setTimeout(function() { btn.textContent = orig; btn.style.opacity = '1'; }, 1500);
  }

  function initMap() {
    var _L = L_();
    if (!_L) { setTimeout(initMap, 200); return; }
    var div = $('osmme-g-map');
    if (!div || div.getAttribute('data-osm-init')) return;
    div.setAttribute('data-osm-init', '1');
    map = _L.map('osmme-g-map', {center: [46.5, 2.5], zoom: 5, fadeAnimation: false, zoomAnimation: false, markerZoomAnimation: false});

    if (window.OSMBasemaps) {
      OSMBasemaps.attach(map, {
        key: window.OSM_BATCH_TILE_KEY,
        select: $('osmme-g-basemap'),
        button: $('osmme-g-3d'),
        root: window.OSM_BATCH_ROOT,
        pid: 0,
        allowVector: true,
        storageKey: 'osmme_basemap_global'
      });
    } else {
      $('osmme-g-3d').style.display = 'none';
      $('osmme-g-basemap').style.display = 'none';
      _L.tileLayer(window.OSM_BATCH_TILE_URL || 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
        {attribution: window.OSM_BATCH_TILE_ATTR || '&copy; OpenStreetMap contributors', maxZoom: window.OSM_BATCH_TILE_MAXZOOM || 19}).addTo(map);
    }

    map.on('click', function(e) {
      var lat = e.latlng.lat.toFixed(6), lon = e.latlng.lng.toFixed(6);
      if (marker) { marker.setLatLng(e.latlng); }
      else { marker = _L.marker(e.latlng, {draggable: true}).addTo(map); bindMarker(marker); }
      marker.bindPopup(lat + ', ' + lon).openPopup();
      setCoords(lat, lon);
    });

    // Champs saisis à la main -> marqueur
    function fromFields() {
      var la = parseFloat($('osmme-g-lat').value), lo = parseFloat($('osmme-g-lon').value);
      if (!isNaN(la) && !isNaN(lo) && la >= -90 && la <= 90 && lo >= -180 && lo <= 180) {
        placeMarker(la, lo, map.getZoom() > 5 ? map.getZoom() : 13);
      }
    }
    $('osmme-g-lat').addEventListener('change', fromFields);
    $('osmme-g-lon').addEventListener('change', fromFields);

    [150, 500, 1200].forEach(function(t) { setTimeout(function() { map.invalidateSize(); }, t); });
    if (window.ResizeObserver) { new ResizeObserver(function() { map.invalidateSize(); }).observe(div); }
  }

  function initPlaces() {
    var sel = $('osmme-g-places');
    window.OSM_PLACES = window.OSM_PLACES || [];
    window.OSM_PLACES.forEach(function(p, i) { var o = document.createElement('option'); o.value = i; o.textContent = p.name; sel.appendChild(o); });
    sel.addEventListener('change', function() {
      var p = window.OSM_PLACES[sel.value];
      if (p) placeMarker(parseFloat(p.lat), parseFloat(p.lon));
    });
  }

  function initSearch() {
    var inp = $('osmme-g-search'), btn = $('osmme-g-search-btn'), res = $('osmme-g-results');
    function doSearch(q) {
      if (!q) return;
      fetch('https://nominatim.openstreetmap.org/search?format=json&limit=6&q=' + encodeURIComponent(q))
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
            d.addEventListener('mouseover', function() { d.style.background = '#f0f4ff'; });
            d.addEventListener('mouseout',  function() { d.style.background = ''; });
            d.addEventListener('click', function() {
              placeMarker(parseFloat(item.lat), parseFloat(item.lon));
              inp.value = item.display_name.split(',')[0];
              res.style.display = 'none';
            });
            res.appendChild(d);
          });
          res.style.display = 'block';
        })
        .catch(function() {
          res.innerHTML = '<div style="padding:8px;color:#b3261e">Recherche indisponible</div>';
          res.style.display = 'block';
        });
    }
    btn.addEventListener('click', function() { doSearch(inp.value); });
    // Entrée : lancer la recherche sans valider le formulaire de la gestion par lot
    inp.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') { e.preventDefault(); e.stopPropagation(); doSearch(inp.value); }
    });
    document.addEventListener('click', function(e) {
      if (e.target !== inp && e.target !== btn) res.style.display = 'none';
    });

    var copyBtn = $('osmme-g-copy'), pasteBtn = $('osmme-g-paste');
    copyBtn.addEventListener('click', function() {
      var lat = $('osmme-g-lat').value, lon = $('osmme-g-lon').value;
      if (!lat || !lon) { flashBtn(copyBtn, '✗ Vide'); return; }
      var text = lat + ',' + lon;
      navigator.clipboard.writeText(text).then(function() { flashBtn(copyBtn, '✓ Copié !'); }).catch(function() {
        var el = document.createElement('textarea');
        el.value = text; document.body.appendChild(el); el.select();
        document.execCommand('copy'); document.body.removeChild(el);
        flashBtn(copyBtn, '✓ Copié !');
      });
    });
    pasteBtn.addEventListener('click', function() {
      navigator.clipboard.readText().then(function(text) {
        var parts = text.trim().split(',');
        if (parts.length >= 2) {
          var lat = parseFloat(parts[0]), lon = parseFloat(parts[1]);
          if (!isNaN(lat) && !isNaN(lon) && lat >= -90 && lat <= 90 && lon >= -180 && lon <= 180) {
            placeMarker(lat, lon); flashBtn(pasteBtn, '✓ Collé !');
          } else { flashBtn(pasteBtn, '✗ Invalide'); }
        } else { flashBtn(pasteBtn, '✗ Format ?'); }
      }).catch(function() { flashBtn(pasteBtn, '✗ Erreur'); });
    });
  }

  var favToken = {/literal}{$OSMME_G_TOKEN|@json_encode}{literal};
  function initFav() {
    var btn = $('osmme-g-fav'), sel = $('osmme-g-places');
    btn.addEventListener('click', function() {
      var la = $('osmme-g-lat').value, lo = $('osmme-g-lon').value;
      if (!la || !lo) { alert('Placez d\u2019abord un marqueur sur la carte.'); return; }
      var name = prompt('Nom du lieu favori :', $('osmme-g-search').value || '');
      if (!name || !name.trim()) return;
      var ep = window.OSM_BATCH_ROOT + 'plugins/osm_map/osmme-places.php';
      var fd = new FormData();
      fd.append('add', '1'); fd.append('pwg_token', favToken); fd.append('name', name); fd.append('lat', la); fd.append('lon', lo);
      fetch(ep, {method: 'POST', body: fd, credentials: 'same-origin'}).then(function(r) { return r.json(); }).then(function(d) {
        if (!d.ok) { alert('Lieu invalide.'); return; }
        return fetch(ep + '?list=1', {credentials: 'same-origin'}).then(function(r) { return r.json(); }).then(function(l) {
          var list = l.places || [];
          window.OSM_PLACES.length = 0;
          list.forEach(function(p) { window.OSM_PLACES.push(p); });
          while (sel.options.length > 1) sel.remove(1);
          list.forEach(function(p, i) { var o = document.createElement('option'); o.value = i; o.textContent = p.name; sel.appendChild(o); });
          for (var i = 0; i < list.length; i++) { if (list[i].id === d.id) { sel.value = i; break; } }
          flashBtn(btn, '\u2713 Enregistr\u00e9 !');
        });
      }).catch(function() { alert('Erreur r\u00e9seau.'); });
    });
  }

  var started = false;
  function start() {
    if (started) return;
    started = true;
    (window.osmmeLeaflet || function(n, c) { c(); })(false, function() {
      setTimeout(function() { initMap(); initPlaces(); initSearch(); initFav(); }, 100);
    });
  }
  // La carte est dans un bloc masqué tant que l'action n'est pas choisie : on l'initialise à la sélection
  function onSelect() {
    var sel = document.querySelector('select[name="selectAction"]');
    if (!sel || sel.value !== ACTION) return;
    start();
    [50, 300, 800].forEach(function(t) { setTimeout(function() { if (map) map.invalidateSize(); }, t); });
  }
  function bind() {
    var sel = document.querySelector('select[name="selectAction"]');
    if (!sel) return;
    sel.addEventListener('change', onSelect);
    onSelect();
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', bind); } else { bind(); }
})();
</script>
{/literal}
