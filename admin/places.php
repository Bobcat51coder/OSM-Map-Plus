<?php
/*
 * This file is part of OSM Map Plus (GPL-2.0-or-later).
 * Page de réglages : lieux favoris (liste à plat).
 */
if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');
check_status(ACCESS_ADMINISTRATOR);
global $template;
include_once(dirname(__FILE__) . '/../include/places.inc.php');

$root   = get_root_url();
$ep     = $root . 'plugins/osm_map/osmme-places.php';
$token  = get_pwg_token();
$places = osmme_pl_list();
$has_osm = (bool)osmme_pl_osm_table();

$template->append('head_elements',
    '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />' . "\n" .
    '<style>.leaflet-container .leaflet-tile{opacity:1!important;visibility:visible!important;max-width:none!important;max-height:none!important;}.leaflet-tile-pane{opacity:1!important;visibility:visible!important;}</style>' .
    '<script>' . osmme_loader_js() . '</script>'
);

$html = '
<style>
#osm-pl { padding:20px; max-width:1000px; }
#osm-pl table { width:100%; border-collapse:collapse; margin:10px 0; }
#osm-pl th, #osm-pl td { padding:6px 8px; border-bottom:1px solid #e5e5e5; text-align:left; font-size:0.9rem; }
#osm-pl input[type=text] { padding:5px 8px; border:1px solid #ccc; border-radius:4px; }
#osm-pl button { padding:6px 12px; border:1px solid #ccc; border-radius:4px; background:#fff; cursor:pointer; }
#osm-pl button.primary { background:#1a73e8; color:#fff; border-color:#1a73e8; font-weight:600; }
#osm-pl .bar { display:flex; gap:12px; align-items:center; flex-wrap:wrap; margin:12px 0; font-size:0.9rem; }
#osm-pl .map { height:360px; border:1px solid #d0d0d0; border-radius:4px; margin:8px 0; position:relative; z-index:0; }
#osm-pl .res a { display:block; padding:3px 6px; cursor:pointer; font-size:0.85rem; }
#osm-pl .box { border:1px solid #d0d0d0; border-radius:4px; padding:10px 14px; margin:14px 0; background:#fafafa; }
</style>
<div id="osm-pl">
  <p><a href="' . $root . 'admin.php?page=plugin-osm_map">&larr; Retour aux paramètres d\'OSM Map Plus</a></p>
  <h3>&#11088; Lieux favoris</h3>
  <p>Ces lieux apparaissent dans le menu « Lieux favoris » de l\'onglet d\'édition d\'une photo et de la gestion par lot : un choix remplit les coordonnées et recentre la carte. Vous pouvez aussi enregistrer la position du marqueur depuis l\'onglet d\'édition (bouton « Enregistrer ce lieu »).</p>

  <div class="box">
    <strong>Ajouter un lieu</strong>
    <div class="bar">
      <input type="text" id="pl-name" placeholder="Nom du lieu" size="30">
      <input type="text" id="pl-lat" placeholder="Latitude (ex: 48.8566)" size="18">
      <input type="text" id="pl-lon" placeholder="Longitude (ex: 2.3522)" size="18">
      <button type="button" class="primary" id="pl-add">Ajouter</button>
      <span id="pl-msg"></span>
    </div>
    <div class="bar" style="margin-bottom:0;">
      <input type="text" id="pl-search" placeholder="&#128269; Rechercher une adresse ou une ville" size="40">
      <button type="button" id="pl-search-btn">Rechercher</button>
      <span style="color:#666;">ou cliquez sur la carte pour placer le lieu</span>
    </div>
    <div class="res" id="pl-res"></div>
    <div class="map" id="pl-map-new"></div>
  </div>

  <table id="pl-tbl"><thead><tr><th>Nom</th><th>Latitude</th><th>Longitude</th><th></th></tr></thead><tbody></tbody></table>
  <p id="pl-empty" style="display:none;"><em>Aucun lieu favori pour le moment.</em></p>

  <div class="box">
    <strong>Carte de tous les lieux favoris</strong> <span id="pl-count" style="color:#666;"></span>
    <div class="map" id="pl-map-all" style="height:420px;"></div>
  </div>

  <div class="box">
    <strong>Sauvegarde et import</strong>
    <div class="bar">
      <span>Sauvegarde automatique : <code>_data/osm_map_places.json</code> (rechargée à la réinstallation du plugin).</span>
    </div>
    <div class="bar">
      <a href="' . $ep . '?export=1">&#11015; Exporter (JSON)</a>
      <label>&#11014; Importer (JSON) : <input type="file" id="pl-imp" accept=".json,application/json"></label>'
      . ($has_osm ? '<button type="button" id="pl-osm">Importer les lieux de piwigo-openstreetmap</button>' : '') . '
      <span id="pl-msg2"></span>
    </div>'
    . ($has_osm ? '' : '<div style="font-size:0.85rem;color:#666;">La table des lieux de piwigo-openstreetmap n\'a pas été trouvée : pas d\'import possible depuis ce plugin.</div>') . '
  </div>
