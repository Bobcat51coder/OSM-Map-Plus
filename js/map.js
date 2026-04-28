/**
 * OSM Map Enhanced for Piwigo - map.js v2.1.0
 */
'use strict';

var OSMMap = (function () {

  var map, clusterGroup;
  var allPhotos     = [];
  var visiblePhotos = [];
  var inViewPhotos  = [];
  var geocodeTimer  = null;
  var panelOpen     = false;
  var apiUrl        = '';

  // ── Fonds de carte ─────────────────────────────────────────────────────
  var TILES = {
    carto_voyager: {
      url: 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}.png',
      attr: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; CARTO',
      maxZoom: 19
    },
    osm_standard: {
      url: 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',
      attr: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
      maxZoom: 19
    },
    esri_satellite: {
      url: 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}',
      attr: 'Tiles &copy; Esri &mdash; Source: Esri, USGS, NOAA',
      maxZoom: 18
    },
    opentopo: {
      url: 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',
      attr: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> &copy; <a href="https://opentopomap.org/">OpenTopoMap</a>',
      maxZoom: 17
    }
  };

  function init(options) {
    apiUrl   = options.apiUrl || window.OSM_API_URL || 'ws.php';
    var initZoom = options.initZoom || 5;

    // Initialiser la carte immédiatement (vue monde) sans attendre les données
    _initMap(options.mapHeight || 500, initZoom, options.tile || 'carto_voyager', null);
    _bindControls();

    // Charger les points GPS via l'endpoint léger (lat/lng/id uniquement)
    var pointsUrl = window.OSM_POINTS_URL || options.pointsUrl;
    if (pointsUrl) {
      _showLoading(true);
      fetch(pointsUrl, { credentials: 'same-origin' })
        .then(function(r) {
          if (!r.ok) throw new Error('HTTP ' + r.status);
          return r.text();
        })
        .then(function(text) {
          // Vérifier que la réponse est bien du JSON avant de parser
          var trimmed = text.trim();
          if (!trimmed || trimmed[0] !== '[') {
            throw new Error('Réponse non-JSON : ' + trimmed.substring(0, 100));
          }
          return JSON.parse(trimmed);
        })
        .then(function(data) {
          // Format compact : [[id, lat, lng], ...]
          allPhotos = data.map(function(p) {
            return { id: p[0], latitude: p[1], longitude: p[2] };
          });
          visiblePhotos = allPhotos.slice();
          // Recentrer sur les données réelles
          var bounds = _computeBounds(allPhotos);
          if (bounds) {
            try {
              var b = L.latLngBounds(bounds[0], bounds[1]);
              if (b.isValid()) map.fitBounds(b.pad(0.05));
            } catch(e) {}
          }
          // Désactiver moveend pendant le rendu initial pour éviter les conflits
          map.off('moveend zoomend', _updatePanelFromBounds);
          _renderMarkers(allPhotos);
          // Réactiver après un délai suffisant pour laisser les chunks se charger
          setTimeout(function() {
            // moveend est activé après le chargement des données (voir init)
            _updatePanelFromBounds();
          }, 200);
          _showLoading(false);
        })
        .catch(function(err) {
          console.error('OSM Map: erreur chargement points', err);
          _showLoading(false);
        });
    } else {
      // Fallback : données inline (page galerie)
      allPhotos     = options.photos || window.OSM_ALL_PHOTOS || [];
      visiblePhotos = allPhotos.slice();
      var initialBounds = _computeBounds(allPhotos);
      if (initialBounds && map) {
        try {
          var b = L.latLngBounds(initialBounds[0], initialBounds[1]);
          if (b.isValid()) map.fitBounds(b.pad(0.05));
        } catch(e) {}
      }
      map.off('moveend zoomend', _updatePanelFromBounds);
      _renderMarkers(allPhotos);
      setTimeout(function() {
        map.on('moveend zoomend', _updatePanelFromBounds);
        _updatePanelFromBounds();
      }, 200);
    }
  }

  function _showLoading(show) {
    var el = document.getElementById('osm-loading');
    if (el) el.style.display = show ? 'inline' : 'none';
    var cnt = document.getElementById('osm-count-visible');
    if (cnt && show) cnt.textContent = '…';
  }

  // Calcule le LatLngBounds de toutes les photos passées en paramètre
  function _computeBounds(photos) {
    if (!photos || !photos.length) return null;
    var lats = photos.map(function(p){ return p.latitude; });
    var lngs = photos.map(function(p){ return p.longitude; });
    var minLat = Math.min.apply(null, lats);
    var maxLat = Math.max.apply(null, lats);
    var minLng = Math.min.apply(null, lngs);
    var maxLng = Math.max.apply(null, lngs);
    return [[minLat, minLng], [maxLat, maxLng]];
  }

  function _initMap(height, initZoom, tileKey, initialBounds) {
    var container = document.getElementById('osm-map');
    if (!container || typeof L === 'undefined') return;
    container.style.height = height + 'px';
    // Contraindre le panel et son body à la même hauteur que la carte
    var panel = document.getElementById('osm-panel');
    if (panel) panel.style.maxHeight = height + 'px';
    var body = container.parentNode;
    if (body && body.classList && body.classList.contains('osm-body')) {
      body.style.height = height + 'px';
    }

    // Forcer la hauteur du container parent = carte + header + footer
    // Neutralise toute règle CSS externe (thème, osm-map-wrapper, etc.)
    var wrapper = container.closest
      ? container.closest('#osm-map-container')
      : document.getElementById('osm-map-container');
    if (wrapper) {
      wrapper.style.height    = '';      // reset toute hauteur CSS forcée
      wrapper.style.minHeight = '';
      wrapper.style.maxHeight = '';
      // Laisser le navigateur recalculer après reset
      requestAnimationFrame(function() {
        wrapper.style.height = '';
      });
    }

    // Si on a des données, utiliser fitBounds au lieu d'un centre fixe
    var mapOptions;
    if (initialBounds) {
      // Initialiser sans centre/zoom : on fera fitBounds juste après
      mapOptions = { center: [0, 0], zoom: 2 };
    } else {
      mapOptions = { center: [46.5, 2.5], zoom: initZoom };
    }

    map = L.map('osm-map', mapOptions);
    var t = TILES[tileKey] || TILES['carto_voyager'];
    L.tileLayer(t.url, { attribution: t.attr, maxZoom: t.maxZoom }).addTo(map);

    // Ajuster la vue sur les données réelles dès le départ
    if (initialBounds) {
      try {
        var b = L.latLngBounds(initialBounds[0], initialBounds[1]);
        if (b.isValid()) map.fitBounds(b.pad(0.1));
      } catch(e) {
        map.setView([46.5, 2.5], initZoom);
      }
    }

    clusterGroup = L.markerClusterGroup({
      chunkedLoading: true,
      chunkSize: 500,           // chunks plus grands = moins de passes RAF
      chunkDelay: 10,           // délai minimal entre chunks
      maxClusterRadius: 80,     // agrégation plus agressive = moins de marqueurs DOM
      showCoverageOnHover: false,
      animate: false,           // désactiver les animations = bien plus rapide avec 15k+ points
      animateAddingMarkers: false,
      removeOutsideVisibleBounds: true  // ne rend que les marqueurs visibles
    });
    map.addLayer(clusterGroup);

    // moveend est activé après le chargement des données (voir init)
    window.addEventListener('resize', function () { map.invalidateSize(); });
  }

  // Token pour annuler un rendu en cours si un nouveau démarre (filtre album)
  var _renderToken = 0;

  function _renderMarkers(photos) {
    if (!clusterGroup) return;
    clusterGroup.clearLayers();
    if (!photos || !photos.length) { _updateStats(0); return; }

    var token = ++_renderToken;   // invalide les chunks d'un rendu précédent
    var CHUNK = photos.length > 5000 ? 500 : (photos.length > 1000 ? 200 : 100);
    var idx = 0;

    function addChunk() {
      if (token !== _renderToken) return;  // rendu annulé
      var batch = [];
      photos.slice(idx, idx + CHUNK).forEach(function (p) {
        var marker = L.marker([p.latitude, p.longitude]);
        marker._osmPhoto = p;
        marker.on('click', function () {
          var self = this;
          var photo = self._osmPhoto;
          _highlightPanel(photo.id);
          if (self._popupBound) { self.openPopup(); return; }
          var photoUrl = window.OSM_PHOTO_URL;
          if (photoUrl && !photo.name) {
            self.bindPopup('<div style="padding:8px;color:#888;font-size:12px;">Chargement…</div>', { maxWidth: 260 }).openPopup();
            fetch(photoUrl + '?id=' + photo.id, { credentials: 'same-origin' })
              .then(function(r) { return r.json(); })
              .then(function(data) {
                photo.name          = data.name;
                photo.date          = data.date;
                photo.album_name    = data.album_name;
                photo.thumbnail_url = data.thumbnail_url;
                photo.page_url      = data.page_url;
                self._popupBound = true;
                self.setPopupContent(_buildPopup(photo));
              })
              .catch(function() {
                self.setPopupContent('<div style="padding:8px;">Photo #' + photo.id + '</div>');
              });
          } else {
            self.bindPopup(_buildPopup(photo), { maxWidth: 260 }).openPopup();
            self._popupBound = true;
          }
        });
        batch.push(marker);
      });
      clusterGroup.addLayers(batch);
      idx += CHUNK;
      if (idx < photos.length) { requestAnimationFrame(addChunk); return; }
      // Fin du rendu — pas de fitBounds ici (déjà fait dans init)
      _updateStats(photos.length);
      _updatePanelFromBounds();
    }
    requestAnimationFrame(addChunk);
  }

  // Reconstruit l'URL page depuis l'id si absente du payload JSON (payload minimal)
  function _getPageUrl(p) {
    if (p.page_url) return p.page_url;
    return (window.OSM_ROOT || '') + 'picture.php?/' + p.id;
  }

  function _buildPopup(p) {
    var pageUrl = _getPageUrl(p);
    var html = '<div style="font-size:13px;max-width:230px;">';
    if (p.thumbnail_url) {
      html += '<a href="' + pageUrl + '">'
            + '<img src="' + p.thumbnail_url + '" '
            + 'style="width:100%;max-height:130px;object-fit:cover;border-radius:4px;margin-bottom:6px;display:block;" '
            + 'onerror="this.style.display=\'none\'">'
            + '</a>';
    }
    html += '<a href="' + pageUrl + '" style="font-weight:700;color:#1a73e8;text-decoration:none;font-size:14px;">'
          + _esc(p.name) + '</a>';
    if (p.album_name) html += '<div style="color:#555;margin-top:3px;font-size:12px;">&#128193; ' + _esc(p.album_name) + '</div>';
    if (p.date)       html += '<div style="color:#888;font-size:11px;margin-top:2px;">&#128197; ' + p.date + '</div>';
    html += '</div>';
    return html;
  }

  var _panelLoadTimer = null;

  function _updatePanelFromBounds() {
    if (!map) return;
    var bounds = map.getBounds();

    // Filtrer strictement les photos dans les bounds actuelles
    inViewPhotos = visiblePhotos.filter(function (p) {
      var lat = parseFloat(p.latitude);
      var lng = parseFloat(p.longitude);
      if (isNaN(lat) || isNaN(lng)) return false;
      return bounds.contains([lat, lng]);
    });
    _updateStats(inViewPhotos.length, visiblePhotos.length);

    // Panneau : 50 photos max, triées par distance au centre de la vue
    var MAX_PANEL = 200;
    var center = map.getCenter();
    var toShow = inViewPhotos.slice().sort(function(a, b) {
      var da = Math.pow(parseFloat(a.latitude) - center.lat, 2)
             + Math.pow(parseFloat(a.longitude) - center.lng, 2);
      var db = Math.pow(parseFloat(b.latitude) - center.lat, 2)
             + Math.pow(parseFloat(b.longitude) - center.lng, 2);
      return da - db;
    }).slice(0, MAX_PANEL);

    // Si aucune photo dans la vue, afficher un message
    if (!toShow.length) {
      var panelList = document.getElementById('osm-panel-list');
      if (panelList) panelList.innerHTML = '<div style="padding:16px;color:#888;font-size:12px;font-style:italic">Aucune photo dans cette zone.<br>Essayez de d\u00e9zoomer ou de changer l\u2019album.</div>';
      if (countEl) countEl.textContent = '0 photo visible';
      return;
    }

    // Si les photos ont déjà leurs détails (nom, url), afficher directement
    var needsLoad = toShow.filter(function(p) { return !p.name; });
    if (!needsLoad.length) {
      _renderPanel(toShow);
      return;
    }

    // Charger les détails manquants en batch, avec debounce 300ms
    clearTimeout(_panelLoadTimer);
    _panelLoadTimer = setTimeout(function() {
      var photoUrl = window.OSM_PHOTO_URL;
      if (!photoUrl) { _renderPanel(toShow); return; }

      var ids = needsLoad.map(function(p) { return p.id; }).join(',');
      fetch(photoUrl + '?ids=' + ids, { credentials: 'same-origin' })
        .then(function(r) { return r.json(); })
        .then(function(details) {
          // Mettre en cache les détails dans les objets photo
          var map_detail = {};
          details.forEach(function(d) { map_detail[d.id] = d; });
          toShow.forEach(function(p) {
            if (map_detail[p.id]) {
              p.name          = map_detail[p.id].name;
              p.date          = map_detail[p.id].date;
              p.album_name    = map_detail[p.id].album_name;
              p.thumbnail_url = map_detail[p.id].thumbnail_url;
              p.page_url      = map_detail[p.id].page_url;
            }
          });
          _renderPanel(toShow);
        })
        .catch(function() { _renderPanel(toShow); });
    }, 300);
  }

  function _renderPanel(photos) {
    var list    = document.getElementById('osm-panel-list');
    var countEl = document.getElementById('osm-panel-count');
    if (!list) return;

    if (countEl) {
      var total = inViewPhotos ? inViewPhotos.length : photos.length;
      var shown = photos.length;
      if (total > shown) {
        countEl.textContent = shown + ' / ' + total + ' photos — triées par distance';
      } else {
        countEl.textContent = shown + ' photo' + (shown > 1 ? 's' : '') + ' visibles';
      }
    }

    if (!photos.length) {
      list.innerHTML = '<p style="padding:12px;color:#888;font-size:13px;">Aucune photo dans cette zone.</p>';
      return;
    }

    var html = '';
    photos.forEach(function (p) {
      html += '<div class="osm-panel-item" id="osm-pi-' + p.id + '"'
            + ' data-lat="' + p.latitude + '" data-lon="' + p.longitude + '">';

      // Miniature — lien natif sans target="_blank" (compatible Safari localhost)
      html += '<a href="' + p.page_url + '" class="osm-pi-thumb-link">';
      html += '<div class="osm-pi-thumb">';
      if (p.thumbnail_url) {
        html += '<img src="' + p.thumbnail_url + '" alt="" loading="lazy"'
              + ' onerror="this.parentNode.innerHTML=\'&#128247;\'">';
      } else {
        html += '<span>&#128247;</span>';
      }
      html += '</div></a>';

      // Infos — lien natif
      html += '<a href="' + p.page_url + '" class="osm-pi-info">';
      html += '<span class="osm-pi-name">' + _esc(p.name) + '</span>';
      if (p.album_name) html += '<span class="osm-pi-album">&#128193; ' + _esc(p.album_name) + '</span>';
      if (p.date)       html += '<span class="osm-pi-date">&#128197; ' + p.date + '</span>';
      html += '</a>';

      html += '</div>';
    });

    list.innerHTML = html;

    // Clic sur le div wrapper (hors lien) → centre la carte
    list.querySelectorAll('.osm-panel-item').forEach(function (item) {
      var lat = parseFloat(item.dataset.lat);
      var lon = parseFloat(item.dataset.lon);
      item.addEventListener('click', function (e) {
        var node = e.target;
        while (node && node !== item) {
          if (node.tagName === 'A') return; // laisser le lien fonctionner
          node = node.parentNode;
        }
        map.setView([lat, lon], 16);
        clusterGroup.eachLayer(function (layer) {
          if (layer.getLatLng &&
              Math.abs(layer.getLatLng().lat - lat) < 0.0001 &&
              Math.abs(layer.getLatLng().lng - lon) < 0.0001) {
            layer.openPopup();
          }
        });
      });
    });
  }

  function _highlightPanel(photoId) {
    var item = document.getElementById('osm-pi-' + photoId);
    if (!item) return;
    var prev = document.querySelector('.osm-panel-item.active');
    if (prev) prev.classList.remove('active');
    item.classList.add('active');
    if (panelOpen) item.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
  }

  function _bindControls() {
    var sel = document.getElementById('osm-album-filter');
    if (sel) {
      sel.addEventListener('change', function () {
        var albumId = parseInt(this.value, 10) || null;
        var pointsBase = window.OSM_POINTS_URL;

        if (!pointsBase) {
          // Fallback page galerie : filtrage local sur album_id
          visiblePhotos = albumId
            ? allPhotos.filter(function (p) { return p.album_id === albumId; })
            : allPhotos.slice();
          map.off('moveend zoomend', _updatePanelFromBounds);
          _renderMarkers(visiblePhotos);
          setTimeout(function() {
            map.on('moveend zoomend', _updatePanelFromBounds);
            _updatePanelFromBounds();
          }, 200);
          return;
        }

        // Rechargement via API avec filtre cat_id
        var url = albumId ? pointsBase + '?cat_id=' + albumId : pointsBase;
        _showLoading(true);
        fetch(url, { credentials: 'same-origin' })
          .then(function(r) {
            if (!r.ok) throw new Error('HTTP ' + r.status);
            return r.text();
          })
          .then(function(text) {
            var trimmed = text.trim();
            if (!trimmed || trimmed[0] !== '[') throw new Error('Non-JSON');
            return JSON.parse(trimmed);
          })
          .then(function(data) {
            visiblePhotos = data.map(function(p) {
              return { id: p[0], latitude: p[1], longitude: p[2] };
            });
            // Recentrer sur la sélection
            var bounds = _computeBounds(visiblePhotos);
            if (bounds) {
              try {
                var b = L.latLngBounds(bounds[0], bounds[1]);
                if (b.isValid()) map.fitBounds(b.pad(0.1));
              } catch(e) {}
            }
            map.off('moveend zoomend', _updatePanelFromBounds);
            _renderMarkers(visiblePhotos);
            setTimeout(function() {
              map.on('moveend zoomend', _updatePanelFromBounds);
              _updatePanelFromBounds();
            }, 200);
            _showLoading(false);
          })
          .catch(function(err) {
            console.error('OSM Map: erreur filtre album', err);
            _showLoading(false);
          });
      });
    }

    var input = document.getElementById('osm-geocoder');
    var drop  = document.getElementById('osm-geocoder-results');
    if (input && drop) {
      input.addEventListener('input', function () {
        clearTimeout(geocodeTimer);
        var q = this.value.trim();
        if (q.length < 3) { drop.style.display = 'none'; return; }
        geocodeTimer = setTimeout(function () { _geocode(q, drop, input); }, 600);
      });
      document.addEventListener('click', function (e) {
        if (!input.contains(e.target) && !drop.contains(e.target)) drop.style.display = 'none';
      });
    }

    var btnFit = document.getElementById('osm-btn-fit');
    if (btnFit) {
      btnFit.addEventListener('click', function () {
        try { var b = clusterGroup.getBounds(); if (b.isValid()) map.fitBounds(b.pad(0.1)); } catch(e) {}
      });
    }

    var btnPanel = document.getElementById('osm-btn-panel');
    var btnClose = document.getElementById('osm-panel-close');
    var panel    = document.getElementById('osm-panel');
    function togglePanel() {
      panelOpen = !panelOpen;
      if (panel)    panel.classList.toggle('osm-panel-open', panelOpen);
      if (btnPanel) btnPanel.classList.toggle('active', panelOpen);
      setTimeout(function () { if (map) map.invalidateSize(); }, 300);
    }
    if (btnPanel) btnPanel.addEventListener('click', togglePanel);
    if (btnClose) btnClose.addEventListener('click', togglePanel);

    // Bouton créer album
    var btnAlbum = document.getElementById('osm-btn-album');
    if (btnAlbum && !window.OSM_IS_ADMIN) { btnAlbum.style.display = 'none'; }
    if (btnAlbum && window.OSM_IS_ADMIN) {
      btnAlbum.addEventListener('click', function () {
        if (!inViewPhotos || inViewPhotos.length === 0) {
          _showToast('Aucune photo visible sur la carte.', 'info');
          return;
        }
        _createAlbumModal();
      });
    }
  }

  function _geocode(query, drop, input) {
    fetch('https://nominatim.openstreetmap.org/search?format=json&limit=6&q=' + encodeURIComponent(query), {
      headers: { 'Accept-Language': 'fr' }
    })
    .then(function (r) { return r.json(); })
    .then(function (results) {
      if (!results || !results.length) {
        drop.innerHTML = '<div class="osm-geo-item osm-geo-empty">Aucun résultat</div>';
        drop.style.display = 'block'; return;
      }
      drop.innerHTML = results.map(function (r) {
        return '<div class="osm-geo-item" data-lat="' + r.lat + '" data-lon="' + r.lon + '">'
             + _esc(r.display_name) + '</div>';
      }).join('');
      drop.style.display = 'block';
      drop.querySelectorAll('.osm-geo-item[data-lat]').forEach(function (item) {
        item.addEventListener('click', function () {
          var lat = parseFloat(this.dataset.lat);
          var lon = parseFloat(this.dataset.lon);
          drop.style.display = 'none';
          input.value = this.textContent;
          map.setView([lat, lon], 12);
          // Forcer la mise à jour du panneau après que la vue soit stabilisée
          setTimeout(function() { _updatePanelFromBounds(); }, 400);
        });
      });
    })
    .catch(function () {
      drop.innerHTML = '<div class="osm-geo-item osm-geo-empty">Erreur géocodage</div>';
      drop.style.display = 'block';
    });
  }

  function _updateStats(visible, total) {
    var v = document.getElementById('osm-count-visible');
    var t = document.getElementById('osm-count-total');
    if (v) v.textContent = visible;
    if (t && total !== undefined) t.textContent = total;
  }

  function _esc(s) {
    return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
  }


  // ── Création d'album ───────────────────────────────────────────────────────
  function _createAlbumModal() {
    // Supprimer modal existante
    var old = document.getElementById('osm-album-modal');
    if (old) old.remove();

    var today = new Date();
    var dateStr = today.getFullYear() + '-'
      + String(today.getMonth()+1).padStart(2,'0') + '-'
      + String(today.getDate()).padStart(2,'0');
    var defaultName = 'Carte ' + dateStr + ' (' + inViewPhotos.length + ' photos)';

    var modal = document.createElement('div');
    modal.id = 'osm-album-modal';
    modal.innerHTML = [
      '<div id="osm-album-overlay"></div>',
      '<div id="osm-album-dialog">',
      '  <h3>&#128194; Créer un album</h3>',
      '  <p id="osm-album-desc"><strong>' + inViewPhotos.length + '</strong> photos visibles seront ajoutées.</p>',
      '  <label for="osm-album-name">Nom de l&#39;album</label>',
      '  <input type="text" id="osm-album-name" value="' + defaultName + '" />',
      '  <div id="osm-album-progress" style="display:none;">',
      '    <div id="osm-album-bar"><div id="osm-album-bar-inner"></div></div>',
      '    <span id="osm-album-status">Création en cours…</span>',
      '  </div>',
      '  <div id="osm-album-actions">',
      '    <button id="osm-album-cancel">Annuler</button>',
      '    <button id="osm-album-confirm">&#10003; Créer</button>',
      '  </div>',
      '</div>'
    ].join('');
    document.body.appendChild(modal);

    document.getElementById('osm-album-overlay').addEventListener('click', function () { modal.remove(); });
    document.getElementById('osm-album-cancel').addEventListener('click', function () { modal.remove(); });
    document.getElementById('osm-album-confirm').addEventListener('click', function () {
      var name = document.getElementById('osm-album-name').value.trim();
      if (!name) { document.getElementById('osm-album-name').focus(); return; }
      _doCreateAlbum(name, modal);
    });
    // Focus sur le champ
    setTimeout(function () {
      var inp = document.getElementById('osm-album-name');
      if (inp) { inp.focus(); inp.select(); }
    }, 50);
  }

  // Parse réponse Piwigo : JSON ou XML selon la version
  function _parseResponse(text) {
    text = (text || '').trim();
    if (text.charAt(0) === '{') {
      var d = JSON.parse(text);
      if (d.stat !== 'ok') throw new Error(d.message || 'Erreur API');
      return d.result;
    }
    // Fallback XML
    var parser = new DOMParser();
    var xml    = parser.parseFromString(text, 'text/xml');
    var rsp    = xml.querySelector('rsp');
    if (!rsp || rsp.getAttribute('stat') !== 'ok') {
      var err = xml.querySelector('err');
      throw new Error(err ? err.getAttribute('msg') : 'Erreur API (XML)');
    }
    var result = {};
    Array.from(rsp.children).forEach(function (el) {
      result[el.tagName] = el.textContent.trim();
    });
    return result;
  }

  function _doCreateAlbum(name, modal) {
    var actions  = document.getElementById('osm-album-actions');
    var progress = document.getElementById('osm-album-progress');
    var status   = document.getElementById('osm-album-status');
    var barInner = document.getElementById('osm-album-bar-inner');
    if (actions)  actions.style.display  = 'none';
    if (progress) progress.style.display = 'block';

    function setStatus(msg, pct) {
      if (status)   status.textContent     = msg;
      if (barInner) barInner.style.width   = (pct || 0) + '%';
    }

    // Étape 1 : créer la catégorie via ws.php
    setStatus('Création de l&#39;album…', 10);
    var params1 = new URLSearchParams();
    params1.append('method', 'pwg.categories.add');
    params1.append('format', 'json');
    params1.append('name',   name);

    fetch(apiUrl, { method: 'POST', body: params1 })
    .then(function (r) { return r.text(); })
    .then(function (text) {
      var data;
      var result = _parseResponse(text);

      var catId = result.id;
      if (!catId) throw new Error('ID album non reçu dans la réponse');
      var photoIds = inViewPhotos.map(function (p) { return p.id; });

      setStatus('Ajout de ' + photoIds.length + ' photos…', 30);

      // Étape 2 : associer les photos à la catégorie par lots de 100
      var BATCH = 100;
      var batches = [];
      for (var i = 0; i < photoIds.length; i += BATCH) {
        batches.push(photoIds.slice(i, i + BATCH));
      }

      var done = 0;
      function nextBatch() {
        if (done >= batches.length) {
          setStatus('Album créé avec succès !', 100);
          setTimeout(function () {
            modal.remove();
            _showToast('Album "' + name + '" créé avec ' + photoIds.length + ' photos !', 'success');
          }, 200);
          return;
        }
        var batch  = batches[done];
        var params2 = new URLSearchParams();
        params2.append('method',       'pwg.images.addSimple');
        params2.append('format',       'json');
        params2.append('category',     catId);
        batch.forEach(function (id) { params2.append('image_id[]', id); });

        // pwg.images.addSimple ne supporte pas le batch — utiliser setInfo pour chaque photo
        // On utilise pwg.images.setInfo avec categories
        var params3 = new URLSearchParams();
        params3.append('method',  'pwg.images.setInfo');
        params3.append('format',  'json');
        params3.append('image_id', batch[0]);
        params3.append('categories', catId);

        // Utiliser l'API batch native : pwg.images.addToAlbum si disponible
        // Sinon : appel séquentiel pwg.images.setInfo
        var params4 = new URLSearchParams();
        params4.append('method',      'pwg.categories.setInfo');
        params4.append('format',      'json');
        params4.append('category_id', catId);

        // La vraie méthode pour associer plusieurs images : IMAGE_CATEGORY_TABLE direct
        // Via API : pwg.images.add ne convient pas. On utilise la méthode la plus compatible :
        // POST sur pwg.images.setInfo image par image est trop lent
        // MEILLEURE approche : pwg.images.addToAlbum (Piwigo 2.7+)
        var paramsAdd = new URLSearchParams();
        paramsAdd.append('method',      'pwg.images.addToAlbum');
        paramsAdd.append('format',      'json');
        paramsAdd.append('category_id', catId);
        batch.forEach(function (id) { paramsAdd.append('image_id[]', id); });

        fetch(apiUrl, { method: 'POST', body: paramsAdd })
        .then(function (r) { return r.text(); })
        .then(function (text) {
          var res;
          try { res = JSON.parse(text); } catch(e) { res = {}; }
          // Si méthode inconnue, fallback sur setInfo individuel
          if (!res || res.stat !== 'ok') {
            return _addPhotosOneByOne(batch, catId, done, batches.length, setStatus, nextBatch);
          }
          done++;
          setStatus('Ajout… ' + Math.min(done * BATCH, photoIds.length) + '/' + photoIds.length, 30 + (done/batches.length)*65);
          nextBatch();
        })
        .catch(function () {
          _addPhotosOneByOne(batch, catId, done, batches.length, setStatus, nextBatch);
        });
        done++;
      }
      nextBatch();
    })
    .catch(function (err) {
      setStatus('Erreur : ' + err.message, 0);
      if (actions) actions.style.display = 'flex';
    });
  }

  // Fallback : ajouter les photos une par une via pwg.images.setInfo
  function _addPhotosOneByOne(batch, catId, batchIdx, totalBatches, setStatus, onDone) {
    var idx = 0;
    function next() {
      if (idx >= batch.length) { onDone(); return; }
      var params = new URLSearchParams();
      params.append('method',      'pwg.images.setInfo');
      params.append('format',      'json');
      params.append('image_id',    batch[idx]);
      params.append('categories',  catId);
      params.append('multiple_value_mode', 'append');
      fetch(apiUrl, { method: 'POST', body: params })
      .then(function () { idx++; next(); })
      .catch(function () { idx++; next(); });
    }
    next();
  }

  function _showToast(msg, type) {
    var toast = document.createElement('div');
    toast.className = 'osm-toast osm-toast-' + (type || 'info');
    toast.textContent = msg;
    document.body.appendChild(toast);
    setTimeout(function () {
      toast.style.opacity = '0';
      setTimeout(function () { toast.remove(); }, 400);
    }, 3500);
  }

  return { init: init };
})();
