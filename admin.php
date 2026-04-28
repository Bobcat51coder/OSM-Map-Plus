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
// OSM Map Enhanced - Page d'administration
// Inclus par Piwigo via get_admin_plugin_menu_links → admin.php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');
check_status(ACCESS_ADMINISTRATOR);

$page['title'] = 'OSM Map Plus';
$errors = array();
$infos  = array();

// ── Sauvegarde ────────────────────────────────────────────────────────────
$tile_options = array(
    'carto_voyager'  => 'CartoDB Voyager (défaut)',
    'osm_standard'   => 'OpenStreetMap Standard',
    'esri_satellite' => 'Satellite (Esri)',
    'opentopo'       => 'Topographique (OpenTopo)',
);

if (isset($_POST['submit_osm'])) {
    $public     = !empty($_POST['osm_map_public']) ? '1' : '0';
    $height     = max(200, min(1200, (int)$_POST['osm_map_height']));
    $max_photos = max(0, min(50000, (int)$_POST['osm_map_max_photos']));
    if (isset($_POST['osm_map_plus_height'])) {
        conf_update_param('osm_map_plus_height', max(300, min(2000, (int)$_POST['osm_map_plus_height'])));
    }
    $zoom       = max(1,   min(18,   (int)$_POST['osm_map_zoom']));
    $tile       = isset($tile_options[$_POST['osm_map_tile']])        ? $_POST['osm_map_tile']        : 'carto_voyager';
    $tile_geotag= isset($tile_options[$_POST['osm_map_tile_geotag']]) ? $_POST['osm_map_tile_geotag'] : 'carto_voyager';

    conf_update_param('osm_map_public',     $public);
    conf_update_param('osm_map_height',     $height);
    conf_update_param('osm_map_max_photos', $max_photos);
    conf_update_param('osm_map_zoom',       $zoom);
    conf_update_param('osm_map_tile',       $tile);
    conf_update_param('osm_map_tile_geotag',$tile_geotag);
    conf_update_param('osm_map_hide_if_osm', !empty($_POST['osm_map_hide_if_osm']) ? '1' : '0');

    $infos[] = 'Options enregistrées.';
}

// ── Lecture config ────────────────────────────────────────────────────────
$osm_public      = (isset($conf['osm_map_public'])      && $conf['osm_map_public'] == '1');
$osm_height      = isset($conf['osm_map_height'])      ? (int)$conf['osm_map_height']      : 500;
$osm_max_photos  = isset($conf['osm_map_max_photos'])  ? (int)$conf['osm_map_max_photos']  : 5000;
$osm_plus_height = isset($conf['osm_map_plus_height']) ? (int)$conf['osm_map_plus_height'] : 600;
$osm_zoom        = isset($conf['osm_map_zoom'])        ? (int)$conf['osm_map_zoom']        : 5;
$osm_tile        = isset($conf['osm_map_tile'])        ? $conf['osm_map_tile']             : 'carto_voyager';
$osm_tile_geotag  = isset($conf['osm_map_tile_geotag']) ? $conf['osm_map_tile_geotag']      : 'carto_voyager';
$osm_hide_if_osm  = isset($conf['osm_map_hide_if_osm'])  && $conf['osm_map_hide_if_osm'] == '1';

// ── Stats ─────────────────────────────────────────────────────────────────
$res      = pwg_query('SELECT COUNT(*) AS nb FROM ' . IMAGES_TABLE . ' WHERE latitude IS NOT NULL AND latitude <> 0');
$nb_gps   = (int)pwg_db_fetch_assoc($res)['nb'];
$res2     = pwg_query('SELECT COUNT(*) AS nb FROM ' . IMAGES_TABLE);
$nb_total = (int)pwg_db_fetch_assoc($res2)['nb'];

ob_start();
?>

<div class="titrePage">
  <h2>OSM Map Plus &mdash; Configuration <span style="font-size:0.6em;font-weight:normal;color:#888;margin-left:12px;">v<?= OSM_MAP_VERSION ?></span></h2>
</div>

