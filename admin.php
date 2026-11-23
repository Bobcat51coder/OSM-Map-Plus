<?php
// +-----------------------------------------------------------------------+
// | This file is part of Piwigo.                                          |
// | This file is part of OSM Map Plus.                                                                     |
// | For copyright and license information, please view the COPYING.txt    |
// | file that was distributed with this source code.                      |
// +-----------------------------------------------------------------------+

// OSM Map Enhanced - Page d'administration
// Inclus par Piwigo via get_admin_plugin_menu_links → admin.php
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');
check_status(ACCESS_ADMINISTRATOR);
include_once(dirname(__FILE__) . '/include/admin_tabs.inc.php');

$page['title'] = 'OSM Map Plus';
$errors = array();
$infos  = array();

// ── Sauvegarde ────────────────────────────────────────────────────────────
$tile_options = array(
    'carto_voyager'  => 'CartoDB Voyager (défaut)',
    'osm_standard'   => 'OpenStreetMap Standard',
    'esri_satellite' => 'Satellite (Esri)',
    'opentopo'       => 'Topographique (OpenTopo)',
    'osm_france'     => 'OpenStreetMap France',
);
// Fonds proposés pour la galerie : + fonds vectoriels (WebGL), le visiteur peut en changer sur la carte
$tile_options_gallery = $tile_options + array(
    'openfreemap'    => 'OpenFreeMap',
    'sat_labels'     => 'Satellite + noms (OpenFreeMap)',
);