</div>
<script>
(function() {
  var EP = ' . json_encode($ep) . ', TOKEN = ' . json_encode($token) . ', places = ' . json_encode($places) . ';
  var $ = function(i) { return document.getElementById(i); };
  function esc(s) { var d = document.createElement("div"); d.textContent = s; return d.innerHTML; }
  function post(data, file) {
    var fd = new FormData();
    fd.append("pwg_token", TOKEN);
    Object.keys(data).forEach(function(k) { fd.append(k, data[k]); });
    if (file) fd.append("file", file);
    return fetch(EP, {method: "POST", body: fd, credentials: "same-origin"}).then(function(r) { return r.json(); });
  }
  function reload() {
    return fetch(EP + "?list=1", {credentials: "same-origin"}).then(function(r) { return r.json(); }).then(function(d) { places = d.places || []; render(); });
  }
  function render() {
    var tb = $("pl-tbl").querySelector("tbody"); tb.innerHTML = "";
    places.forEach(function(p) {
      var tr = document.createElement("tr");
      tr.innerHTML = "<td><input type=\\"text\\" class=\\"n\\" size=\\"32\\" value=\\"" + esc(p.name).replace(/"/g, "&quot;") + "\\"></td>"
        + "<td><input type=\\"text\\" class=\\"la\\" size=\\"12\\" value=\\"" + p.lat + "\\"></td>"
        + "<td><input type=\\"text\\" class=\\"lo\\" size=\\"12\\" value=\\"" + p.lon + "\\"></td>"
        + "<td><button type=\\"button\\" class=\\"v\\">Voir</button> <button type=\\"button\\" class=\\"s\\">Enregistrer</button> <button type=\\"button\\" class=\\"d\\">Supprimer</button> <span class=\\"m\\"></span></td>";
      tr.querySelector(".v").addEventListener("click", function() { showOnMap(p); });
      tr.querySelector(".s").addEventListener("click", function() {
        post({update: 1, id: p.id, name: tr.querySelector(".n").value, lat: tr.querySelector(".la").value, lon: tr.querySelector(".lo").value}).then(function(d) {
          tr.querySelector(".m").textContent = d.ok ? "\\u2713" : "Valeurs invalides";
          if (d.ok) reload();
        });
      });
      tr.querySelector(".d").addEventListener("click", function() {
        if (!confirm("Supprimer le lieu \\u00ab " + p.name + " \\u00bb ?")) return;
        post({"delete": 1, id: p.id}).then(reload);
      });
      tb.appendChild(tr);
    });
    drawAll();
    $("pl-tbl").style.display = places.length ? "" : "none";
    $("pl-empty").style.display = places.length ? "none" : "";
  }
  $("pl-add").addEventListener("click", function() {
    post({add: 1, name: $("pl-name").value, lat: $("pl-lat").value, lon: $("pl-lon").value}).then(function(d) {
      if (d.ok) { $("pl-name").value = $("pl-lat").value = $("pl-lon").value = ""; $("pl-msg").textContent = "\\u2713 ajout\\u00e9"; reload(); }
      else { $("pl-msg").textContent = "Nom, latitude (-90 \\u00e0 90) et longitude (-180 \\u00e0 180) requis."; }
    });
  });
  $("pl-imp").addEventListener("change", function() {
    var f = this.files[0]; if (!f) return;
    post({"import": 1}, f).then(function(d) { $("pl-msg2").textContent = (d.imported || 0) + " lieu(x) import\\u00e9(s)."; reload(); });
    this.value = "";
  });
  var ob = $("pl-osm");
  if (ob) ob.addEventListener("click", function() {
    post({import_osm: 1}).then(function(d) { $("pl-msg2").textContent = d.found ? (d.imported + " lieu(x) import\\u00e9(s) sur " + d.total + " (les doublons sont ignor\\u00e9s).") : "Table introuvable."; reload(); });
  });
  render();

  // ── Cartes (Leaflet) ───────────────────────────────────────────────────
  var L_, mapNew, mapAll, newMarker, allLayer, markers = {}, loadingL = false;
  function tiles(m) {
    L_.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {attribution: "&copy; OpenStreetMap contributors", maxZoom: 19}).addTo(m);
  }
  function setNew(lat, lon, move) {
    if (!L_ || !mapNew) return;
    $("pl-lat").value = lat.toFixed(6); $("pl-lon").value = lon.toFixed(6);
    if (newMarker) { newMarker.setLatLng([lat, lon]); }
    else {
      newMarker = L_.marker([lat, lon], {draggable: true}).addTo(mapNew);
      newMarker.on("dragend", function() { var ll = newMarker.getLatLng(); setNew(ll.lat, ll.lng, false); });
    }
    if (move) mapNew.setView([lat, lon], Math.max(mapNew.getZoom(), 14));
  }
  function drawAll() {
    if (!mapAll) return;
    if (allLayer) { mapAll.removeLayer(allLayer); }
    allLayer = L_.layerGroup().addTo(mapAll); markers = {};
    var pts = [];
    places.forEach(function(p) {
      var m = L_.marker([p.lat, p.lon]).addTo(allLayer);
      m.bindTooltip(p.name); m.bindPopup("<strong>" + esc(p.name) + "</strong><br>" + p.lat.toFixed(6) + ", " + p.lon.toFixed(6));
      markers[p.id] = m; pts.push([p.lat, p.lon]);
    });
    $("pl-count").textContent = "(" + places.length + ")";
    if (pts.length === 1) { mapAll.setView(pts[0], 12); }
    else if (pts.length > 1) { mapAll.fitBounds(pts, {padding: [30, 30], maxZoom: 14}); }
  }
  function showOnMap(p) {
    if (!mapAll) return;
    mapAll.setView([p.lat, p.lon], 15);
    if (markers[p.id]) markers[p.id].openPopup();
    $("pl-map-all").scrollIntoView({behavior: "smooth", block: "center"});
  }
  function geocode() {
    var q = $("pl-search").value.trim(); if (!q) return;
    var box = $("pl-res"); box.textContent = "Recherche...";
    fetch("https://nominatim.openstreetmap.org/search?format=json&limit=5&q=" + encodeURIComponent(q), {headers: {"Accept": "application/json"}})
      .then(function(r) { return r.json(); }).then(function(list) {
        box.innerHTML = "";
        if (!list.length) { box.textContent = "Aucun résultat."; return; }
        list.forEach(function(it) {
          var a = document.createElement("a"); a.textContent = it.display_name;
          a.addEventListener("click", function() {
            setNew(parseFloat(it.lat), parseFloat(it.lon), true);
            if (!$("pl-name").value) $("pl-name").value = it.display_name.split(",")[0];
            box.innerHTML = "";
          });
          box.appendChild(a);
        });
      }).catch(function() { box.textContent = "Erreur de recherche."; });
  }
  $("pl-search-btn").addEventListener("click", geocode);
  $("pl-search").addEventListener("keydown", function(e) { if (e.key === "Enter") { e.preventDefault(); geocode(); } });
  ["pl-lat", "pl-lon"].forEach(function(id) {
    $(id).addEventListener("change", function() {
      var la = parseFloat($("pl-lat").value.replace(",", ".")), lo = parseFloat($("pl-lon").value.replace(",", "."));
      if (mapNew && isFinite(la) && isFinite(lo) && Math.abs(la) <= 90 && Math.abs(lo) <= 180) setNew(la, lo, true);
    });
  });
  function initMaps() {
    L_ = window.L;
    if (!L_ || !L_.map) {
      L_ = null;
      if (window.osmmeLeaflet && !loadingL) { loadingL = true; window.osmmeLeaflet(false, initMaps); }
      else { setTimeout(initMaps, 300); }
      return;
    }
    delete L_.Icon.Default.prototype._getIconUrl;
    L_.Icon.Default.mergeOptions({
      iconUrl: "https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png",
      iconRetinaUrl: "https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon-2x.png",
      shadowUrl: "https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png"
    });
    mapNew = L_.map("pl-map-new", {center: [46.6, 2.5], zoom: 5}); tiles(mapNew);
    mapNew.on("click", function(e) { setNew(e.latlng.lat, e.latlng.lng, false); });
    mapAll = L_.map("pl-map-all", {center: [46.6, 2.5], zoom: 5, scrollWheelZoom: false}); tiles(mapAll);
    mapAll.on("click", function() { mapAll.scrollWheelZoom.enable(); });
    mapAll.on("mouseout", function() { mapAll.scrollWheelZoom.disable(); });
    [150, 600, 1500].forEach(function(t) { setTimeout(function() { mapNew.invalidateSize(); mapAll.invalidateSize(); }, t); });
    drawAll();
  }
  initMaps();
  // après un ajout : on retire le marqueur de saisie
  $("pl-add").addEventListener("click", function() {
    setTimeout(function() { if (!$("pl-lat").value && newMarker) { mapNew.removeLayer(newMarker); newMarker = null; } }, 600);
  });
})();
</script>';

$template->assign('OSM_PHOTO_EDIT_HTML', $html);
$template->set_filenames(array('plugin_admin_content' => dirname(__FILE__).'/../template/photo_edit_wrap.tpl'));
$template->assign_var_from_handle('ADMIN_CONTENT', 'plugin_admin_content');
