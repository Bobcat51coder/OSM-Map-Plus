<div class="full-line-box">
  <strong>&#128506; OSM Map Plus &mdash; G&eacute;otaggage</strong>
</div>
<div class="full-line-box osmme-exif-alert" id="osmme-exif-alert-{$element.ID}" data-id="{$element.ID}" style="display:none;"></div>

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
    <select id="osmme-places-{$element.ID}" title="Lieux favoris"
      style="padding:5px 6px;border:1px solid #ccc;border-radius:4px;font-size:0.85rem;max-width:180px;"><option value="">&#11088; Lieux favoris</option></select>
    <select id="osmme-basemap-{$element.ID}" title="Fond de carte"
      style="padding:5px 6px;border:1px solid #ccc;border-radius:4px;font-size:0.85rem;"></select>
    <button type="button" id="osmme-3d-btn-{$element.ID}" title="Ouvrir la carte en relief 3D dans un nouvel onglet"
      style="padding:5px 10px;border:1px solid #ccc;border-radius:4px;background:#fff;cursor:pointer;">&#127956;&#65039; 3D</button>
    <button type="button" id="osmme-copy-btn-{$element.ID}" title="Copier les coordonn&eacute;es"
      style="padding:5px 10px;background:#34a853;color:#fff;border:none;border-radius:4px;cursor:pointer;">&#128203; Copier</button>
    <button type="button" id="osmme-paste-btn-{$element.ID}" title="Coller les coordonn&eacute;es"
      style="padding:5px 10px;background:#ff6d00;color:#fff;border:none;border-radius:4px;cursor:pointer;">&#128203; Coller</button>
    <div id="osmme-results-{$element.ID}"
      style="display:none;position:absolute;top:34px;left:0;right:80px;background:#fff;border:1px solid #ccc;border-radius:4px;z-index:9999;box-shadow:0 2px 8px rgba(0,0,0,.15);max-height:180px;overflow-y:auto;"></div>
  </div>
  <div id="osm-unit-map-{$element.ID}" style="height:{$OSM_UNIT_HEIGHT}px;width:calc(100% - 60px);margin:5px 30px;border:1px solid #ccc;border-radius:4px;"></div>
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
    map = _L.map(divId, {center: center, zoom: zoom, fadeAnimation: false, zoomAnimation: false, markerZoomAnimation: false});
    if (window.OSMBasemaps) {
      // Même liste de fonds que la galerie. Les fonds vectoriels (WebGL) sont limités aux pages de peu de photos
      // (les navigateurs plafonnent le nombre de contextes WebGL simultanés).
      var nMaps = document.querySelectorAll('[id^="osm-unit-map-"]').length;
      OSMBasemaps.attach(map, {
        key: window.OSM_BATCH_TILE_KEY,
        select: document.getElementById('osmme-basemap-{/literal}{$element.ID}{literal}'),
        button: document.getElementById('osmme-3d-btn-{/literal}{$element.ID}{literal}'),
        root: window.OSM_BATCH_ROOT,
        pid: imageID,
        allowVector: nMaps <= 8,
        storageKey: 'osmme_basemap_geotag'
      });
      hardenMap(_L, map, null, div);
    } else {
      var b3x = document.getElementById('osmme-3d-btn-{/literal}{$element.ID}{literal}');
      if (b3x) b3x.style.display = 'none';
      var tl = _L.tileLayer(tileUrl, {attribution: tileAttr, maxZoom: tileZoom}).addTo(map);
      hardenMap(_L, map, tl, div);
    }
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

  // Carte blanche / qui disparaît : recalcul de taille (conteneur masqué ou redimensionné par Piwigo)
  // + bascule automatique sur OpenStreetMap si le fond choisi ne répond pas (clé Carto absente/invalide…)
  function hardenMap(_L, m, layer, el) {
    var errs = 0, swapped = false;
    if (layer) layer.on('tileerror', function() {
      if (swapped || ++errs < 4) return;
      swapped = true;
      m.removeLayer(layer);
      _L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png',
        {maxZoom: 19, attribution: '&copy; OpenStreetMap contributors'}).addTo(m);
    });
    [150, 500, 1200, 3000].forEach(function(t) { setTimeout(function() { m.invalidateSize(); }, t); });
    if (window.ResizeObserver) { new ResizeObserver(function() { m.invalidateSize(); }).observe(el); }
    if (window.IntersectionObserver) {
      new IntersectionObserver(function(en) { if (en[0].isIntersecting) m.invalidateSize(); }).observe(el);
    }
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

  window['osmmePlace_' + imageID] = function(lat, lon) { placeMarker(lat, lon); };

  (function() {
    var sel = document.getElementById('osmme-places-' + imageID), list = window.OSM_PLACES || [];
    if (!sel) return;
    if (!list.length) { sel.style.display = 'none'; return; }
    list.forEach(function(p, i) { var o = document.createElement('option'); o.value = i; o.textContent = p.name; sel.appendChild(o); });
    sel.addEventListener('change', function() {
      var p = list[sel.value];
      if (p) { placeMarker(p.lat, p.lon); }
    });
  })();

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

  (window.osmmeLeaflet || function(n, c) { c(); })(false, function() {
    setTimeout(function() { initMap(); initSearch(); }, 100);
  });
})();
{/literal}
</script>