if (isset($_POST['submit_osm'])) {
    $public     = !empty($_POST['osm_map_public']) ? '1' : '0';
    $height     = max(200, min(1200, (int)$_POST['osm_map_height']));
    $max_photos = max(0, min(50000, (int)$_POST['osm_map_max_photos']));
    if (isset($_POST['osm_map_plus_height'])) {
        conf_update_param('osm_map_plus_height', max(300, min(2000, (int)$_POST['osm_map_plus_height'])));
    }
    $zoom       = max(1,   min(18,   (int)$_POST['osm_map_zoom']));
    $tile       = isset($tile_options_gallery[$_POST['osm_map_tile']]) ? $_POST['osm_map_tile']        : 'carto_voyager';
    $tile_geotag= isset($tile_options_gallery[$_POST['osm_map_tile_geotag']]) ? $_POST['osm_map_tile_geotag'] : 'carto_voyager';

    conf_update_param('osm_map_public',     $public);
    conf_update_param('osm_map_height',     $height);
    conf_update_param('osm_map_max_photos', $max_photos);
    conf_update_param('osm_map_zoom',       $zoom);
    conf_update_param('osm_map_tile',       $tile);
    conf_update_param('osm_map_tile_geotag',$tile_geotag);
    $tile_edit = isset($tile_options_gallery[$_POST['osm_map_tile_edit'] ?? '']) ? $_POST['osm_map_tile_edit'] : 'carto_voyager';
    conf_update_param('osm_map_tile_edit',  $tile_edit);
    conf_update_param('osm_map_edit_height', max(200, min(1000, (int)($_POST['osm_map_edit_height'] ?? 400))));
    $tile_photo = isset($tile_options_gallery[$_POST['osm_map_tile_photo'] ?? '']) ? $_POST['osm_map_tile_photo'] : 'carto_voyager';
    conf_update_param('osm_map_tile_photo', $tile_photo);
    conf_update_param('osm_map_photo_height', max(150, min(800, (int)($_POST['osm_map_photo_height'] ?? 220))));
    conf_update_param('osm_map_unit_height', max(150, min(800,  (int)($_POST['osm_map_unit_height'] ?? 280))));
    // champ désactivé (absent du POST) quand piwigo-openstreetmap est actif : on garde alors le choix enregistré
    if (isset($_POST['osm_map_position'])) {
        conf_update_param('osm_map_position', in_array($_POST['osm_map_position'], array('top', 'bottom', 'none'), true) ? $_POST['osm_map_position'] : 'bottom');
    }
    conf_update_param('osm_map_hide_if_osm', !empty($_POST['osm_map_hide_if_osm']) ? '1' : '0');
    conf_update_param('osm_map_carto_api_key', trim($_POST['osm_map_carto_api_key'] ?? ''));

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
$osm_tile_photo   = isset($conf['osm_map_tile_photo'])   ? $conf['osm_map_tile_photo']       : 'carto_voyager';
$osm_photo_height = isset($conf['osm_map_photo_height']) ? (int)$conf['osm_map_photo_height'] : 220;
$osm_tile_edit    = isset($conf['osm_map_tile_edit'])    ? $conf['osm_map_tile_edit']        : 'carto_voyager';
$osm_edit_height  = isset($conf['osm_map_edit_height'])  ? (int)$conf['osm_map_edit_height'] : 400;
$osm_unit_height  = isset($conf['osm_map_unit_height'])  ? (int)$conf['osm_map_unit_height'] : 280;
$osm_hide_if_osm  = isset($conf['osm_map_hide_if_osm'])  && $conf['osm_map_hide_if_osm'] == '1';
$osm_position     = (isset($conf['osm_map_position']) && in_array($conf['osm_map_position'], array('top', 'bottom', 'none'), true)) ? $conf['osm_map_position'] : 'bottom';
$osm_native_active = isset($GLOBALS['pwg_loaded_plugins']['piwigo-openstreetmap']);
$osm_carto_api_key = isset($conf['osm_map_carto_api_key']) ? $conf['osm_map_carto_api_key'] : '';

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
<?= osmme_admin_tabs('settings') ?>

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

  <!-- Position de la grande carte -->
  <div class="osm-admin-row">
    <div class="osm-admin-label">
      <strong>Affichage de la carte de la galerie</strong>
      <p class="description">Place la grande carte en haut ou en bas des pages d'albums, ou la masque. Chaque album peut avoir son propre choix (page « Propriétés » de l'album), qui prend la main sur ce réglage. <?php if ($osm_native_active): ?><br><strong>piwigo-openstreetmap est actif :</strong> il affiche sa carte en haut, « En haut » est donc traité comme « En bas ». La petite carte des pages photo reste aussi en bas de page.<?php endif ?></p>
    </div>
    <div class="osm-admin-field">
      <select name="osm_map_position">
        <option value="top" <?= $osm_position === 'top' ? 'selected' : '' ?>>En haut de la page</option>
        <option value="bottom" <?= $osm_position === 'bottom' ? 'selected' : '' ?>>En bas de la page</option>
        <option value="none" <?= $osm_position === 'none' ? 'selected' : '' ?>>Pas d'affichage</option>
      </select>
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

  <!-- Clé API CartoDB -->
  <div class="osm-admin-row">
    <div class="osm-admin-label">
      <strong>Clé API CartoDB</strong>
      <p class="description">Depuis le 26/08/2026, les fonds de carte « CartoDB Voyager » nécessitent une clé API gratuite. Obtenez la vôtre sur <a href="https://carto.com/basemaps/apikey/" target="_blank" rel="noopener">carto.com/basemaps/apikey</a>, puis collez-la ici. Laissez vide pour les autres fonds de carte (OpenStreetMap, Satellite, OpenTopo), qui n'en ont pas besoin.</p>
    </div>
    <div class="osm-admin-field">
      <input type="text" name="osm_map_carto_api_key" value="<?= htmlspecialchars($osm_carto_api_key) ?>" placeholder="Votre clé CartoDB" style="width:320px;max-width:100%;padding:5px 8px;border:1px solid #ccc;border-radius:3px;">
    </div>
  </div>

  <!-- Fond de carte galerie -->
  <div class="osm-admin-row">
    <div class="osm-admin-label">
      <strong>Fond de carte — Galerie</strong>
      <p class="description">Fond par défaut de la carte affichée sur la galerie publique. Les visiteurs peuvent en changer via le menu « Fond » de la carte (leur choix est mémorisé dans leur navigateur).</p>
    </div>
    <div class="osm-admin-field">
      <select name="osm_map_tile">
        <?php foreach ($tile_options_gallery as $k => $v): ?>
        <option value="<?= $k ?>" <?= $k === $osm_tile ? 'selected' : '' ?>><?= $v ?></option>
        <?php endforeach ?>
      </select>
    </div>
  </div>

  <!-- Mini-carte des pages photo -->
  <div class="osm-admin-row">
    <div class="osm-admin-label">
      <strong>Fond de carte — Page d'une photo</strong>
      <p class="description">Fond par défaut de la mini-carte « Localisation » affichée sous chaque photo géolocalisée. Même liste que la galerie ; les visiteurs peuvent le changer avec le menu « Fond ».</p>
    </div>
    <div class="osm-admin-field">
      <select name="osm_map_tile_photo">
        <?php foreach ($tile_options_gallery as $k => $v): ?>
        <option value="<?= $k ?>" <?= $k === $osm_tile_photo ? 'selected' : '' ?>><?= $v ?></option>
        <?php endforeach ?>
      </select>
    </div>
  </div>

  <div class="osm-admin-row">
    <div class="osm-admin-label">
      <strong>Hauteur de la carte — Page d'une photo</strong>
      <p class="description">En pixels. Min : 150, Max : 800.</p>
    </div>
    <div class="osm-admin-field">
      <input type="number" name="osm_map_photo_height" value="<?= $osm_photo_height ?>" min="150" max="800" step="10"> px
    </div>
  </div>

  <!-- Fond de carte géotaggage (gestion par lot) -->
  <div class="osm-admin-row">
    <div class="osm-admin-label">
      <strong>Fond de carte — Gestion par lot</strong>
      <p class="description">Fond par défaut des cartes du mode unitaire de la gestion par lot. Même liste que la galerie ; un menu « Fond » permet aussi de le changer sur chaque carte.</p>
    </div>
    <div class="osm-admin-field">
      <select name="osm_map_tile_geotag">
        <?php foreach ($tile_options_gallery as $k => $v): ?>
        <option value="<?= $k ?>" <?= $k === $osm_tile_geotag ? 'selected' : '' ?>><?= $v ?></option>
        <?php endforeach ?>
      </select>
    </div>
  </div>

  <div class="osm-admin-row">
    <div class="osm-admin-label">
      <strong>Hauteur de la carte — Gestion par lot</strong>
      <p class="description">En pixels, pour chaque photo du mode unitaire. Min : 150, Max : 800.</p>
    </div>
    <div class="osm-admin-field">
      <input type="number" name="osm_map_unit_height" value="<?= $osm_unit_height ?>" min="150" max="800" step="10"> px
    </div>
  </div>

  <!-- Page d'édition d'une photo -->
  <div class="osm-admin-row">
    <div class="osm-admin-label">
      <strong>Fond de carte — Édition d'une photo</strong>
      <p class="description">Fond par défaut de l'onglet « OSM Map Plus » de la page d'édition d'une photo. Même liste que la galerie ; un menu « Fond » permet aussi de le changer sur la carte.</p>
    </div>
    <div class="osm-admin-field">
      <select name="osm_map_tile_edit">
        <?php foreach ($tile_options_gallery as $k => $v): ?>
        <option value="<?= $k ?>" <?= $k === $osm_tile_edit ? 'selected' : '' ?>><?= $v ?></option>
        <?php endforeach ?>
      </select>
    </div>
  </div>

  <div class="osm-admin-row">
    <div class="osm-admin-label">
      <strong>Hauteur de la carte — Édition d'une photo</strong>
      <p class="description">En pixels. Min : 200, Max : 1000.</p>
    </div>
    <div class="osm-admin-field">
      <input type="number" name="osm_map_edit_height" value="<?= $osm_edit_height ?>" min="200" max="1000" step="10"> px
    </div>
  </div>

