<?php
/*
 * This file is part of OSM Map Plus (GPL-2.0-or-later).
 * Liste paginée des photos sans position GPS en base (pour les géolocaliser une à une).
 */
if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');
check_status(ACCESS_ADMINISTRATOR);
global $template;
include_once(dirname(__FILE__) . '/../include/exif_gps.inc.php');
foreach (array('include/derivative_params.inc.php','include/derivative_std_params.inc.php','include/functions_picture.inc.php') as $f_inc) {
    if (file_exists(PHPWG_ROOT_PATH . $f_inc)) { include_once(PHPWG_ROOT_PATH . $f_inc); }
}

$root = get_root_url();
$base = $root . 'admin.php?page=plugin&section=osm_map/admin/no_gps.php';
$edit = $root . 'admin.php?page=plugin&section=osm_map/admin/photo_edit.php&image_id=';

// ── Paramètres ────────────────────────────────────────────────────────────
$album = isset($_GET['album']) ? (int)$_GET['album'] : 0;
$pp    = isset($_GET['pp']) ? (int)$_GET['pp'] : 48;
if (!in_array($pp, array(24, 48, 96, 200))) { $pp = 48; }
$sorts = array(
    'date_asc'  => array('Date de prise de vue (anciennes d\'abord)', 'i.date_creation IS NULL, i.date_creation ASC, i.id ASC'),
    'date_desc' => array('Date de prise de vue (récentes d\'abord)',  'i.date_creation IS NULL, i.date_creation DESC, i.id DESC'),
    'add_desc'  => array('Date d\'ajout (récentes d\'abord)',          'i.date_available DESC, i.id DESC'),
    'add_asc'   => array('Date d\'ajout (anciennes d\'abord)',         'i.date_available ASC, i.id ASC'),
    'name'      => array('Nom',                                         'i.name IS NULL, i.name ASC, i.file ASC'),
);
$sort = (isset($_GET['sort']) && isset($sorts[$_GET['sort']])) ? $_GET['sort'] : 'date_asc';
$np   = max(1, isset($_GET['np']) ? (int)$_GET['np'] : 1);

// ── Requête ───────────────────────────────────────────────────────────────
$where = '(i.latitude IS NULL OR i.longitude IS NULL OR (i.latitude = 0 AND i.longitude = 0))';
if ($album > 0) {
    $ids = function_exists('get_subcat_ids') ? get_subcat_ids(array($album)) : array($album);
    $ids = array_map('intval', $ids);
    if (!$ids) { $ids = array($album); }
    $where .= ' AND i.id IN (SELECT image_id FROM ' . IMAGE_CATEGORY_TABLE . ' WHERE category_id IN (' . implode(',', $ids) . '))';
}
$r = pwg_db_fetch_assoc(pwg_query('SELECT COUNT(*) AS n FROM ' . IMAGES_TABLE . ' i WHERE ' . $where));
$total = (int)$r['n'];
$r = pwg_db_fetch_assoc(pwg_query('SELECT COUNT(*) AS n FROM ' . IMAGES_TABLE));
$all = (int)$r['n'];
$pages = max(1, (int)ceil($total / $pp));
$np = min($np, $pages);
$res = pwg_query('SELECT i.* FROM ' . IMAGES_TABLE . ' i WHERE ' . $where . ' ORDER BY ' . $sorts[$sort][1]
               . ' LIMIT ' . (($np - 1) * $pp) . ', ' . $pp);

// ── Albums pour le filtre ─────────────────────────────────────────────────
$opts = '<option value="0">Tous les albums</option>';
$rc = pwg_query('SELECT id, name, uppercats FROM ' . CATEGORIES_TABLE . ' ORDER BY global_rank');
while ($c = pwg_db_fetch_assoc($rc)) {
    $depth = substr_count($c['uppercats'], ',');
    $opts .= '<option value="' . (int)$c['id'] . '"' . ((int)$c['id'] === $album ? ' selected' : '') . '>'
           . str_repeat('&nbsp;&nbsp;&nbsp;', $depth) . htmlspecialchars(strip_tags($c['name'])) . '</option>';
}
$sopts = '';
foreach ($sorts as $k => $v) { $sopts .= '<option value="' . $k . '"' . ($k === $sort ? ' selected' : '') . '>' . htmlspecialchars($v[0]) . '</option>'; }
$popts = '';
foreach (array(24, 48, 96, 200) as $n) { $popts .= '<option value="' . $n . '"' . ($n === $pp ? ' selected' : '') . '>' . $n . '</option>'; }

function osmme_ng_url($base, $album, $sort, $pp, $np) {
    return htmlspecialchars($base . '&album=' . (int)$album . '&sort=' . urlencode($sort) . '&pp=' . (int)$pp . '&np=' . (int)$np);
}
function osmme_ng_pager($base, $album, $sort, $pp, $np, $pages) {
    if ($pages <= 1) { return ''; }
    $set = array(1, $pages);
    for ($i = $np - 2; $i <= $np + 2; $i++) { if ($i >= 1 && $i <= $pages) { $set[] = $i; } }
    $set = array_values(array_unique($set)); sort($set);
    $h = '<div class="osm-ng-pager">';
    if ($np > 1) { $h .= '<a href="' . osmme_ng_url($base, $album, $sort, $pp, $np - 1) . '">&lsaquo; Précédente</a>'; }
    $prev = 0;
    foreach ($set as $i) {
        if ($prev && $i > $prev + 1) { $h .= '<span>&hellip;</span>'; }
        $h .= ($i === $np) ? '<strong>' . $i . '</strong>' : '<a href="' . osmme_ng_url($base, $album, $sort, $pp, $i) . '">' . $i . '</a>';
        $prev = $i;
    }
    if ($np < $pages) { $h .= '<a href="' . osmme_ng_url($base, $album, $sort, $pp, $np + 1) . '">Suivante &rsaquo;</a>'; }
    return $h . '</div>';
}