{literal}
<script>
(function() {
  if (window.__osmmeExifInit) return;
  window.__osmmeExifInit = true;
  function fmt(e) { return e.lat.toFixed(6) + ', ' + e.lon.toFixed(6); }
  function dist(m) { return m >= 1000 ? (m / 1000).toFixed(1).replace('.', ',') + ' km' : m + ' m'; }
  function run() {
    var boxes = document.querySelectorAll('.osmme-exif-alert');
    if (!boxes.length || !window.OSM_BATCH_ROOT) return;
    var ids = [];
    boxes.forEach(function(b) { ids.push(b.getAttribute('data-id')); });
    var url = window.OSM_BATCH_ROOT + 'plugins/osm_map/osmme-exifcheck.php?ids=' + ids.join(',');
    fetch(url, {credentials: 'same-origin'}).then(function(r) { return r.json(); }).then(function(data) {
      (data.items || []).forEach(function(it) {
        var box = document.getElementById('osmme-exif-alert-' + it.id);
        if (!box || it.validated || (it.state !== 'diff' && it.state !== 'dbempty')) return;
        var msg = it.state === 'diff'
          ? '&#9888;&#65039; <strong>La position en base diff\u00e8re du GPS EXIF du fichier</strong> (\u00e9cart ' + dist(it.dist) + ') \u2014 fichier : <code>' + fmt(it.exif) + '</code>'
          : '&#9888;&#65039; <strong>Le fichier contient un GPS (EXIF) mais la base n\'en a pas</strong> \u2014 fichier : <code>' + fmt(it.exif) + '</code>';
        box.style.cssText = 'display:block;background:#fff4e5;border:1px solid #f5c27a;border-radius:4px;padding:6px 10px;font-size:0.85rem;';
        box.innerHTML = msg + ' <button type="button" style="margin-left:8px;padding:3px 10px;border:1px solid #1a73e8;background:#fff;color:#1a73e8;border-radius:4px;cursor:pointer;">Utiliser la position du fichier</button>';
        box.querySelector('button').addEventListener('click', function() {
          var f = window['osmmePlace_' + it.id];
          if (f) { f(it.exif.lat, it.exif.lon); }
          else {
            document.getElementById('osmme-lat-' + it.id).value = it.exif.lat.toFixed(6);
            document.getElementById('osmme-lon-' + it.id).value = it.exif.lon.toFixed(6);
          }
          box.style.background = '#e6f4ea'; box.style.borderColor = '#a8d5b5';
          box.innerHTML = '&#10003; Position du fichier appliqu\u00e9e aux champs \u2014 pensez \u00e0 <strong>valider la page</strong> pour enregistrer.';
        });
      });
    }).catch(function() {});
  }
  if (document.readyState === 'loading') { document.addEventListener('DOMContentLoaded', run); } else { setTimeout(run, 0); }
})();
</script>
{/literal}
