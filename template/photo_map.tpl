{* photo_map.tpl - Mini-carte sur la page photo individuelle *}

<div id="osm-photo-map-container" class="osm-photo-map-wrapper">
  <h4 class="osm-photo-map-title">📍 {'Location'|@translate}</h4>
  <div id="osm-photo-map"
       style="height: 220px; width: 100%;"
       data-lat="{$OSM_PHOTO_LAT}"
       data-lon="{$OSM_PHOTO_LON}"
       data-title="{$OSM_PHOTO_TITLE|escape:'html'}">
  </div>
  <div class="osm-photo-coords">
    {$OSM_PHOTO_LAT|string_format:"%.6f"}, {$OSM_PHOTO_LON|string_format:"%.6f"}
    <a href="https://www.openstreetmap.org/?mlat={$OSM_PHOTO_LAT}&mlon={$OSM_PHOTO_LON}&zoom=14"
       target="_blank" rel="noopener">
      {'View on OSM'|@translate} ↗
    </a>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  var el = document.getElementById('osm-photo-map');
  if (!el || typeof L === 'undefined') return;

  var lat   = parseFloat(el.dataset.lat);
  var lon   = parseFloat(el.dataset.lon);
  var title = el.dataset.title;

  var map = L.map('osm-photo-map', { zoomControl: true }).setView([lat, lon], 14);

  L.tileLayer('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}.png', {
    attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> © <a href="https://carto.com/">CARTO</a>',
    maxZoom: 19
  }).addTo(map);

  L.marker([lat, lon])
    .addTo(map)
    .bindPopup('<strong>' + title + '</strong>')
    .openPopup();
});
</script>