// ── Lignes ────────────────────────────────────────────────────────────────
$exif_ext = function_exists('exif_read_data');
$cards = '';
while ($row = pwg_db_fetch_assoc($res)) {
    $name = ($row['name'] !== null && $row['name'] !== '') ? $row['name'] : pathinfo($row['file'], PATHINFO_FILENAME);
    $th = '';
    try {
        $u = (new DerivativeImage(ImageStdParams::get_by_type(IMG_SQUARE), new SrcImage($row)))->get_url();
        $th = (strpos($u, 'http') === 0 || $u[0] === '/') ? $u : $root . ltrim($u, './');
    } catch (Throwable $e) { $th = ''; }
    $date = !empty($row['date_creation']) ? substr($row['date_creation'], 0, 10) : '';
    $flag = '';
    if ($exif_ext) {
        $e = osmme_read_exif_gps(PHPWG_ROOT_PATH . $row['path']);
        if ($e) { $flag = '<span class="osm-ng-flag" title="Le fichier contient un GPS (EXIF) : voir l\'audit">&#9888;&#65039; GPS dans le fichier</span>'; }
    }
    $cards .= '<a class="osm-ng-card" href="' . htmlspecialchars($edit . (int)$row['id']) . '" target="_blank" rel="noopener" title="Géolocaliser cette photo (nouvel onglet)">'
            . ($th !== '' ? '<img src="' . htmlspecialchars($th) . '" alt="" loading="lazy">' : '<span class="osm-ng-noimg"></span>')
            . '<span class="osm-ng-name">' . htmlspecialchars($name) . '</span>'
            . '<span class="osm-ng-date">' . ($date !== '' ? htmlspecialchars($date) : '&mdash;') . ' &middot; #' . (int)$row['id'] . '</span>'
            . $flag . '</a>';
}

$pager = osmme_ng_pager($base, $album, $sort, $pp, $np, $pages);
$html = '
<style>
#osm-ng { padding:20px; max-width:1200px; }
#osm-ng .bar { display:flex; gap:14px; align-items:center; flex-wrap:wrap; margin:12px 0; }
#osm-ng select, #osm-ng button { padding:6px 10px; border:1px solid #ccc; border-radius:4px; background:#fff; }
#osm-ng button { background:#1a73e8; color:#fff; border-color:#1a73e8; cursor:pointer; font-weight:600; }
.osm-ng-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(150px,1fr)); gap:12px; margin:12px 0; }
.osm-ng-card { display:flex; flex-direction:column; gap:3px; border:1px solid #d0d0d0; border-radius:4px; padding:8px; background:#fff; text-decoration:none; color:inherit; }
.osm-ng-card:hover { border-color:#1a73e8; box-shadow:0 1px 6px rgba(26,115,232,.25); }
.osm-ng-card img, .osm-ng-noimg { width:100%; aspect-ratio:1/1; object-fit:cover; border-radius:3px; background:#eee; display:block; }
.osm-ng-name { font-size:0.85rem; font-weight:600; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.osm-ng-date { font-size:0.78rem; color:#666; }
.osm-ng-flag { font-size:0.75rem; color:#b26a00; background:#fff4e5; border:1px solid #f5c27a; border-radius:3px; padding:1px 5px; }
.osm-ng-pager { display:flex; gap:6px; flex-wrap:wrap; align-items:center; margin:8px 0; }
.osm-ng-pager a, .osm-ng-pager strong { padding:4px 10px; border:1px solid #d0d0d0; border-radius:4px; text-decoration:none; background:#fff; }
.osm-ng-pager strong { background:#1a73e8; color:#fff; border-color:#1a73e8; }
</style>
<div id="osm-ng">
  <p><a href="' . $root . 'admin.php?page=plugin-osm_map">&larr; Retour aux paramètres d\'OSM Map Plus</a></p>
  <h3>&#128205; Photos sans position GPS en base</h3>
  <p><strong>' . $total . '</strong> photo(s) sans GPS' . ($album > 0 ? ' dans cet album' : '') . ' sur ' . $all . ' au total. Cliquez une vignette pour ouvrir son éditeur de position (nouvel onglet).'
  . ($exif_ext ? ' Le repère &laquo;&nbsp;GPS dans le fichier&nbsp;&raquo; signale une photo dont le fichier contient déjà une position (voir l\'audit &laquo;&nbsp;Écarts GPS / EXIF&nbsp;&raquo;).' : '') . '</p>
  <form method="get" action="' . htmlspecialchars($root . 'admin.php') . '" class="bar">
    <input type="hidden" name="page" value="plugin"><input type="hidden" name="section" value="osm_map/admin/no_gps.php">
    <label>Album : <select name="album">' . $opts . '</select></label>
    <label>Tri : <select name="sort">' . $sopts . '</select></label>
    <label>Par page : <select name="pp">' . $popts . '</select></label>
    <button type="submit">Afficher</button>
  </form>
  ' . $pager . '
  <div class="osm-ng-grid">' . ($cards !== '' ? $cards : '<p>Aucune photo sans GPS pour ce filtre. &#127881;</p>') . '</div>
  ' . $pager . '
</div>';

$template->assign('OSM_PHOTO_EDIT_HTML', $html);
$template->set_filenames(array('plugin_admin_content' => dirname(__FILE__).'/../template/photo_edit_wrap.tpl'));
$template->assign_var_from_handle('ADMIN_CONTENT', 'plugin_admin_content');
