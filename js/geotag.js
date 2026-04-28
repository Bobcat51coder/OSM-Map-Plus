/**
 * OSM Map Enhanced - Géotaggage (batch manager + photo edit)
 * Bobcat-Fr / Claude (Anthropic)
 */
'use strict';

var OSMGeotag = (function () {

  var batchMap, batchMarker, batchLat, batchLon;

  // ── Gestion par lot ──────────────────────────────────────────────────────
  function initBatch() {
    var container = document.getElementById('osm-batch-map');
    if (!container || typeof L === 'undefined') return;

    var photos  = window.OSM_BATCH_PHOTOS || [];
    var apiUrl  = window.OSM_API_URL || 'ws.php';

    // Carte centrée sur les photos sélectionnées si GPS dispo, sinon France
    var center = [46.5, 2.5], zoom = 5;
    var withGps = photos.filter(function(p){ return p.latitude && p.longitude; });
    if (withGps.length === 1) {
      center = [withGps[0].latitude, withGps[0].longitude];
      zoom = 13;
    } else if (withGps.length > 1) {
      var lats = withGps.map(function(p){ return p.latitude; });
      var lons = withGps.map(function(p){ return p.longitude; });
      center = [
        (Math.min.apply(null,lats) + Math.max.apply(null,lats)) / 2,
        (Math.min.apply(null,lons) + Math.max.apply(null,lons)) / 2
      ];
      zoom = 8;
    }

    batchMap = L.map('osm-batch-map', { center: center, zoom: zoom });
    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}.png', {
      attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; CARTO',
      maxZoom: 19
    }).addTo(batchMap);

    // Marqueurs des photos déjà géolocalisées (bleu clair)
    var existingGroup = L.layerGroup().addTo(batchMap);
    withGps.forEach(function(p) {
      var m = L.circleMarker([p.latitude, p.longitude], {
        radius: 7, color: '#1a73e8', fillColor: '#4fa7ff',
        fillOpacity: 0.8, weight: 2
      });
      m.bindTooltip(p.name, { direction: 'top' });
      existingGroup.addLayer(m);
    });

    // Clic sur la carte → marqueur rouge = nouvelle position
    batchMap.on('click', function(e) {
      batchLat = e.latlng.lat.toFixed(6);
      batchLon = e.latlng.lng.toFixed(6);

      if (batchMarker) {
        batchMarker.setLatLng(e.latlng);
      } else {
        batchMarker = L.marker(e.latlng, { draggable: true }).addTo(batchMap);
        batchMarker.on('dragend', function() {
          var ll = batchMarker.getLatLng();
          batchLat = ll.lat.toFixed(6);
          batchLon = ll.lng.toFixed(6);
          _updateCoords(batchLat, batchLon);
        });
      }
      batchMarker.bindPopup(
        '<strong>Nouvelle position</strong><br>' + batchLat + ', ' + batchLon +
        '<br><small>Déplaçable</small>'
      ).openPopup();

      _updateCoords(batchLat, batchLon);
      document.getElementById('osm-batch-save').disabled = false;
    });

    // Bouton Appliquer
    var btnSave = document.getElementById('osm-batch-save');
    if (btnSave) {
      btnSave.addEventListener('click', function() {
        if (!batchLat || !batchLon) return;
        // Récupérer les IDs cochés dans la gestion par lot
        var ids = _getCheckedIds();
        if (!ids.length) {
          alert('Aucune photo cochée dans la liste.');
          return;
        }
        _applyGps(ids, batchLat, batchLon, apiUrl, existingGroup, photos);
      });
    }

    // Bouton Effacer GPS
    var btnClear = document.getElementById('osm-batch-clear');
    if (btnClear) {
      btnClear.addEventListener('click', function() {
        var ids = _getCheckedIds();
        if (!ids.length) { alert('Aucune photo cochée.'); return; }
        if (!confirm('Effacer les coordonnées GPS de ' + ids.length + ' photo(s) ?')) return;
        _applyGps(ids, '', '', apiUrl, existingGroup, photos);
      });
    }
  }

  function _updateCoords(lat, lon) {
    var div = document.getElementById('osm-batch-coords');
    var inLat = document.getElementById('osm-batch-lat');
    var inLon = document.getElementById('osm-batch-lon');
    if (div) div.style.display = 'block';
    if (inLat) inLat.value = lat;
    if (inLon) inLon.value = lon;
    var info = document.getElementById('osm-batch-info');
    if (info) info.textContent = 'Position : ' + lat + ', ' + lon + ' — cochez les photos à géotagger puis cliquez Appliquer.';
  }

  function _getCheckedIds() {
    // Les checkboxes de sélection dans la gestion par lot Piwigo
    var ids = [];
    document.querySelectorAll('input[name="elements[]"]:checked, input[name="element_ids[]"]:checked').forEach(function(cb) {
      var id = parseInt(cb.value, 10);
      if (id) ids.push(id);
    });
    return ids;
  }

  function _applyGps(ids, lat, lon, apiUrl, existingGroup, photos) {
    var btnSave = document.getElementById('osm-batch-save');
    var info    = document.getElementById('osm-batch-info');
    if (btnSave) btnSave.disabled = true;
    if (info) info.textContent = 'Enregistrement en cours… (0/' + ids.length + ')';

    var done = 0, errors = 0;

    function next(i) {
      if (i >= ids.length) {
        var msg = lat
          ? 'GPS appliqué à ' + (done) + ' photo(s).'
          : 'GPS effacé sur ' + (done) + ' photo(s).';
        if (errors) msg += ' (' + errors + ' erreur(s))';
        if (info) info.textContent = msg;
        if (btnSave) btnSave.disabled = false;
        // Rafraîchir les marqueurs existants
        existingGroup.clearLayers();
        photos.forEach(function(p) {
          if (ids.indexOf(p.id) !== -1) {
            p.latitude  = lat ? parseFloat(lat) : null;
            p.longitude = lon ? parseFloat(lon) : null;
          }
          if (p.latitude && p.longitude) {
            var m = L.circleMarker([p.latitude, p.longitude], {
              radius: 7, color: '#1a73e8', fillColor: '#4fa7ff',
              fillOpacity: 0.8, weight: 2
            });
            m.bindTooltip(p.name, { direction: 'top' });
            existingGroup.addLayer(m);
          }
        });
        return;
      }

      var params = new URLSearchParams();
      params.append('method',   'pwg.images.setInfo');
      params.append('format',   'json');
      params.append('image_id', ids[i]);
      if (lat && lon) {
        params.append('latitude',  lat);
        params.append('longitude', lon);
      } else {
        params.append('latitude',  '');
        params.append('longitude', '');
      }

      fetch(apiUrl, { method: 'POST', body: params })
      .then(function(r) { return r.text(); })
      .then(function(text) {
        done++;
        if (info) info.textContent = 'Enregistrement… (' + done + '/' + ids.length + ')';
        next(i + 1);
      })
      .catch(function() {
        errors++;
        done++;
        next(i + 1);
      });
    }
    next(0);
  }

  // ── Édition photo individuelle ───────────────────────────────────────────
  function initPhotoEdit(imageId, lat, lon, apiUrl) {
    var container = document.getElementById('osm-edit-map');
    if (!container || typeof L === 'undefined') return;

    var initLat = lat || 46.5;
    var initLon = lon || 2.5;
    var initZoom = lat ? 13 : 5;

    var editMap = L.map('osm-edit-map', { center: [initLat, initLon], zoom: initZoom });
    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}.png', {
      attribution: '&copy; OpenStreetMap &copy; CARTO', maxZoom: 19
    }).addTo(editMap);

    var marker = null;
    if (lat && lon) {
      marker = L.marker([lat, lon], { draggable: true }).addTo(editMap);
      _bindEditMarker(marker, imageId, apiUrl);
    }

    editMap.on('click', function(e) {
      if (marker) {
        marker.setLatLng(e.latlng);
      } else {
        marker = L.marker(e.latlng, { draggable: true }).addTo(editMap);
        _bindEditMarker(marker, imageId, apiUrl);
      }
      _setEditCoords(e.latlng.lat.toFixed(6), e.latlng.lng.toFixed(6));
    });

    // Géocodeur simple
    var geocInput = document.getElementById('osm-edit-geocoder');
    if (geocInput) {
      geocInput.addEventListener('keydown', function(e) {
        if (e.key !== 'Enter') return;
        e.preventDefault();
        fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q=' + encodeURIComponent(geocInput.value))
        .then(function(r){ return r.json(); })
        .then(function(res) {
          if (!res || !res.length) { alert('Lieu non trouvé.'); return; }
          var ll = [parseFloat(res[0].lat), parseFloat(res[0].lon)];
          editMap.setView(ll, 13);
          if (marker) { marker.setLatLng(ll); } else {
            marker = L.marker(ll, { draggable: true }).addTo(editMap);
            _bindEditMarker(marker, imageId, apiUrl);
          }
          _setEditCoords(res[0].lat, res[0].lon);
        });
      });
    }
  }

  function _bindEditMarker(marker, imageId, apiUrl) {
    marker.on('dragend', function() {
      var ll = marker.getLatLng();
      _setEditCoords(ll.lat.toFixed(6), ll.lng.toFixed(6));
    });
  }

  function _setEditCoords(lat, lon) {
    var inLat = document.getElementById('osm-edit-lat');
    var inLon = document.getElementById('osm-edit-lon');
    if (inLat) inLat.value = lat;
    if (inLon) inLon.value = lon;
  }

  return { initBatch: initBatch, initPhotoEdit: initPhotoEdit };
})();

