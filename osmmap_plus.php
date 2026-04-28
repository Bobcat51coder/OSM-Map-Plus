<?php
// OSM Map Plus — Page carte mondiale autonome
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
if (!defined('PHPWG_ROOT_PATH'))
    define('PHPWG_ROOT_PATH', '../../');

define('OSMME_WORLDMAP_PAGE', true);
include_once(PHPWG_ROOT_PATH . 'include/common.inc.php');

// Connectés seulement
if (empty($user) || !isset($user['status']) || $user['status'] === 'guest') {
    redirect(make_index_url());
}

// Photos géolocalisées filtrées selon les droits
$forbidden_cats = empty($user['forbidden_categories']) ? array() : explode(',', $user['forbidden_categories']);
$access_filter  = !empty($forbidden_cats)
    ? ' AND ic.category_id NOT IN (' . implode(',', $forbidden_cats) . ')'
    : '';

// Compter uniquement — les points sont chargés via osmme-points.php (AJAX)
// Pas de limite arbitraire, pas de JSON massif inline
$count_q  = 'SELECT COUNT(DISTINCT i.id) AS nb
    FROM ' . IMAGES_TABLE . ' AS i
    INNER JOIN ' . IMAGE_CATEGORY_TABLE . ' AS ic ON ic.image_id = i.id
    WHERE i.latitude IS NOT NULL AND i.longitude IS NOT NULL AND i.latitude != 0'
    . $access_filter;
$count_r = pwg_query($count_q);
$count_row = pwg_db_fetch_assoc($count_r);
$nb   = (int)$count_row['nb'];
$root = get_root_url();

$tiles = array(
    'carto'     => array('label' => 'Carto Voyager',  'url' => 'https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}.png',   'attr' => '&copy; OpenStreetMap &copy; CARTO'),
    'osm'       => array('label' => 'OpenStreetMap',  'url' => 'https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',                     'attr' => '&copy; OpenStreetMap contributors'),
    'satellite' => array('label' => 'Satellite Esri', 'url' => 'https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', 'attr' => 'Tiles &copy; Esri'),
    'topo'      => array('label' => 'OpenTopoMap',    'url' => 'https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png',                       'attr' => '&copy; OpenStreetMap &copy; OpenTopoMap'),
);

$gallery_title = isset($conf['gallery_title']) ? $conf['gallery_title'] : 'Piwigo';
$home_url      = make_index_url();
$plugin_url    = get_root_url() . 'plugins/osm_map/';
$api_url       = get_root_url() . 'ws.php?format=json&method=osmme.photos.getGeolocated';
$is_admin      = is_admin() ? 'true' : 'false';

// Albums pour le filtre — avec compteur de photos GPS et nom du parent pour distinguer les homonymes
$albums = array();
$qa = pwg_query(
    'SELECT c.id, c.name,
        COUNT(DISTINCT i.id) AS nb_gps,
        (SELECT p.name FROM ' . CATEGORIES_TABLE . ' p WHERE p.id = c.id_uppercat) AS parent_name
     FROM ' . CATEGORIES_TABLE . ' c
     INNER JOIN ' . IMAGE_CATEGORY_TABLE . ' ic ON ic.category_id = c.id
     INNER JOIN ' . IMAGES_TABLE . ' i ON i.id = ic.image_id
        AND i.latitude IS NOT NULL AND i.latitude != 0'
    . (!empty($forbidden_cats) ? ' AND ic.category_id NOT IN (' . implode(',', $forbidden_cats) . ')' : '') .
    ' GROUP BY c.id, c.name, c.id_uppercat
     ORDER BY c.name, c.id'
);
while ($a = pwg_db_fetch_assoc($qa)) {
    // Format : "Nom (Parent) — N photos" pour distinguer les homonymes
    $label = $a['name'];
    if (!empty($a['parent_name'])) $label .= ' (' . $a['parent_name'] . ')';
    $label .= ' — ' . $a['nb_gps'];
    $a['display_name'] = $label;
    $albums[] = $a;
}

$photos_script = '<script>'
    . 'window.OSM_ROOT="' . $root . '";'
    . 'window.OSM_POINTS_URL="' . $root . 'plugins/osm_map/osmme-points.php";'
    . 'window.OSM_PHOTO_URL="' . $root . 'plugins/osm_map/osmme-photo.php";'
    . 'window.OSM_API_URL=\'' . $api_url . '\';'
    . 'window.OSM_IS_ADMIN=' . $is_admin . ';'
    . '</script>';

$template->set_filename('osmmap_plus', dirname(__FILE__) . '/template/osmmap_plus.tpl');
$template->assign(array(
    'GALLERY_TITLE'   => 'OSM Map Plus &mdash; ' . $gallery_title,
    'HOME'            => $home_url,
    'NB_PHOTOS'       => $nb,
    'PHOTOS_SCRIPT'   => $photos_script,
    'ROOT_URL'        => $root,
    'MAP_BLOCK_TPL'   => dirname(__FILE__) . '/template/map_block.tpl',
    'OSM_PLUGIN_PATH' => $plugin_url,
    'OSM_API_URL'     => $api_url,
    'OSM_MAP_HEIGHT'  => isset($conf['osm_map_plus_height']) ? (int)$conf['osm_map_plus_height'] : 600,
    'OSM_ALBUMS'      => $albums,
    'TILES_JSON'      => json_encode($tiles),
    'OSM_MAP_VERSION' => OSM_MAP_VERSION,
    'OSM_TILE'        => isset($conf['osm_map_tile']) ? $conf['osm_map_tile'] : 'carto_voyager',
    'OSM_INIT_ZOOM'   => isset($conf['osm_map_zoom'])  ? (int)$conf['osm_map_zoom']  : 5,
));
$template->pparse('osmmap_plus');
