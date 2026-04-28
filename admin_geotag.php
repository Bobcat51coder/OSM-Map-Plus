<?php
/*
 * This file is part of [OSM-Map-Plus].
 *
 * [Nom de ton plugin] is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * [Nom de ton plugin] is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with [Nom de ton plugin].  If not, see <https://www.gnu.org/licenses/>.
 */
/**
 * OSM Map Enhanced - Onglet géotaggage édition photo individuelle
 * Bobcat-Fr / Claude (Anthropic)
 */
if (!defined('PHPWG_ROOT_PATH')) {
    define('PHPWG_ROOT_PATH', realpath(dirname(__FILE__) . '/../..') . '/');
}
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');
if (!is_admin()) { redirect(make_index_url()); }

$image_id = isset($_GET['image_id']) ? (int)$_GET['image_id'] : 0;
if (!$image_id) die('image_id manquant');

// ── Sauvegarde ────────────────────────────────────────────────────────────
$status_msg = '';
if (isset($_POST['osm_save_geotag'])) {
    $lat = $_POST['osm_lat'] !== '' ? (float)$_POST['osm_lat'] : null;
    $lon = $_POST['osm_lon'] !== '' ? (float)$_POST['osm_lon'] : null;

    if ($lat !== null && $lon !== null) {
        pwg_query('UPDATE ' . IMAGES_TABLE . ' SET latitude=' . $lat . ', longitude=' . $lon . ' WHERE id=' . $image_id);
        $status_msg = '✓ Coordonnées enregistrées : ' . $lat . ', ' . $lon;
    } else {
        pwg_query('UPDATE ' . IMAGES_TABLE . ' SET latitude=NULL, longitude=NULL WHERE id=' . $image_id);
        $status_msg = '✓ Coordonnées GPS supprimées.';
    }
}

// ── Lecture photo ─────────────────────────────────────────────────────────
$res = pwg_query('SELECT id, name, file, latitude, longitude FROM ' . IMAGES_TABLE . ' WHERE id=' . $image_id);
$photo = pwg_db_fetch_assoc($res);
if (!$photo) die('Photo introuvable');

$lat  = $photo['latitude']  ? (float)$photo['latitude']  : '';
$lon  = $photo['longitude'] ? (float)$photo['longitude'] : '';
$name = $photo['name'] ? $photo['name'] : pathinfo($photo['file'], PATHINFO_FILENAME);
$root = get_root_url();
$plg  = $root . 'plugins/osm_map/';
?>
<link rel="stylesheet" href="<?= $plg ?>css/geotag.css?v=<?= OSM_MAP_VERSION ?>">

<div id="osm-edit-section">
  <h3 style="margin:0 0 14px;">&#128506; Géotaggage — <?= htmlspecialchars($name) ?></h3>

  <!-- Géocodeur -->
  <div id="osm-edit-geocoder-wrap">
    <input type="text" id="osm-edit-geocoder" placeholder="Rechercher un lieu et appuyer sur Entrée…" autocomplete="off">
  </div>

  <!-- Carte -->
  <div id="osm-edit-map"></div>

  <!-- Formulaire coords -->
  <form method="post" action="">
    <div class="osm-edit-coords">
      <label>Latitude&nbsp;:
        <input type="text" id="osm-edit-lat" name="osm_lat" value="<?= $lat ?>" placeholder="ex: 48.8566">
      </label>
      <label>Longitude&nbsp;:
        <input type="text" id="osm-edit-lon" name="osm_lon" value="<?= $lon ?>" placeholder="ex: 2.3522">
      </label>
    </div>
    <button type="submit" name="osm_save_geotag" id="osm-edit-save">&#10003; Enregistrer</button>
    <button type="button" id="osm-edit-clear">&#10005; Effacer GPS</button>
    <div id="osm-edit-status"><?= htmlspecialchars($status_msg) ?></div>
  </form>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
  if (typeof OSMGeotag !== 'undefined') {
    OSMGeotag.initPhotoEdit(
      <?= $image_id ?>,
      <?= $lat !== '' ? $lat : 'null' ?>,
      <?= $lon !== '' ? $lon : 'null' ?>,
      '<?= $root ?>ws.php'
    );
  }

  // Bouton effacer
  document.getElementById('osm-edit-clear').addEventListener('click', function() {
    if (!confirm('Supprimer les coordonnées GPS de cette photo ?')) return;
    document.getElementById('osm-edit-lat').value = '';
    document.getElementById('osm-edit-lon').value = '';
  });

  // Sync champs texte → marker
  ['osm-edit-lat','osm-edit-lon'].forEach(function(id) {
    document.getElementById(id).addEventListener('change', function() {
      var lat = parseFloat(document.getElementById('osm-edit-lat').value);
      var lon = parseFloat(document.getElementById('osm-edit-lon').value);
      // Signaler au module geotag (si marker créé)
    });
  });
});
</script>
