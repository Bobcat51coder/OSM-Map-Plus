<!DOCTYPE html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex,nofollow">
<title>{$GALLERY_TITLE}</title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/MarkerCluster.css">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css">
<link rel="stylesheet" href="{$ROOT_URL}plugins/osm_map/css/map.css?v=2.5.3">
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>
<style>
{literal}
* { box-sizing: border-box; margin: 0; padding: 0; }
body { font-family: sans-serif; background: #f5f5f5; }
#osmw-topbar {
  background: #fff; border-bottom: 1px solid #ddd;
  padding: 8px 14px; display: flex; align-items: center;
  gap: 10px; flex-wrap: wrap; position: sticky; top: 0; z-index: 1000;
}
#osmw-back {
  padding: 6px 12px; background: #1a73e8; color: #fff;
  border: none; border-radius: 4px; cursor: pointer;
  text-decoration: none; font-size: 0.85rem;
}
/* Page mondiale uniquement — hauteur plein écran */
#osmw-page .osm-map-wrapper { height: calc(100vh - 48px); }
#osmw-page .osm-body { height: calc(100vh - 48px - 44px - 32px); }
#osmw-page #osm-map { height: 100% !important; }
{/literal}
</style>
</head>
<body>

<div id="osmw-page">
<div id="osmw-topbar">
  <a id="osmw-back" href="{$HOME}">&#8592; Galerie</a>
  <strong>&#127760; OSM Map Plus</strong>
  <span style="color:#666;font-size:0.85rem;">{$NB_PHOTOS} photos géolocalisées</span>
</div>

{* Injecter les photos AVANT map_block.tpl qui appelle OSMMap.init *}
{$PHOTOS_SCRIPT nofilter}

{* Inclure exactement le même bloc que la page d'accueil *}
{include file=$MAP_BLOCK_TPL}

</div><!-- /osmw-page -->
</body>
</html>