<?php if (!empty($infos)): ?>
<div class="infos"><ul><?php foreach ($infos as $m): ?><li><?= htmlspecialchars($m) ?></li><?php endforeach ?></ul></div>
<?php endif ?>
<?php if (!empty($errors)): ?>
<div class="errors"><ul><?php foreach ($errors as $m): ?><li><?= htmlspecialchars($m) ?></li><?php endforeach ?></ul></div>
<?php endif ?>

<style>
#osm-admin-wrap { max-width:860px; margin:0 auto; padding:0 20px 40px 20px; box-sizing:border-box; }
.osm-admin-section { background:#fff; border:1px solid #d0d0d0; border-radius:4px; margin-bottom:20px; padding:0; }
.osm-admin-section h3 { margin:0; padding:10px 16px; background:#f5f5f5; border-bottom:1px solid #d0d0d0; font-size:1rem; border-radius:4px 4px 0 0; }
.osm-admin-row { display:flex; align-items:flex-start; padding:14px 16px; border-bottom:1px solid #ebebeb; gap:20px; }
.osm-admin-row:last-child { border-bottom:none; }
.osm-admin-label { flex:0 0 260px; min-width:0; }
.osm-admin-label strong { display:block; margin-bottom:4px; }
.osm-admin-label .description { color:#666; font-size:0.85em; line-height:1.4; margin:0; }
.osm-admin-field { flex:1; padding-top:2px; min-width:0; }
.osm-admin-field input[type=number] { width:90px; padding:4px 6px; border:1px solid #ccc; border-radius:3px; }
.osm-admin-stats { display:flex; gap:40px; padding:16px; flex-wrap:wrap; }
.osm-admin-stat { text-align:center; }
.osm-admin-stat .val { font-size:1.8em; font-weight:700; color:#1a73e8; line-height:1; }
.osm-admin-stat .lbl { font-size:0.82em; color:#666; margin-top:4px; }
</style>

<div id="osm-admin-wrap">
<!-- Stats -->
<div class="osm-admin-section">
  <h3>📊 Statistiques</h3>
  <div class="osm-admin-stats">
    <div class="osm-admin-stat">
      <div class="val"><?= $nb_total ?></div>
      <div class="lbl">Photos totales</div>
    </div>
    <div class="osm-admin-stat">
      <div class="val"><?= $nb_gps ?></div>
      <div class="lbl">Géolocalisées (GPS)</div>
    </div>
    <?php if ($nb_total > 0): ?>
    <div class="osm-admin-stat">
      <div class="val"><?= round($nb_gps / $nb_total * 100) ?>%</div>
      <div class="lbl">Taux GPS</div>
    </div>
    <?php endif ?>
  </div>
</div>

<!-- Options -->
<form method="post" action="">
<div class="osm-admin-section">
  <h3>⚙️ Options de la carte</h3>

  <!-- Affichage public -->
  <div class="osm-admin-row">
    <div class="osm-admin-label">
      <strong>Affichage public</strong>
      <p class="description">Si activé, la carte est visible par les visiteurs non connectés, uniquement avec les photos des albums publics.</p>
    </div>
    <div class="osm-admin-field">
      <input type="hidden" name="osm_map_public" value="0">
      <label>
        <input type="checkbox" name="osm_map_public" value="1" <?= $osm_public ? 'checked' : '' ?>>
        Autoriser l'affichage public
      </label>
    </div>
  </div>

  <!-- Hauteur -->
  <div class="osm-admin-row">
    <div class="osm-admin-label">
      <strong>Hauteur de la carte</strong>
      <p class="description">En pixels. Min : 200px, Max : 1200px.</p>
    </div>
    <div class="osm-admin-field">
      <input type="number" name="osm_map_height" value="<?= $osm_height ?>" min="200" max="1200" step="50"> px
    </div>
  </div>

  <!-- Max photos -->
  <div class="osm-admin-row">
    <div class="osm-admin-label">
      <strong>Hauteur carte mondiale (OSM Map Plus)</strong>
      <p class="description">Hauteur en pixels de la carte sur la page OSM Map Plus. Min : 300, Max : 2000.</p>
    </div>
    <div class="osm-admin-field">
      <input type="number" name="osm_map_plus_height" value="<?= $osm_plus_height ?>" min="300" max="2000" step="50"> px
    </div>
  </div>

  <div class="osm-admin-row">
    <div class="osm-admin-label">
      <strong>Nombre max de photos</strong>
      <p class="description">Limite de photos chargées sur la carte galerie (0 = illimité). La carte mondiale charge toujours tout via API.<?php if ($nb_gps > $osm_max_photos): ?><br><span style="color:#c00;">⚠ <?= $nb_gps ?> photos GPS dispo, seules <?= $osm_max_photos ?> affichées.</span><?php endif ?></p>
    </div>
    <div class="osm-admin-field">
      <input type="number" name="osm_map_max_photos" value="<?= $osm_max_photos ?>" min="0" max="50000" step="1"> photos
    </div>
  </div>

  <!-- Zoom -->
  <div class="osm-admin-row">
    <div class="osm-admin-label">
      <strong>Zoom initial</strong>
      <p class="description">Niveau de zoom au chargement (1 = monde entier, 18 = détail rue). La carte se recentre automatiquement sur les photos.</p>
    </div>
    <div class="osm-admin-field">
      <input type="number" id="osm_zoom" name="osm_map_zoom" value="<?= $osm_zoom ?>" min="1" max="18">
      <span id="osm-zoom-lbl" style="margin-left:8px;color:#555;font-style:italic;"></span>
      <script>
      (function(){
        var L={1:'Monde',4:'Continent',6:'Pays',8:'Région',10:'Ville',12:'Quartier',14:'Rue',16:'Bâtiment',18:'Détail'};
        var inp=document.getElementById('osm_zoom'), lbl=document.getElementById('osm-zoom-lbl');
        function u(){ var v=parseInt(inp.value),k=Object.keys(L).reverse().find(function(k){return v>=k;}); lbl.textContent=k?'← '+L[k]:''; }
        inp.addEventListener('input',u); u();
      })();
      </script>
    </div>
  </div>

  <!-- Coexistence avec piwigo-openstreetmap -->
  <div class="osm-admin-row">
    <div class="osm-admin-label">
      <strong>Masquer si OSM natif actif</strong>
      <p class="description">Si le plugin piwigo-openstreetmap est actif, masquer notre carte sur la page d'accueil (évite d'avoir deux cartes).</p>
    </div>
    <div class="osm-admin-field">
      <input type="hidden" name="osm_map_hide_if_osm" value="0">
      <label>
        <input type="checkbox" name="osm_map_hide_if_osm" value="1" <?= $osm_hide_if_osm ? 'checked' : '' ?>>
        Masquer notre carte si piwigo-openstreetmap est actif
      </label>
    </div>
  </div>

  <!-- Fond de carte galerie -->
  <div class="osm-admin-row">
    <div class="osm-admin-label">
      <strong>Fond de carte — Galerie</strong>
      <p class="description">Carte affichée sur la galerie publique.</p>
    </div>
    <div class="osm-admin-field">
      <select name="osm_map_tile">
        <?php foreach ($tile_options as $k => $v): ?>
        <option value="<?= $k ?>" <?= $k === $osm_tile ? 'selected' : '' ?>><?= $v ?></option>
        <?php endforeach ?>
      </select>
    </div>
  </div>

  <!-- Fond de carte géotaggage -->
  <div class="osm-admin-row">
    <div class="osm-admin-label">
      <strong>Fond de carte — Géotaggage</strong>
      <p class="description">Carte utilisée dans l'édition photo et le mode unitaire batch.</p>
    </div>
    <div class="osm-admin-field">
      <select name="osm_map_tile_geotag">
        <?php foreach ($tile_options as $k => $v): ?>
        <option value="<?= $k ?>" <?= $k === $osm_tile_geotag ? 'selected' : '' ?>><?= $v ?></option>
        <?php endforeach ?>
      </select>
    </div>
  </div>

</div>

<p><input type="hidden" name="submit_osm" value="1"><input class="submit" type="submit" value="💾 Enregistrer"></p>
</form>
</div><!-- /osm-admin-wrap -->
<?php
$_admin_content = ob_get_clean();
global $template;
$template->assign('OSM_ADMIN_HTML', $_admin_content);
$template->set_filenames(array('plugin_admin_content' => dirname(__FILE__).'/template/admin.tpl'));
$template->assign_var_from_handle('ADMIN_CONTENT', 'plugin_admin_content');