</div>

<p><input type="hidden" name="submit_osm" value="1"><input class="submit" type="submit" value="💾 Enregistrer"></p>
</form>

<?php
// ── État de l'affichage de la carte par album ───────────────────────────
$osmme_cats = array();   // id => nom complet (« Parent / Enfant »)
$osmme_q = pwg_query('SELECT id, name, uppercats FROM ' . CATEGORIES_TABLE);
$osmme_raw = array();
while ($r = pwg_db_fetch_assoc($osmme_q)) { $osmme_raw[(int)$r['id']] = $r; }
foreach ($osmme_raw as $id => $r) {
    $parts = array();
    foreach (explode(',', $r['uppercats']) as $u) { $u = (int)$u; if (isset($osmme_raw[$u])) $parts[] = $osmme_raw[$u]['name']; }
    $osmme_cats[$id] = implode(' / ', $parts);
}
asort($osmme_cats, SORT_NATURAL | SORT_FLAG_CASE);
$osmme_over = osmme_album_positions();
// tri par nom d'album (les albums supprimés à la fin)
uksort($osmme_over, function ($a, $b) use ($osmme_cats) {
    $na = isset($osmme_cats[(int)$a]) ? $osmme_cats[(int)$a] : "\xff" . $a;
    $nb = isset($osmme_cats[(int)$b]) ? $osmme_cats[(int)$b] : "\xff" . $b;
    return strnatcasecmp($na, $nb);
});
$osmme_lbl  = array('top' => 'En haut', 'bottom' => 'En bas', 'none' => 'Pas d\'affichage');
$osmme_cnt  = array('top' => 0, 'bottom' => 0, 'none' => 0);
foreach ($osmme_over as $cid => $v) { if (isset($osmme_cnt[$v]) && isset($osmme_cats[(int)$cid])) $osmme_cnt[$v]++; }
$osmme_nb_over = array_sum($osmme_cnt);
$osmme_nb_follow = max(0, count($osmme_cats) - count(array_intersect_key($osmme_over, $osmme_cats)));
$osmme_tok = get_pwg_token();
$osmme_ep  = get_root_url() . 'plugins/osm_map/osmme-albumpos.php';
?>
<div class="osm-admin-section" id="osmme-albstate">
  <h3>🗂️ Affichage de la carte par album</h3>
  <div style="padding:14px 16px;">
    <p style="margin:0 0 10px;">
      Réglage général : <strong><?= $osmme_lbl[$osm_position] ?></strong><?= ($osm_native_active && $osm_position === 'top') ? ' <em>(traité comme « En bas » : piwigo-openstreetmap est actif)</em>' : '' ?>.
      <strong><?= $osmme_nb_follow ?></strong> album(s) le suivent.
      <strong><?= $osmme_nb_over ?></strong> album(s) ont un réglage propre :
      <?= $osmme_cnt['top'] ?> en haut · <?= $osmme_cnt['bottom'] ?> en bas · <?= $osmme_cnt['none'] ?> sans carte.
    </p>

    <table class="osmme-albtbl" style="width:100%;border-collapse:collapse;">
      <thead><tr><th style="text-align:left;padding:6px;border-bottom:1px solid #ddd;">Album</th><th style="text-align:left;padding:6px;border-bottom:1px solid #ddd;width:230px;">Affichage</th></tr></thead>
      <tbody id="osmme-albrows">
      <?php foreach ($osmme_over as $cid => $v): if (!in_array($v, array('top','bottom','none'), true)) continue; ?>
        <tr data-cid="<?= (int)$cid ?>">
          <td style="padding:6px;border-bottom:1px solid #eee;">
            <?php if (isset($osmme_cats[(int)$cid])): ?>
              <a href="<?= get_root_url() ?>admin.php?page=album-<?= (int)$cid ?>-properties"><?= htmlspecialchars($osmme_cats[(int)$cid]) ?></a>
            <?php else: ?><em>(album supprimé #<?= (int)$cid ?>)</em><?php endif ?>
          </td>
          <td style="padding:6px;border-bottom:1px solid #eee;">
            <select class="osmme-albsel">
              <option value="">Réglage général</option>
              <?php foreach ($osmme_lbl as $k => $l): ?><option value="<?= $k ?>" <?= $v === $k ? 'selected' : '' ?>><?= $l ?></option><?php endforeach ?>
            </select> <span class="osmme-albmsg" style="color:#2e7d32;"></span>
          </td>
        </tr>
      <?php endforeach ?>
      </tbody>
    </table>
    <?php if (!$osmme_nb_over): ?><p style="color:#666;margin:8px 0 0;"><em>Aucun album n'a de réglage propre : tous suivent le réglage général.</em></p><?php endif ?>

    <p style="margin:14px 0 0;display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
      <select id="osmme-albadd">
        <option value="">+ Ajouter un album…</option>
        <?php foreach ($osmme_cats as $cid => $nm): if (isset($osmme_over[$cid])) continue; ?>
          <option value="<?= (int)$cid ?>"><?= htmlspecialchars($nm) ?></option>
        <?php endforeach ?>
      </select>
      <select id="osmme-albaddpos">
        <?php foreach ($osmme_lbl as $k => $l): ?><option value="<?= $k ?>" <?= $k === 'none' ? 'selected' : '' ?>><?= $l ?></option><?php endforeach ?>
      </select>
      <button type="button" id="osmme-albaddbtn" class="submit">Ajouter</button>
    </p>
    <p style="color:#777;font-size:0.85em;margin:8px 0 0;">Chaque changement est enregistré immédiatement. Ce réglage peut aussi être modifié dans la page « Propriétés » de chaque album. Un réglage s'applique à l'album lui-même, pas à ses sous-albums.</p>
  </div>
</div>
<script>
(function () {
  var EP = <?= osmme_js($osmme_ep) ?>, TOKEN = <?= osmme_js($osmme_tok) ?>;
  function save(cid, pos, msg, done) {
    var fd = new FormData(); fd.append('cat_id', cid); fd.append('pos', pos); fd.append('pwg_token', TOKEN);
    if (msg) { msg.style.color = '#777'; msg.textContent = '…'; }
    fetch(EP, {method: 'POST', body: fd, credentials: 'same-origin'}).then(function (r) { return r.json(); }).then(function (d) {
      if (d && d.ok) { if (msg) { msg.style.color = '#2e7d32'; msg.textContent = '✓ Enregistré'; } if (done) done(); }
      else if (msg) { msg.style.color = '#c62828'; msg.textContent = '✗ Erreur'; }
    }).catch(function () { if (msg) { msg.style.color = '#c62828'; msg.textContent = '✗ Erreur'; } });
  }
  document.getElementById('osmme-albrows').addEventListener('change', function (e) {
    if (!e.target.classList.contains('osmme-albsel')) return;
    var tr = e.target.closest('tr'), msg = tr.querySelector('.osmme-albmsg');
    save(tr.getAttribute('data-cid'), e.target.value, msg, function () {
      // « Réglage général » : l'album n'a plus de réglage propre -> on retire la ligne
      if (e.target.value === '') { setTimeout(function () { location.reload(); }, 400); }
    });
  });
  document.getElementById('osmme-albaddbtn').addEventListener('click', function () {
    var cid = document.getElementById('osmme-albadd').value, pos = document.getElementById('osmme-albaddpos').value;
    if (!cid) return;
    save(cid, pos, null, function () { location.reload(); });
  });
})();
</script>

</div><!-- /osm-admin-wrap -->
<?php
$_admin_content = ob_get_clean();
global $template;
$template->assign('OSM_ADMIN_HTML', $_admin_content);
$template->set_filenames(array('plugin_admin_content' => dirname(__FILE__).'/template/admin.tpl'));
$template->assign_var_from_handle('ADMIN_CONTENT', 'plugin_admin_content');
