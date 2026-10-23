<?php
/*
 * This file is part of OSM Map Plus.
 *
 * OSM Map Plus is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * OSM Map Plus is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with OSM Map Plus.  If not, see <https://www.gnu.org/licenses/>.
 */
// OSM Map Enhanced — Édition photo individuelle
// Bobcat-Fr / Claude (Anthropic)
if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');
check_status(ACCESS_ADMINISTRATOR);

if (!isset($_GET['image_id'])) die('image_id manquant');
$image_id = (int)$_GET['image_id'];

// ── Sauvegarde ────────────────────────────────────────────────────────────
$msg_info  = '';
$msg_error = '';
if (isset($_POST['osm_save'])) {
    check_pwg_token();
    $lat = trim($_POST['osm_lat']);
    $lon = trim($_POST['osm_lon']);
    if ($lat !== '' && $lon !== '') {
        if (is_numeric($lat) && is_numeric($lon)
            && (float)$lat >= -90  && (float)$lat <= 90
            && (float)$lon >= -180 && (float)$lon <= 180) {
            pwg_query('UPDATE '.IMAGES_TABLE.' SET latitude='.(float)$lat.', longitude='.(float)$lon.' WHERE id='.$image_id);
            $msg_info = '✓ Coordonnées enregistrées : '.$lat.', '.$lon;
        } else {
            $msg_error = 'Valeurs invalides (lat: -90→90, lon: -180→180)';
        }
    } else {
        pwg_query('UPDATE '.IMAGES_TABLE.' SET latitude=NULL, longitude=NULL WHERE id='.$image_id);
        $msg_info = '✓ Coordonnées GPS supprimées.';
    }
}

// ── Lecture photo ─────────────────────────────────────────────────────────
$res   = pwg_query('SELECT id, name, file, latitude, longitude FROM '.IMAGES_TABLE.' WHERE id='.$image_id);
$photo = pwg_db_fetch_assoc($res);
if (!$photo) die('Photo introuvable');

$lat  = ($photo['latitude']  !== null && $photo['latitude']  !== '') ? (float)$photo['latitude']  : '';
$lon  = ($photo['longitude'] !== null && $photo['longitude'] !== '') ? (float)$photo['longitude'] : '';
$name = $photo['name'] ? $photo['name'] : pathinfo($photo['file'], PATHINFO_FILENAME);

// ── Tabsheet — reproduit exactement le pattern de admin_photo.php d'OSM Geotag ──
// L'URL de base doit être page=photo-XXXX pour que les onglets natifs fonctionnent
// Contexte : on arrive via page=plugin&section=...
// Piwigo ne sait pas qu'on est sur une page photo, donc les URLs des onglets
// natifs sont construites avec une base vide → on les corrige manuellement
global $page, $template;
$page['photo_id'] = $image_id;
$_GET['page']     = 'photo-' . $image_id; // pour tabsheet_before_select

include_once(PHPWG_ROOT_PATH.'admin/include/tabsheet.class.php');
$tabsheet = new tabsheet();
$tabsheet->set_id('photo');
$tabsheet->select('osmme_enhanced');
$tabsheet->assign();

// Corriger les URLs des onglets natifs qui ont une base vide
// Piwigo génère : '-properties', '-coi' → on préfixe avec la vraie base
$base = get_root_url() . 'admin.php?page=photo-' . $image_id;
$sheets = $template->get_template_vars('tabsheet');
if (!empty($sheets)) {
    foreach ($sheets as &$sheet) {
        if (isset($sheet['U_LINK']) && substr($sheet['U_LINK'], 0, 1) === '-') {
            $sheet['U_LINK'] = $base . $sheet['U_LINK'];
        }
    }
    unset($sheet);
    $template->assign('tabsheet', $sheets);
}

// ── Assets Leaflet ────────────────────────────────────────────────────────
$root = get_root_url();
$plg  = $root . 'plugins/osm_map/';
global $template;
$template->append('head_elements',
    '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />' . "\n" .
    '<script>window.OSM_CARTO_API_KEY=' . json_encode(osmme_resolve_carto_key()) . ';</script>' .
    '<script src="' . get_root_url() . 'plugins/osm_map/js/basemaps.js?v=' . OSM_MAP_ASSET_V . '"></script>' .
    '<script>' . osmme_loader_js() . '</script>'
);