// ── Carte inline (remplace OSM Geotag dans formulaire unitaire / picture_modify) ─
OSMGeotag.initInline = function(imageId, lat, lon, apiUrl) {
    var container = document.getElementById('osm-geotag-map');
    if (!container || typeof L === 'undefined') return;

    var hasGps   = (lat !== null && lon !== null);
    var center   = hasGps ? [lat, lon] : [46.5, 2.5];
    var zoom     = hasGps ? 13 : 5;

    var map = L.map('osm-geotag-map', { center: center, zoom: zoom });
    L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; CARTO',
        maxZoom: 19
    }).addTo(map);

    var marker = null;
    var latInput = document.getElementById('osm-geotag-lat');
    var lonInput = document.getElementById('osm-geotag-lon');
    var status   = document.getElementById('osm-geotag-status');

    function setCoords(lt, ln) {
        if (latInput) latInput.value = parseFloat(lt).toFixed(6);
        if (lonInput) lonInput.value = parseFloat(ln).toFixed(6);
    }

    function showStatus(msg, color) {
        if (!status) return;
        status.textContent = msg;
        status.style.color = color || '#1e8e3e';
    }

    if (hasGps) {
        marker = L.marker([lat, lon], { draggable: true }).addTo(map);
        marker.on('dragend', function() {
            var ll = marker.getLatLng();
            setCoords(ll.lat, ll.lng);
            showStatus('Position modifiée — pensez à Sauvegarder.', '#e65100');
        });
    }

    // Clic sur la carte
    map.on('click', function(e) {
        if (marker) {
            marker.setLatLng(e.latlng);
        } else {
            marker = L.marker(e.latlng, { draggable: true }).addTo(map);
            marker.on('dragend', function() {
                var ll = marker.getLatLng();
                setCoords(ll.lat, ll.lng);
                showStatus('Position modifiée — pensez à Sauvegarder.', '#e65100');
            });
        }
        setCoords(e.latlng.lat, e.latlng.lng);
        showStatus('Position sélectionnée — pensez à Sauvegarder.', '#e65100');
    });

    // Géocodeur
    var geocInput = document.getElementById('osm-geotag-geocoder');
    if (geocInput) {
        geocInput.addEventListener('keydown', function(e) {
            if (e.key !== 'Enter') return;
            e.preventDefault();
            var q = geocInput.value.trim();
            if (!q) return;
            showStatus('Recherche…', '#666');
            fetch('https://nominatim.openstreetmap.org/search?format=json&limit=5&q=' + encodeURIComponent(q))
            .then(function(r) { return r.json(); })
            .then(function(res) {
                if (!res || !res.length) { showStatus('Lieu non trouvé.', '#d93025'); return; }
                // Afficher dropdown si plusieurs résultats
                if (res.length === 1) {
                    _applyGeocode(res[0]);
                } else {
                    _showGeocodeDropdown(res);
                }
            })
            .catch(function() { showStatus('Erreur de géocodage.', '#d93025'); });
        });
    }

    function _applyGeocode(result) {
        var ll = [parseFloat(result.lat), parseFloat(result.lon)];
        map.setView(ll, 14);
        if (marker) { marker.setLatLng(ll); } else {
            marker = L.marker(ll, { draggable: true }).addTo(map);
        }
        setCoords(result.lat, result.lon);
        showStatus('Lieu trouvé : ' + result.display_name.split(',')[0] + ' — pensez à Sauvegarder.', '#e65100');
        _removeDropdown();
    }

    function _showGeocodeDropdown(results) {
        _removeDropdown();
        var wrap = document.getElementById('osm-geotag-geocoder-wrap');
        var dd = document.createElement('ul');
        dd.id = 'osm-geotag-dropdown';
        dd.style.cssText = 'position:absolute;z-index:9999;background:#fff;border:1px solid #ccc;border-radius:4px;list-style:none;margin:0;padding:0;max-width:400px;box-shadow:0 2px 6px rgba(0,0,0,.2);';
        results.forEach(function(r) {
            var li = document.createElement('li');
            li.textContent = r.display_name;
            li.style.cssText = 'padding:7px 12px;cursor:pointer;font-size:0.85em;border-bottom:1px solid #eee;';
            li.addEventListener('mouseenter', function(){ li.style.background='#f0f4ff'; });
            li.addEventListener('mouseleave', function(){ li.style.background=''; });
            li.addEventListener('click', function(){ _applyGeocode(r); });
            dd.appendChild(li);
        });
        if (wrap) wrap.style.position = 'relative';
        if (wrap) wrap.appendChild(dd);
    }

    function _removeDropdown() {
        var old = document.getElementById('osm-geotag-dropdown');
        if (old) old.parentNode.removeChild(old);
    }

    // Bouton effacer GPS
    var btnClear = document.getElementById('osm-geotag-clear');
    if (btnClear) {
        btnClear.addEventListener('click', function() {
            if (!confirm('Supprimer les coordonnées GPS ?')) return;
            if (marker) { map.removeLayer(marker); marker = null; }
            if (latInput) latInput.value = '';
            if (lonInput) lonInput.value = '';
            showStatus('GPS effacé — pensez à Sauvegarder.', '#d93025');
        });
    }
};
