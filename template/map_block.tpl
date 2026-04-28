{* map_block.tpl - Bloc carte OSM pour la galerie Piwigo *}
{* Compatible tous thèmes via hooks universels               *}

<div id="osm-map-container" class="osm-map-wrapper">

  {* ── En-tête / contrôles ── *}
  <div class="osm-header">
    <h3 class="osm-title">
      <span class="osm-icon">🗺️</span>
      {'Map'|@translate}
    </h3>

    <div class="osm-controls">

      {* Filtre album *}
      <div class="osm-control-group">
        <label for="osm-album-filter">{'Choix album'|@translate}</label>
        <select id="osm-album-filter">
          <option value="">— {'Tous les albums'|@translate} —</option>
          {foreach from=$OSM_ALBUMS item=album}
            <option value="{$album.id}">
              {$album.display_name|default:$album.name}
            </option>
          {/foreach}
        </select>
      </div>

      {* Géocodeur *}
      <div class="osm-control-group osm-geocoder-group">
        <label for="osm-geocoder">{'Search'|@translate}</label>
        <div class="osm-geocoder-wrapper">
          <input type="text"
                 id="osm-geocoder"
                 placeholder="{'Search a location...'|@translate}"
                 autocomplete="off" />
          <div id="osm-geocoder-results" class="osm-geocoder-dropdown"></div>
        </div>
      </div>

      {* Boutons action *}
      <div class="osm-control-group osm-actions">
        <button id="osm-btn-fit">↗ {'Recentrer'|@translate}</button>
        <button id="osm-btn-panel">☰ {'Liste'|@translate}</button>
        <button id="osm-btn-album">&#128193; {'Créer album'|@translate}</button>
      </div>

    </div>{* /osm-controls *}
  </div>{* /osm-header *}

  {* ── Corps : carte + panneau latéral ── *}
  <div class="osm-body">

    {* Carte Leaflet *}
    <div id="osm-map"
         style="height: {$OSM_MAP_HEIGHT}px;"
         data-api-url="{$OSM_API_URL}">
    </div>

    {* Panneau liste photos (masqué par défaut) *}
    <div id="osm-panel" class="osm-panel osm-panel-hidden">
      <div class="osm-panel-header">
        <span id="osm-panel-title">Photos</span>
        <button id="osm-panel-close">✕</button>
      </div>
      <div id="osm-panel-list" class="osm-panel-list">
        {* Rempli dynamiquement par JS *}
      </div>
    </div>

  </div>{* /osm-body *}

  {* ── Pied de bloc : statistiques ── *}
  <div class="osm-footer">
    <span class="osm-stats">
      <span id="osm-count-visible">0</span>
      {'photos displayed'|@translate} /
      <span id="osm-count-total">0</span>
      {'total'|@translate}
    </span>
    <span id="osm-loading" class="osm-loading" style="display:none;">
      ⏳ {'Loading...'|@translate}
    </span>
  </div>

</div>{* /osm-map-container *}

{* ── Script d'initialisation ── *}
<script src="{$OSM_PLUGIN_PATH}js/map.js?v={$OSM_MAP_VERSION}"></script>
<script>
  document.addEventListener('DOMContentLoaded', function() {
    OSMMap.init({
      apiUrl:    '{$OSM_API_URL}',
      mapHeight: {$OSM_MAP_HEIGHT},
      tile:      '{$OSM_TILE}',
      initZoom:  {$OSM_INIT_ZOOM}
    });
  });
</script>