// ── HTML ──────────────────────────────────────────────────────────────────
$init_lat = $lat !== '' ? $lat : 'null';
$init_lon = $lon !== '' ? $lon : 'null';
$init_lat_js = $lat !== '' ? $lat : 46.5;
$init_lon_js = $lon !== '' ? $lon : 2.5;
$init_zoom   = $lat !== '' ? 13 : 5;

global $conf;
// Réglages de l'onglet d'édition (relus en base pour avoir la valeur la plus récente)
$res_tile = pwg_query("SELECT param, value FROM " . CONFIG_TABLE . " WHERE param IN ('osm_map_tile_edit', 'osm_map_edit_height')");
$cfg_edit = array();
while ($r_cfg = pwg_db_fetch_assoc($res_tile)) { $cfg_edit[$r_cfg['param']] = $r_cfg['value']; }
$tile_geotag = isset($cfg_edit['osm_map_tile_edit']) ? $cfg_edit['osm_map_tile_edit'] : (isset($conf['osm_map_tile_edit']) ? $conf['osm_map_tile_edit'] : 'carto_voyager');
$edit_height = isset($cfg_edit['osm_map_edit_height']) ? (int)$cfg_edit['osm_map_edit_height'] : (isset($conf['osm_map_edit_height']) ? (int)$conf['osm_map_edit_height'] : 400);
$edit_height = max(200, min(1000, $edit_height));

$tiles = array(
    'carto_voyager' => array(osmme_carto_tile_url('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}.png'), '&copy; OpenStreetMap &copy; CARTO', 19),
    'osm_standard'  => array('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', '&copy; OpenStreetMap contributors', 19),
    'esri_satellite'=> array('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', 'Tiles &copy; Esri', 18),
    'opentopo'      => array('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', '&copy; OpenStreetMap &copy; OpenTopoMap', 17),
    'osm_france'    => array('https://{s}.tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png', '&copy; OpenStreetMap France | &copy; OpenStreetMap contributors', 20),
);
$t = isset($tiles[$tile_geotag]) ? $tiles[$tile_geotag] : $tiles['carto_voyager'];

