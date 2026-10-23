<?php
/**
 * OSM Map Plus — Vue 3D (relief) des photos géolocalisées
 * Ouverte dans un nouvel onglet depuis le bouton « 3D » de la carte de la galerie.
 * MapLibre GL JS + relief (AWS Terrain Tiles) + fonds OpenFreeMap / Satellite + noms / OSM France.
 *
 * Paramètres GET : lat, lng, z (zoom Leaflet), base (ofm|sat|osmfr), cat_id (filtre album)
 */
if (!defined('PHPWG_ROOT_PATH'))
    define('PHPWG_ROOT_PATH', '../../');

ob_start();
include_once(PHPWG_ROOT_PATH . 'include/common.inc.php');
ob_end_clean();

// Mêmes règles d'accès que osmme-points.php
$is_guest  = empty($user) || !isset($user['status']) || $user['status'] === 'guest';
$allow_pub = !empty($conf['osm_map_public']);
if ($is_guest && !$allow_pub) {
    redirect(make_index_url());
}

$lat  = isset($_GET['lat']) ? max(-85, min(85, (float)$_GET['lat'])) : 46.5;
$lng  = isset($_GET['lng']) ? max(-180, min(180, (float)$_GET['lng'])) : 2.5;
$z    = isset($_GET['z'])   ? max(1, min(20, (float)$_GET['z'])) : 6;
$base = (isset($_GET['base']) && in_array($_GET['base'], array('ofm', 'sat', 'osmfr'), true)) ? $_GET['base'] : 'ofm';
$cat  = isset($_GET['cat_id']) ? (int)$_GET['cat_id'] : 0;
$pid  = isset($_GET['pid']) ? (int)$_GET['pid'] : 0;
$all  = !empty($_GET['all']);

