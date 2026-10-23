/**
 * OSM Map Plus — fonds de carte partagés (cartes de géotaggage : gestion par lot + édition photo)
 * Même liste que la carte de la galerie : raster (Carto, OSM, OSM France, Esri, OpenTopo)
 * et vectoriel (OpenFreeMap, Satellite + noms) via MapLibre GL chargé à la demande.
 *
 * OSMBasemaps.attach(map, { key, select, allowVector, storageKey }) -> { set(key), get() }
 */
(function () {
  'use strict';

  function cartoUrl() {
    var k = window.OSM_CARTO_API_KEY || '';
    return 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}.png' + (k ? ('?key=' + encodeURIComponent(k)) : '');
  }
  var OSM_ATTR = '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors';

  function tileDef(key) {
    switch (key) {
      case 'carto_voyager':  return { url: cartoUrl(), attr: '&copy; OpenStreetMap &copy; CARTO', maxZoom: 19 };
      case 'osm_standard':   return { url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', attr: OSM_ATTR, maxZoom: 19 };
      case 'osm_france':     return { url: 'https://{s}.tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png', attr: '&copy; OpenStreetMap France | ' + OSM_ATTR, maxZoom: 20 };
      case 'esri_satellite': return { url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', attr: 'Tiles &copy; Esri', maxZoom: 18 };
      case 'opentopo':       return { url: 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', attr: '&copy; OpenStreetMap &copy; OpenTopoMap', maxZoom: 17 };
      case 'openfreemap':    return { vector: true, style: 'https://tiles.openfreemap.org/styles/liberty',
                                      attr: '<a href="https://openfreemap.org" target="_blank" rel="noopener">OpenFreeMap</a> &copy; OpenMapTiles Data from OpenStreetMap' };
      case 'sat_labels':     return { vector: true, style: null,
                                      attr: 'Imagery &copy; Esri, Maxar &mdash; Noms : <a href="https://openfreemap.org" target="_blank" rel="noopener">OpenFreeMap</a> &copy; OpenStreetMap' };
    }
    return null;
  }

  var CHOICES = [
    ['carto_voyager', 'Carto Voyager'],
    ['osm_standard',  'OpenStreetMap'],
    ['osm_france',    'OpenStreetMap France'],
    ['openfreemap',   'OpenFreeMap'],
    ['esri_satellite','Satellite (Esri)'],
    ['sat_labels',    'Satellite + noms'],
    ['opentopo',      'Topographique']
  ];

  var MAPLIBRE_JS  = 'https://cdn.jsdelivr.net/npm/maplibre-gl@4.7.1/dist/maplibre-gl.js';
  var MAPLIBRE_CSS = 'https://cdn.jsdelivr.net/npm/maplibre-gl@4.7.1/dist/maplibre-gl.css';
  var MLLEAFLET_JS = 'https://cdn.jsdelivr.net/npm/@maplibre/maplibre-gl-leaflet@0.0.22/leaflet-maplibre-gl.js';

  // Un chargeur AMD (require.js…) présent dans l'admin Piwigo ferait enregistrer MapLibre comme module
  // au lieu de le rendre global : on le masque le temps du chargement.
  function loadScript(src, ok, fail) {
    var s = document.createElement('script');
    var savedDefine = window.define, hidden = false;
    if (savedDefine && savedDefine.amd) { window.define = undefined; hidden = true; }
    function restore() { if (hidden) { window.define = savedDefine; hidden = false; } }
    s.src = src; s.async = true;
    s.onload = function () { restore(); ok(); };
    s.onerror = function () { restore(); console.error('OSM Map: chargement impossible', src); fail(); };
    document.head.appendChild(s);
  }

  // MapLibre + plugin : chargés une seule fois, files d'attente pour les cartes multiples
  var mlState = 0, mlQueue = [];
  function withMapLibre(ok, fail) {
    var L = window.L;
    if (window.maplibregl && L && L.maplibreGL) { ok(); return; }
    mlQueue.push([ok, fail]);
    if (mlState === 1) return;
    mlState = 1;
    if (!document.querySelector('link[href="' + MAPLIBRE_CSS + '"]')) {
      var l = document.createElement('link'); l.rel = 'stylesheet'; l.href = MAPLIBRE_CSS; document.head.appendChild(l);
    }
    function done(success) {
      mlState = success ? 2 : 0;
      var q = mlQueue; mlQueue = [];
      q.forEach(function (p) { (success ? p[0] : p[1])(); });
    }
    function step2() {
      if (window.L && window.L.maplibreGL) { done(true); return; }
      loadScript(MLLEAFLET_JS, function () { done(!!(window.L && window.L.maplibreGL)); }, function () { done(false); });
    }
    if (window.maplibregl) step2();
    else loadScript(MAPLIBRE_JS, step2, function () { done(false); });
  }

  function satLabelsStyle() {
    var name = ['coalesce', ['get', 'name:fr'], ['get', 'name']];
    var font = ['Noto Sans Regular'], fontB = ['Noto Sans Bold'];
    var halo = { 'text-color': '#ffffff', 'text-halo-color': 'rgba(0,0,0,0.75)', 'text-halo-width': 1.5 };
    function place(id, classes, minzoom, size, bold) {
      return { id: id, type: 'symbol', source: 'ofm', 'source-layer': 'place', minzoom: minzoom,
        filter: ['match', ['get', 'class'], classes, true, false],
        layout: { 'text-field': name, 'text-font': bold ? fontB : font, 'text-size': size, 'text-max-width': 8 }, paint: halo };
    }
    return {
      version: 8,
      glyphs: 'https://tiles.openfreemap.org/fonts/{fontstack}/{range}.pbf',
      sources: {
        esri: { type: 'raster', tileSize: 256, maxzoom: 19,
                tiles: ['https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}'] },
        ofm:  { type: 'vector', url: 'https://tiles.openfreemap.org/planet' }
      },
      layers: [
        { id: 'esri', type: 'raster', source: 'esri' },
        place('pl-country', ['country'], 1, ['interpolate', ['linear'], ['zoom'], 1, 11, 6, 16], true),
        place('pl-state', ['state', 'province'], 4, 13, false),
        place('pl-city', ['city', 'town'], 5, ['interpolate', ['linear'], ['zoom'], 5, 12, 12, 19], true),
        place('pl-village', ['village', 'suburb', 'quarter', 'neighbourhood'], 10, ['interpolate', ['linear'], ['zoom'], 10, 11, 15, 15], false),
        place('pl-hamlet', ['hamlet', 'isolated_dwelling'], 12, 11, false),
        { id: 'roads', type: 'symbol', source: 'ofm', 'source-layer': 'transportation_name', minzoom: 12,
          layout: { 'text-field': name, 'text-font': font, 'text-size': 11, 'symbol-placement': 'line' }, paint: halo },
        { id: 'water', type: 'symbol', source: 'ofm', 'source-layer': 'water_name', minzoom: 3,
          layout: { 'text-field': name, 'text-font': font, 'text-size': 12 },
          paint: { 'text-color': '#cfe8ff', 'text-halo-color': 'rgba(0,0,0,0.75)', 'text-halo-width': 1.5 } }
      ]
    };
  }

  function attach(map, o) {
    o = o || {};
    var L = window.L;
    var allowVector = o.allowVector !== false;
    // La molette ne zoome la carte qu'après un clic dans la carte (sinon elle défile la page) ; désactivée à la sortie du curseur
    if (o.clickToActivate !== false && map.scrollWheelZoom) {
      map.scrollWheelZoom.disable();
      map.on('click focus', function () { map.scrollWheelZoom.enable(); });
      map.on('mouseout', function () { map.scrollWheelZoom.disable(); });
    }
    var storageKey = o.storageKey || 'osmme_basemap_geotag';
    var layer = null, curKey = null, token = 0;

    // Leaflet < 1.0 (ancien Leaflet d'un autre plugin dans l'admin) : pas de fonds vectoriels (L.Layer absent)
    var modern = !!(L && L.Layer && L.Layer.extend && parseInt(String(L.version || '0'), 10) >= 1);
    var vectorOk = allowVector && modern;

    function allowed(k) {
      var t = tileDef(k);
      if (!t) return false;
      if (!t.vector) return true;
      // « Satellite + noms » garde une version raster (Esri imagerie + noms Esri) quand le vectoriel est impossible
      return vectorOk || k === 'sat_labels';
    }

    function set(key) {
      if (!allowed(key)) key = 'carto_voyager';
      var t = tileDef(key);
      var my = ++token;

      function apply(newLayer) {
        if (my !== token) return;
        if (layer) { try { map.removeLayer(layer); } catch (e) {} }
        layer = newLayer; curKey = key;
        newLayer.addTo(map);
        if (newLayer.bringToBack) newLayer.bringToBack();
        if (o.select) o.select.value = key;
        try { window.localStorage.setItem(storageKey, JSON.stringify({ d: o.key || '', k: key })); } catch (e) {}
      }

      if (t.vector && !vectorOk && key === 'sat_labels') {
        apply(L.layerGroup([
          L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', { attribution: 'Imagery &copy; Esri, Maxar', maxZoom: 18 }),
          L.tileLayer('https://server.arcgisonline.com/ArcGIS/rest/services/Reference/World_Boundaries_and_Places/MapServer/tile/{z}/{y}/{x}', { attribution: 'Noms &copy; Esri', maxZoom: 18 })
        ]));
        return;
      }

      if (!t.vector) {
        var tl = L.tileLayer(t.url, { attribution: t.attr, maxZoom: t.maxZoom });
        // Si le fond ne répond pas (clé Carto absente/invalide, serveur HS…) : bascule sur OpenStreetMap
        var errs = 0, swapped = false;
        tl.on('tileerror', function () {
          if (swapped || ++errs < 4) return;
          swapped = true;
          if (key !== 'osm_standard') {
            var d = tileDef('osm_standard');
            if (my === token) apply(L.tileLayer(d.url, { attribution: d.attr, maxZoom: d.maxZoom }));
          }
        });
        apply(tl);
        return;
      }

      var fail = function () {
        if (my !== token) return;
        if (o.select && curKey) o.select.value = curKey;
        if (!curKey) set('osm_standard');
      };
      withMapLibre(function () {
        if (my !== token) return;
        try {
          apply(L.maplibreGL({ style: key === 'sat_labels' ? satLabelsStyle() : t.style, attribution: t.attr }));
        } catch (e) { console.error('OSM Map: fond vectoriel', e); fail(); }
      }, fail);
    }

    if (o.select) {
      o.select.innerHTML = '';
      CHOICES.forEach(function (c) {
        if (!allowed(c[0])) return;
        var opt = document.createElement('option');
        opt.value = c[0]; opt.textContent = c[1];
        o.select.appendChild(opt);
      });
      o.select.addEventListener('change', function () { set(this.value); });
    }

    var start = o.key;
    try {
      var saved = JSON.parse(window.localStorage.getItem(storageKey) || 'null');
      if (saved && saved.d === (o.key || '') && allowed(saved.k)) start = saved.k;
    } catch (e) {}
    set(allowed(start) ? start : 'carto_voyager');
    // Ouvre la vue 3D (nouvel onglet) centrée sur la carte ; extra : { pid: id photo à mettre en évidence, cat: id album }
    function open3D(root, extra) {
      extra = extra || {};
      var c = map.getCenter();
      var b = (curKey === 'sat_labels' || curKey === 'esri_satellite') ? 'sat' : (curKey === 'osm_france' ? 'osmfr' : 'ofm');
      var url = (root || '/') + 'plugins/osm_map/osmme-3d.php?lat=' + c.lat.toFixed(5) + '&lng=' + c.lng.toFixed(5)
              + '&z=' + map.getZoom() + '&base=' + b;
      if (extra.pid) url += '&pid=' + parseInt(extra.pid, 10);
      if (extra.cat) url += '&cat_id=' + parseInt(extra.cat, 10);
      window.open(url, '_blank', 'noopener');
    }
    function rootUrl() {
      if (o.root) return o.root;
      var sc = document.querySelector('script[src*="osm_map/js/basemaps.js"]');
      if (sc) return sc.getAttribute('src').replace(/plugins\/osm_map\/js\/basemaps\.js.*$/, '').replace(/^(https?:)?\/\/[^\/]+/, '') || '/';
      return '/';
    }
    // Bouton 3D : celui fourni (o.button), sinon créé juste après le menu de fond
    var btn = o.button || null;
    if (!btn && o.select && o.select.parentNode) {
      btn = o.select.parentNode.querySelector('.osmme-3d-btn');
      if (!btn) {
        btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'osmme-3d-btn';
        btn.title = 'Ouvrir la carte en relief 3D dans un nouvel onglet';
        btn.innerHTML = '\uD83C\uDFD4\uFE0F 3D';
        btn.style.cssText = 'margin-left:6px;padding:4px 10px;border:1px solid #ccc;border-radius:4px;background:#fff;color:#333;cursor:pointer;font-size:0.85rem;';
        o.select.parentNode.insertBefore(btn, o.select.nextSibling);
      }
    }
    if (btn) btn.addEventListener('click', function (e) { e.preventDefault(); open3D(rootUrl(), { pid: o.pid }); });
    return { set: set, get: function () { return curKey; }, open3D: open3D };
  }

  window.OSMBasemaps = { attach: attach, choices: CHOICES };
})();