$html = '
<style>
#osm-photo-edit { padding:20px; max-width:900px; }
#osm-photo-edit h3 { margin:0 0 16px; font-size:1.1rem; }
#osm-geocoder-wrap { display:flex; gap:8px; margin-bottom:12px; }
#osm-geocoder-input { flex:1; padding:7px 10px; border:1px solid #ccc; border-radius:4px; font-size:0.9rem; }
#osm-geocoder-btn { padding:7px 14px; background:#1a73e8; color:#fff; border:none; border-radius:4px; cursor:pointer; }
#osm-geocoder-results { background:#fff; border:1px solid #ccc; border-radius:4px; margin-top:2px; position:absolute; z-index:9999; width:400px; box-shadow:0 2px 8px rgba(0,0,0,.15); }
#osm-geocoder-results div { padding:8px 12px; cursor:pointer; font-size:0.88rem; border-bottom:1px solid #f0f0f0; }
#osm-geocoder-results div:hover { background:#f0f4ff; }
.leaflet-container .leaflet-tile{opacity:1!important;visibility:visible!important;max-width:none!important;max-height:none!important;}.leaflet-tile-pane{opacity:1!important;visibility:visible!important;}
#osm-edit-map { height:' . $edit_height . 'px; border:1px solid #d0d0d0; border-radius:4px; margin:0 30px 12px !important; width:auto !important; position:relative; }
.osm-coords-row { display:flex; gap:20px; margin-bottom:14px; align-items:center; flex-wrap:wrap; }
.osm-coords-row label { display:flex; align-items:center; gap:8px; font-size:0.9rem; }
.osm-coords-row input { width:140px; padding:5px 8px; border:1px solid #ccc; border-radius:4px; font-family:monospace; }
.osm-hint { font-size:0.82em; color:#666; background:#fffbe6; border:1px solid #ffe082; border-radius:3px; padding:6px 10px; margin-bottom:14px; }
#osm-save-btn { padding:8px 22px; background:#1a73e8; color:#fff; border:none; border-radius:4px; cursor:pointer; font-weight:600; font-size:0.9rem; }
#osm-clear-btn { padding:8px 16px; background:#fff; color:#d93025; border:1px solid #d93025; border-radius:4px; cursor:pointer; font-size:0.9rem; margin-left:8px; }
.osm-msg-info  { color:#1e8e3e; background:#e6f4ea; border:1px solid #a8d5b5; border-radius:3px; padding:8px 12px; margin-bottom:12px; }
.osm-msg-error { color:#d93025; background:#fce8e6; border:1px solid #f5c6c2; border-radius:3px; padding:8px 12px; margin-bottom:12px; }
</style>

<div id="osm-photo-edit">
  <h3>&#127760; Géotaggage — ' . htmlspecialchars($name) . '</h3>';

if ($msg_info)  $html .= '<div class="osm-msg-info">'  . htmlspecialchars($msg_info)  . '</div>';
if ($msg_error) $html .= '<div class="osm-msg-error">' . htmlspecialchars($msg_error) . '</div>';

$html .= '
  <!-- Géocodeur -->
  <div id="osm-geocoder-wrap" style="position:relative;">
    <input type="text" id="osm-geocoder-input" placeholder="Rechercher un lieu et appuyer sur Entrée ou cliquer 🔍" autocomplete="off">
    <button type="button" id="osm-geocoder-btn">&#128269;</button>
    <select id="osm-basemap-edit" title="Fond de carte" style="padding:5px 8px;border:1px solid #ccc;border-radius:4px;"></select>
    <button type="button" id="osm-edit-3d" title="Ouvrir la carte en relief 3D dans un nouvel onglet" style="padding:7px 12px;border:1px solid #ccc;border-radius:4px;background:#fff;cursor:pointer;">&#127956;&#65039; 3D</button>
    <div id="osm-geocoder-results" style="display:none;"></div>
  </div>

  <div class="osm-hint">&#128161; Cliquez sur la carte ou déplacez le marqueur pour modifier la position, puis cliquez <strong>Enregistrer</strong>.</div>

  <!-- Carte -->
  <div id="osm-edit-map"></div>

  <!-- Formulaire -->
  <form method="post" action="">
    <input type="hidden" name="pwg_token" value="' . get_pwg_token() . '">
    <div class="osm-coords-row">
      <label>Latitude&nbsp;: <input type="text" id="osm-lat" name="osm_lat" value="' . $lat . '" placeholder="ex: 48.8566"></label>
      <label>Longitude&nbsp;: <input type="text" id="osm-lon" name="osm_lon" value="' . $lon . '" placeholder="ex: 2.3522"></label>
    </div>
    <button type="submit" name="osm_save" id="osm-save-btn">&#10003; Enregistrer</button>
    <button type="button" id="osm-clear-btn">&#10005; Effacer GPS</button>
  </form>
</div>

<script>
var OSM_EDIT_LAT  = ' . $init_lat . ';
var OSM_EDIT_LON  = ' . $init_lon . ';
var OSM_EDIT_ZOOM = ' . $init_zoom . ';
var OSM_EDIT_ROOT = ' . json_encode($root) . ';
var OSM_EDIT_ID = ' . (int)$image_id . ';
var OSM_EDIT_TILE_KEY = ' . json_encode($tile_geotag) . ';
var OSM_EDIT_TILE = {url: "' . $t[0] . '", attr: "' . $t[1] . '", maxZoom: ' . $t[2] . '};
</script>
<script>
(function() {
  var map, marker;

  function initMap() {
    var _L = window.L;
    if (!_L) { setTimeout(initMap, 200); return; }
    // Fixer le chemin des icones Leaflet
    delete _L.Icon.Default.prototype._getIconUrl;
    _L.Icon.Default.mergeOptions({
      iconUrl: "https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png",
      iconRetinaUrl: "https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png",
      shadowUrl: "https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png"
    });
    var center = (OSM_EDIT_LAT && OSM_EDIT_LON) ? [OSM_EDIT_LAT, OSM_EDIT_LON] : [46.5, 2.5];
    // Marges latérales de 30 px (passage du curseur sans toucher la carte) — imposées aussi en JS, au cas où le thème écrase le CSS
    var mapEl = document.getElementById("osm-edit-map");
    mapEl.style.setProperty("margin", "0 30px 12px", "important");
    mapEl.style.setProperty("width", "auto", "important");
    mapEl.style.setProperty("box-sizing", "border-box", "important");
    map = _L.map("osm-edit-map", {center: center, zoom: OSM_EDIT_ZOOM, fadeAnimation: false, zoomAnimation: false, markerZoomAnimation: false});
    if (window.OSMBasemaps) {
      OSMBasemaps.attach(map, {key: OSM_EDIT_TILE_KEY, select: document.getElementById("osm-basemap-edit"), button: document.getElementById("osm-edit-3d"), root: OSM_EDIT_ROOT, pid: OSM_EDIT_ID, storageKey: "osmme_basemap_edit"});
    } else {
      _L.tileLayer(OSM_EDIT_TILE.url, {attribution: OSM_EDIT_TILE.attr, maxZoom: OSM_EDIT_TILE.maxZoom}).addTo(map);
    }
    [150, 500, 1200, 3000].forEach(function(t) { setTimeout(function() { map.invalidateSize(); }, t); });
    if (window.ResizeObserver) { new ResizeObserver(function() { map.invalidateSize(); }).observe(document.getElementById("osm-edit-map")); }

    if (OSM_EDIT_LAT && OSM_EDIT_LON) {
      marker = _L.marker([OSM_EDIT_LAT, OSM_EDIT_LON], {draggable: true}).addTo(map);
      bindMarker();
    }

    map.on("click", function(e) {
      if (marker) { marker.setLatLng(e.latlng); }
      else { marker = _L.marker(e.latlng, {draggable: true}).addTo(map); bindMarker(); }
      setCoords(e.latlng.lat.toFixed(6), e.latlng.lng.toFixed(6));
    });
  }

  function bindMarker() {
    marker.on("dragend", function() {
      var ll = marker.getLatLng();
      setCoords(ll.lat.toFixed(6), ll.lng.toFixed(6));
    });
  }

  function setCoords(lat, lon) {
    document.getElementById("osm-lat").value = lat;
    document.getElementById("osm-lon").value = lon;
  }

  // Géocodeur Nominatim
  var geocInput = document.getElementById("osm-geocoder-input");
  var geocResults = document.getElementById("osm-geocoder-results");
  var geocTimer;

  function doSearch(q) {
    if (!q) return;
    fetch("https://nominatim.openstreetmap.org/search?format=json&limit=5&q=" + encodeURIComponent(q))
    .then(function(r) { return r.json(); })
    .then(function(res) {
      geocResults.innerHTML = "";
      if (!res || !res.length) {
        geocResults.innerHTML = "<div style=\"color:#888\">Aucun résultat</div>";
        geocResults.style.display = "block";
        return;
      }
      res.forEach(function(item) {
        var d = document.createElement("div");
        d.textContent = item.display_name;
        d.addEventListener("click", function() {
          var lat = parseFloat(item.lat), lon = parseFloat(item.lon);
          map.setView([lat, lon], 13);
          if (marker) { marker.setLatLng([lat, lon]); }
          else { marker = window.L.marker([lat, lon], {draggable: true}).addTo(map); bindMarker(); }
          setCoords(lat.toFixed(6), lon.toFixed(6));
          geocResults.style.display = "none";
          geocInput.value = item.display_name.split(",")[0];
        });
        geocResults.appendChild(d);
      });
      geocResults.style.display = "block";
    });
  }

  geocInput.addEventListener("keydown", function(e) {
    if (e.key === "Enter") { e.preventDefault(); clearTimeout(geocTimer); doSearch(geocInput.value); }
    else { clearTimeout(geocTimer); geocTimer = setTimeout(function(){ doSearch(geocInput.value); }, 600); }
  });
  document.getElementById("osm-geocoder-btn").addEventListener("click", function() {
    doSearch(geocInput.value);
  });
  document.addEventListener("click", function(e) {
    if (!geocResults.contains(e.target) && e.target !== geocInput) geocResults.style.display = "none";
  });

  // Bouton effacer
  document.getElementById("osm-clear-btn").addEventListener("click", function() {
    if (!confirm("Supprimer les coordonnées GPS de cette photo ?")) return;
    document.getElementById("osm-lat").value = "";
    document.getElementById("osm-lon").value = "";
    if (marker) { map.removeLayer(marker); marker = null; }
  });

  // Attendre que tous les scripts soient chargés avant init
  (window.osmmeLeaflet || function(n, c) { c(); })(false, function() {
    setTimeout(initMap, 100);
  });
})();
</script>';

global $template;
$template->assign('OSM_PHOTO_EDIT_HTML', $html);
$template->set_filenames(array('plugin_admin_content' => dirname(__FILE__).'/../template/photo_edit_wrap.tpl'));
$template->assign_var_from_handle('ADMIN_CONTENT', 'plugin_admin_content');