$root = get_root_url();
$cfg  = array(
    'lat'    => $lat,
    'lng'    => $lng,
    'zoom'   => $z,
    'base'   => $base,
    'pid'    => $pid,
    'solo'   => ($pid > 0 && !$all),
    'root'   => $root,
    'points' => $root . 'plugins/osm_map/osmme-points.php' . ($cat > 0 ? '?cat_id=' . $cat : ''),
    'photo'  => $root . 'plugins/osm_map/osmme-photo.php',
    'home'   => make_index_url(),
);
$title = (isset($conf['gallery_title']) ? $conf['gallery_title'] : 'Piwigo');
header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, max-age=0');
?><!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>Carte 3D &mdash; <?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/maplibre-gl@5.22.0/dist/maplibre-gl.css">
<script src="https://cdn.jsdelivr.net/npm/maplibre-gl@5.22.0/dist/maplibre-gl.js"></script>
<style>
  * { box-sizing: border-box; }
  html, body { margin: 0; height: 100%; font-family: system-ui, sans-serif; }
  #map { position: absolute; inset: 0; background: #05070d; }
  #panel {
    position: absolute; top: 10px; left: 10px; z-index: 5;
    background: rgba(255,255,255,.96); border-radius: 8px; padding: 10px 12px;
    box-shadow: 0 2px 10px rgba(0,0,0,.3); font-size: 13px; color: #222;
    display: flex; flex-direction: column; gap: 8px; max-width: calc(100vw - 80px);
  }
  #panel .row { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
  #panel label { color: #555; white-space: nowrap; }
  #panel select { height: 30px; border: 1px solid #ccc; border-radius: 5px; padding: 0 6px; background: #fff; }
  #panel input[type=range] { width: 120px; }
  #panel a.back { color: #1a73e8; text-decoration: none; font-weight: 600; }
  #panel .count { color: #666; font-size: 12px; }
  #msg { position: absolute; bottom: 30px; left: 50%; transform: translateX(-50%); z-index: 5;
         background: rgba(0,0,0,.75); color: #fff; padding: 8px 14px; border-radius: 6px; font-size: 13px; display: none; }
  .ph-pop { font-size: 13px; max-width: 230px; }
  .ph-pop img { width: 100%; max-height: 130px; object-fit: cover; border-radius: 4px; margin-bottom: 6px; display: block; }
  .ph-pop a.t { font-weight: 700; color: #1a73e8; text-decoration: none; font-size: 14px; }
  .ph-pop .m { color: #666; font-size: 12px; margin-top: 3px; }
</style>
</head>
<body>
<div id="map"></div>
<div id="panel">
  <div class="row">
    <a class="back" href="#" id="close">&#10005; Fermer</a>
    <strong>&#127956;&#65039; Carte 3D</strong> <small style="color:#999">v<?= defined('OSM_MAP_VERSION') ? OSM_MAP_VERSION : '' ?>+globe</small>
    <span class="count" id="count">…</span>
    <?php if ($pid > 0 && !$all): ?><a class="back" href="<?= htmlspecialchars('?' . http_build_query(array_merge($_GET, array('all' => 1)))) ?>" id="allph">Toutes les photos</a><?php endif; ?>
  </div>
  <div class="row">
    <label for="base">Fond</label>
    <select id="base">
      <option value="ofm">OpenFreeMap</option>
      <option value="sat">Satellite + noms</option>
      <option value="osmfr">OpenStreetMap France</option>
    </select>
  </div>
  <div class="row">
    <label for="globe">Globe</label>
    <input type="checkbox" id="globe" title="Afficher la Terre en globe (sans relief)">
  </div>
  <div class="row" id="exagrow">
    <label for="exag">Relief</label>
    <input type="range" id="exag" min="0" max="3" step="0.1" value="1.5">
    <span id="exagv">&times;1.5</span>
  </div>
</div>
<div id="msg"></div>

<script>
(function () {
  'use strict';
  var CFG = <?= json_encode($cfg, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP) ?>;

  var msgEl = document.getElementById('msg');
  function msg(t) { msgEl.textContent = t; msgEl.style.display = 'block'; setTimeout(function () { msgEl.style.display = 'none'; }, 5000); }

  if (typeof maplibregl === 'undefined' || (maplibregl.supported && !maplibregl.supported())) {
    document.getElementById('map').innerHTML = '<p style="padding:30px;font-size:15px">La vue 3D n&rsquo;est pas disponible sur ce navigateur (WebGL requis).</p>';
    return;
  }

  var GLYPHS = 'https://tiles.openfreemap.org/fonts/{fontstack}/{range}.pbf';
  var FONT = ['Noto Sans Regular'];
  var FONTB = ['Noto Sans Bold'];
  var NAME = ['coalesce', ['get', 'name:fr'], ['get', 'name']];
  var HALO = { 'text-color': '#ffffff', 'text-halo-color': 'rgba(0,0,0,0.75)', 'text-halo-width': 1.5 };

  function place(id, classes, minzoom, size, bold) {
    return { id: id, type: 'symbol', source: 'ofm', 'source-layer': 'place', minzoom: minzoom,
      filter: ['match', ['get', 'class'], classes, true, false],
      layout: { 'text-field': NAME, 'text-font': bold ? FONTB : FONT, 'text-size': size, 'text-max-width': 8 },
      paint: HALO };
  }
  function satStyle() {
    return {
      version: 8, glyphs: GLYPHS,
      sources: {
        esri: { type: 'raster', tileSize: 256, maxzoom: 19,
                tiles: ['https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}'],
                attribution: 'Imagery &copy; Esri, Maxar, Earthstar Geographics' },
        ofm:  { type: 'vector', url: 'https://tiles.openfreemap.org/planet',
                attribution: '<a href="https://openfreemap.org" target="_blank" rel="noopener">OpenFreeMap</a> &copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a>' }
      },
      layers: [
        { id: 'esri', type: 'raster', source: 'esri' },
        place('pl-country', ['country'], 1, ['interpolate', ['linear'], ['zoom'], 1, 11, 6, 16], true),
        place('pl-state', ['state', 'province'], 4, 13, false),
        place('pl-city', ['city', 'town'], 5, ['interpolate', ['linear'], ['zoom'], 5, 12, 12, 19], true),
        place('pl-village', ['village', 'suburb', 'quarter', 'neighbourhood'], 10, ['interpolate', ['linear'], ['zoom'], 10, 11, 15, 15], false),
        place('pl-hamlet', ['hamlet', 'isolated_dwelling'], 12, 11, false),
        { id: 'roads', type: 'symbol', source: 'ofm', 'source-layer': 'transportation_name', minzoom: 12,
          layout: { 'text-field': NAME, 'text-font': FONT, 'text-size': 11, 'symbol-placement': 'line' }, paint: HALO },
        { id: 'water', type: 'symbol', source: 'ofm', 'source-layer': 'water_name', minzoom: 3,
          layout: { 'text-field': NAME, 'text-font': FONT, 'text-size': 12 },
          paint: { 'text-color': '#cfe8ff', 'text-halo-color': 'rgba(0,0,0,0.75)', 'text-halo-width': 1.5 } }
      ]
    };
  }
  function osmfrStyle() {
    return {
      version: 8, glyphs: GLYPHS,
      sources: { osmfr: { type: 'raster', tileSize: 256, maxzoom: 20,
        tiles: ['https://a.tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png', 'https://b.tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png'],
        attribution: '&copy; <a href="https://www.openstreetmap.fr/" target="_blank" rel="noopener">OpenStreetMap France</a> | &copy; <a href="https://www.openstreetmap.org/copyright" target="_blank" rel="noopener">OpenStreetMap</a> contributors' } },
      layers: [{ id: 'osmfr', type: 'raster', source: 'osmfr' }]
    };
  }
  function styleFor(b) {
    if (b === 'sat') return satStyle();
    if (b === 'osmfr') return osmfrStyle();
    return 'https://tiles.openfreemap.org/styles/liberty';
  }

  var exag = 1.5;
  var photoFC = { type: 'FeatureCollection', features: [] };
  var curBase = CFG.base;
  var globeOn = false;
  try { globeOn = new URLSearchParams(location.search).get('globe') === '1'; } catch (e) {}

  var map = new maplibregl.Map({
    container: 'map',
    style: styleFor(curBase),
    center: [CFG.lng, CFG.lat],
    zoom: Math.max(2, CFG.zoom - 1),     // zoom Leaflet (tuiles 256px) -> MapLibre (512px)
    pitch: globeOn ? 0 : 60,
    maxPitch: 85,
    attributionControl: { compact: true }
  });
  map.addControl(new maplibregl.NavigationControl({ visualizePitch: true }), 'top-right');
  map.addControl(new maplibregl.FullscreenControl(), 'top-right');
  map.addControl(new maplibregl.ScaleControl({ unit: 'metric' }), 'bottom-left');

  // Relief + photos : à (ré)appliquer après chaque changement de style
  function onStyle() {
    if (!map.getSource('dem')) {
      map.addSource('dem', {
        type: 'raster-dem', encoding: 'terrarium', tileSize: 256, maxzoom: 15,
        tiles: ['https://s3.amazonaws.com/elevation-tiles-prod/terrarium/{z}/{x}/{y}.png'],
        attribution: 'Relief : <a href="https://registry.opendata.aws/terrain-tiles/" target="_blank" rel="noopener">AWS Terrain Tiles</a>'
      });
    }
    // Globe : projection sphérique + atmosphère, sans relief (le relief reste réservé à la carte plane)
    try {
      map.setProjection({ type: globeOn ? 'globe' : 'mercator' });
      if (globeOn) {
        map.setTerrain(null);
        map.setSky({ 'atmosphere-blend': ['interpolate', ['linear'], ['zoom'], 0, 1, 5, 1, 7, 0] });
      } else {
        map.setTerrain({ source: 'dem', exaggeration: exag });
      }
    } catch (e) { console.error(e); }

    // Ombrage du relief (sous les libellés si le style en a)
    if (!globeOn && !map.getLayer('hillshade')) {
      var firstSymbol;
      (map.getStyle().layers || []).some(function (l) { if (l.type === 'symbol') { firstSymbol = l.id; return true; } });
      map.addLayer({ id: 'hillshade', type: 'hillshade', source: 'dem',
        paint: { 'hillshade-exaggeration': curBase === 'sat' ? 0.15 : 0.35,
                 'hillshade-shadow-color': '#3a3a3a' } }, firstSymbol);
    }

    addPhotoLayers();
  }

  function addPhotoLayers() {
    if (map.getSource('photos')) return;
    map.addSource('photos', { type: 'geojson', data: photoFC, cluster: true, clusterRadius: 50, clusterMaxZoom: 14 });
    map.addLayer({ id: 'ph-clusters', type: 'circle', source: 'photos', filter: ['has', 'point_count'],
      paint: {
        'circle-color': ['step', ['get', 'point_count'], '#51a2f0', 50, '#f1a33b', 500, '#e0564a'],
        'circle-radius': ['step', ['get', 'point_count'], 16, 50, 21, 500, 27],
        'circle-stroke-width': 2, 'circle-stroke-color': '#fff'
      } });
    map.addLayer({ id: 'ph-count', type: 'symbol', source: 'photos', filter: ['has', 'point_count'],
      layout: { 'text-field': ['get', 'point_count_abbreviated'], 'text-font': FONTB, 'text-size': 12, 'text-allow-overlap': true },
      paint: { 'text-color': '#fff' } });
    map.addLayer({ id: 'ph-point', type: 'circle', source: 'photos', filter: ['!', ['has', 'point_count']],
      paint: { 'circle-color': '#e8412e', 'circle-radius': 7, 'circle-stroke-width': 2, 'circle-stroke-color': '#fff' } });
    // Photo d'origine (bouton 3D d'une page photo) : mise en évidence
    if (CFG.pid) {
      map.addLayer({ id: 'ph-current', type: 'circle', source: 'photos',
        filter: ['all', ['!', ['has', 'point_count']], ['==', ['get', 'id'], CFG.pid]],
        paint: { 'circle-color': '#1a73e8', 'circle-radius': 11, 'circle-stroke-width': 3, 'circle-stroke-color': '#fff' } });
    }
  }

  map.on('style.load', function () { try { onStyle(); } catch (err) { console.error('OSM Map 3D', err); } });

  // ── Chargement des points ────────────────────────────────────────────────
  fetch(CFG.points, { credentials: 'same-origin' })
    .then(function (r) { if (!r.ok) throw new Error('HTTP ' + r.status); return r.text(); })
    .then(function (t) {
      t = t.trim();
      if (!t || t[0] !== '[') throw new Error('réponse invalide');
      var pts = JSON.parse(t);
      if (CFG.solo) pts = pts.filter(function (p) { return Number(p[0]) === Number(CFG.pid); });
      photoFC = { type: 'FeatureCollection', features: pts.map(function (p) {
        return { type: 'Feature', properties: { id: p[0] }, geometry: { type: 'Point', coordinates: [p[2], p[1]] } };
      }) };
      document.getElementById('count').textContent = pts.length + ' photo' + (pts.length > 1 ? 's' : '');
      var src = map.getSource('photos');
      if (src) src.setData(photoFC);
    })
    .catch(function (e) { console.error('OSM Map 3D', e); msg('Impossible de charger les photos.'); document.getElementById('count').textContent = ''; });

  // ── Interactions ─────────────────────────────────────────────────────────
  map.on('click', 'ph-clusters', function (e) {
    var f = map.queryRenderedFeatures(e.point, { layers: ['ph-clusters'] })[0];
    if (!f) return;
    map.getSource('photos').getClusterExpansionZoom(f.properties.cluster_id).then(function (z) {
      map.easeTo({ center: f.geometry.coordinates, zoom: z + 0.5 });
    });
  });
  function onPointClick(e) {
    var f = e.features[0];
    var id = f.properties.id;
    var coords = f.geometry.coordinates.slice();
    var popup = new maplibregl.Popup({ maxWidth: '260px' }).setLngLat(coords)
      .setHTML('<div class="ph-pop" style="color:#888">Chargement…</div>').addTo(map);
    fetch(CFG.photo + '?id=' + id, { credentials: 'same-origin' })
      .then(function (r) { return r.json(); })
      .then(function (d) {
        // d.name est déjà échappé côté serveur (htmlspecialchars)
        var h = '<div class="ph-pop">';
        if (d.thumbnail_url) h += '<a href="' + d.page_url + '" target="_blank" rel="noopener"><img src="' + d.thumbnail_url + '" alt="" onerror="this.style.display=\'none\'"></a>';
        h += '<a class="t" href="' + d.page_url + '" target="_blank" rel="noopener">' + (d.name || ('Photo #' + id)) + '</a>';
        if (d.album_name) h += '<div class="m">&#128193; ' + String(d.album_name).replace(/</g, '&lt;') + '</div>';
        if (d.date) h += '<div class="m">&#128197; ' + d.date + '</div>';
        popup.setHTML(h + '</div>');
      })
      .catch(function () { popup.setHTML('<div class="ph-pop">Photo #' + id + '</div>'); });
  }
  map.on('click', 'ph-point', onPointClick);
  map.on('click', 'ph-current', onPointClick);
  ['ph-clusters', 'ph-point', 'ph-current'].forEach(function (l) {
    map.on('mouseenter', l, function () { map.getCanvas().style.cursor = 'pointer'; });
    map.on('mouseleave', l, function () { map.getCanvas().style.cursor = ''; });
  });

  // ── Panneau ──────────────────────────────────────────────────────────────
  var baseSel = document.getElementById('base');
  baseSel.value = curBase;
  baseSel.addEventListener('change', function () {
    curBase = this.value;
    map.setStyle(styleFor(curBase));   // 'style.load' ré-applique relief + photos
  });
  var gl = document.getElementById('globe'), exr = document.getElementById('exagrow');
  function applyGlobeUi() { gl.checked = globeOn; exr.style.opacity = globeOn ? 0.4 : 1; document.getElementById('exag').disabled = globeOn; }
  applyGlobeUi();
  gl.addEventListener('change', function () {
    globeOn = this.checked;
    applyGlobeUi();
    map.setStyle(styleFor(curBase));   // 'style.load' ré-applique projection, relief et photos
    map.easeTo(globeOn ? { pitch: 0, zoom: Math.min(map.getZoom(), 2.2), duration: 800 } : { pitch: 60, duration: 800 });
  });
  var ex = document.getElementById('exag'), exv = document.getElementById('exagv');
  ex.addEventListener('input', function () {
    exag = parseFloat(this.value) || 0;
    exv.textContent = '×' + exag.toFixed(1);
    try { if (map.getSource('dem') && map.getTerrain()) map.setTerrain({ source: 'dem', exaggeration: Math.max(exag, 0.01) }); } catch (err) { /* style en cours de chargement : appliqué par onStyle */ }
  });
  document.getElementById('close').addEventListener('click', function (e) {
    e.preventDefault();
    window.close();
    setTimeout(function () { window.location.href = CFG.home; }, 200);   // si l'onglet ne peut pas se fermer
  });
})();
</script>
</body>
</html>
