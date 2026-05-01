<?php
/*
Plugin Name: OSM Map Plus
Version: 1.0.0-2026-03-19
Description: Carte OpenStreetMap pour Piwigo avec clustering, géocodeur, filtres par album, panneau liste et création d'album depuis la carte. Chargement AJAX sans limite, compatible tous thèmes.
Plugin URI: https://github.com/Bobcat51coder/OSM-Map-Plus
Author: Bobcat51
Author URI:
*/

// +-----------------------------------------------------------------------+
// | OSM Map Plus - Plugin for Piwigo                                      |
// +-----------------------------------------------------------------------+
// | Conception, cahier des charges et tests : Bobcat-Fr                  |
// | Développement : Claude (Anthropic) — https://claude.ai               |
// | Librairies    : Leaflet, MarkerCluster, OpenStreetMap, Nominatim     |
// | Licence       : GPL-2.0                                              |
// +-----------------------------------------------------------------------+
/*
 * This file is part of OSM-Map-Plus.
 *
 * OSM-Map-Plus is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * OSM-Map-Plus is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with OSM-Map-Plus. If not, see <https://www.gnu.org/licenses/>.
 */

defined('PHPWG_ROOT_PATH') or die('Accès direct interdit.');

// --- Définition du plugin (DOIT correspondre au dossier : osm_map) ---
$plugin = 'osm_map'; // Nom du dossier (obligatoire pour ton code)
define($plugin . '_PATH', PHPWG_PLUGINS_PATH . $plugin . '/');
define('OSM_MAP_PATH', $plugin . '_PATH'); // Rétrocompatibilité avec ton code existant
define('OSM_MAP_VERSION', '1.0.0-2026-03-19');

// --- Métadonnées du plugin (pour Piwigo) ---
$plugin_info = array(
    'name'        => 'OSM-Map-Plus',  // Nom affiché dans l'interface
    'version'     => OSM_MAP_VERSION, // Version uniformisée
    'description' => 'Plugin Piwigo pour cartes OpenStreetMap enrichies : clustering, géocodeur, filtres par album, panneau liste et création d\'album depuis la carte. Chargement AJAX sans limite, compatible tous thèmes.',
    'author'      => 'Bobcat51',
    'url'         => 'https://github.com/Bobcat51coder/OSM-Map-Plus', // URL correcte du dépôt
    'state'       => 'stable',
);

// --- Initialisation des paramètres par défaut ---
add_event_handler('init', 'osmme_init_conf');
function osmme_init_conf() {
    global $conf;
    $defaults = array(
        'osm_map_public'      => '0',
        'osm_map_height'      => '500',
        'osm_map_max_photos'  => '5000',
        'osm_map_zoom'        => '5',
        'osm_map_tile'        => 'carto_voyager',
        'osm_map_tile_geotag' => 'carto_voyager',
        'osm_map_hide_if_osm' => '0',
    );
    foreach ($defaults as $key => $val) {
        if (!isset($conf[$key])) {
            conf_update_param($key, $val);
            $conf[$key] = $val;
        }
    }
    // Migration : si l'ancienne valeur par défaut 2000 est encore en base, la passer à 5000
    if (isset($conf['osm_map_max_photos']) && (int)$conf['osm_map_max_photos'] === 2000) {
        conf_update_param('osm_map_max_photos', '5000');
        $conf['osm_map_max_photos'] = '5000';
    }
}
// ── Lien vers la page d'administration ───────────────────────────────────
add_event_handler('get_admin_plugin_menu_links', 'osmme_admin_menu');
function osmme_admin_menu($menu) {
    array_push($menu, array(
        'NAME' => 'OSM Map Enhanced',
        'URL'  => get_root_url() . 'admin.php?page=plugin&amp;section=osm_map/admin.php',
    ));
    return $menu;
}

add_event_handler('loc_end_page_header', 'osmme_inject_assets');
add_event_handler('loc_end_page_header',  'osmme_add_menu_entry');
add_event_handler('loc_end_index',        'osmme_inject_map_block');
add_event_handler('loc_end_picture',      'osmme_inject_on_photo_page');

// Hooks admin uniquement — pattern identique à piwigo-openstreetmap
if (defined('IN_ADMIN')) {
    include_once(dirname(__FILE__).'/admin/admin_boot.php');
}

function osmme_inject_assets() {
    if (defined('OSMME_WORLDMAP_PAGE')) return;
    global $template;
    $url = get_root_url() . 'plugins/osm_map/';
    // Forcer le menu Piwigo au-dessus des cartes Leaflet
    // Ne PAS toucher aux z-index internes de Leaflet (marker, tile, etc.)
    $template->append('head_elements',
        '<style>' .
        '.leaflet-container { z-index: 0 !important; }' .
        '</style>'
    );
    // jsDelivr = CDN plus rapide et fiable qu'unpkg pour les assets Leaflet
    $template->append('head_elements',
        '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css" />' . "\n" .
        '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />' . "\n" .
        '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />' . "\n" .
        '<link rel="stylesheet" href="' . $url . 'css/map.css?v=' . OSM_MAP_VERSION . '" />' . "\n" .
        '<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js"></script>' . "\n" .
        '<script src="https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js"></script>'
    );
}

// Chargement des classes Piwigo pour les derivatives
function osmme_load_derivative_classes() {
    static $loaded = false;
    if ($loaded) return;
    $loaded = true;
    $root = PHPWG_ROOT_PATH;
    if (file_exists($root . "include/derivative_params.inc.php"))
        include_once($root . "include/derivative_params.inc.php");
    if (file_exists($root . "include/derivative_std_params.inc.php"))
        include_once($root . "include/derivative_std_params.inc.php");
    if (file_exists($root . "include/functions_picture.inc.php"))
        include_once($root . "include/functions_picture.inc.php");
}

function osmme_get_image_columns() {
    static $cols = null;
    if ($cols !== null) return $cols;
    $cols = array();
    $res  = pwg_query('DESCRIBE ' . IMAGES_TABLE);
    while ($row = pwg_db_fetch_assoc($res)) {
        $cols[] = $row['Field'];
    }
    return $cols;
}

// Construction URL miniature
// Piwigo stocke les derivatives dans _data/i/upload/YYYY/MM/DD/<hash>-th.jpg
// Le champ path contient : "./upload/YYYY/MM/DD/<hash>.jpg"
// => On remplace "./upload/" par "_data/i/upload/" et on insere "-th" avant lextension
function osmme_build_thumb_url($row, $root) {
    if (empty($row['path'])) return '';
    osmme_load_derivative_classes();
    try {
        $src   = new SrcImage($row);
        $deriv = new DerivativeImage(ImageStdParams::get_by_type(IMG_THUMB), $src);
        $url   = $deriv->get_url();
        // get_url() retourne un chemin relatif type "_data/i/upload/.../hash-th.jpg"
        // On le transforme en URL absolue
        if (strpos($url, 'http') === 0) return $url;
        return $root . ltrim($url, './');
    } catch (Exception $e) {
        // Fallback : reconstruction manuelle
        $path     = preg_replace('/^\.\//', '', $row['path']); // retire "./"
        $dir      = pathinfo($path, PATHINFO_DIRNAME);
        $basename = pathinfo($path, PATHINFO_FILENAME);
        $ext      = pathinfo($path, PATHINFO_EXTENSION);
        return $root . '_data/i/' . $dir . '/' . $basename . '-th.' . $ext;
    }
}

function osmme_inject_map_block() {
    if (defined('OSMME_WORLDMAP_PAGE')) return;
    global $template, $conf, $page, $pwg_loaded_plugins;

    // Option : masquer notre carte si piwigo-openstreetmap est actif
    if (!empty($conf['osm_map_hide_if_osm'])
        && $conf['osm_map_hide_if_osm'] == '1'
        && isset($pwg_loaded_plugins['piwigo-openstreetmap'])) {
        return;
    }

    // ── Restriction de page ───────────────────────────────────────────────
    // Autoriser categories, category, flat, et aussi absence de section (page d'accueil)
    $section = isset($page['section']) ? $page['section'] : 'categories';
    if (!in_array($section, array('categories', 'category', 'flat', ''))) {
        return;
    }

    $root   = get_root_url();
    $height = isset($conf['osm_map_height']) ? (int)$conf['osm_map_height'] : 500;

    // ── Option : affichage public autorisé ────────────────────────────────
    // Par défaut : carte masquée si non connecté.
    // Mettre $conf['osm_map_public'] = true dans local/config/config.inc.php pour l'activer.
    $allow_public = !empty($conf['osm_map_public']);
    // Détection connexion sans déclencher les vérifications fichiers de Piwigo
    $is_logged    = (isset($_SESSION['pwg_uid']) && (int)$_SESSION['pwg_uid'] > 0);

    if (!$is_logged && !$allow_public) {
        return; // Non connecté et mode public désactivé → on n'affiche rien
    }

    $cols        = osmme_get_image_columns();
    $select_cols = 'i.id, i.name, i.file, i.latitude, i.longitude, i.date_creation';
    if (in_array('path', $cols)) $select_cols .= ', i.path';

    // ── Filtre de visibilité ──────────────────────────────────────────────
    // Si non connecté (mode public), on restreint aux photos dans des albums publics.
    // Si connecté, on affiche tout (selon les droits Piwigo habituels).
    $visibility_join   = '';
    $visibility_where  = '';
    $visibility_join2  = '';
    $visibility_where2 = '';

    if (!$is_logged) {
        // Rejoindre les catégories et filtrer sur status = 'public'
        $visibility_join  = ' INNER JOIN ' . IMAGE_CATEGORY_TABLE . ' AS ic_pub ON ic_pub.image_id = i.id'
                          . ' INNER JOIN ' . CATEGORIES_TABLE . ' AS c_pub ON c_pub.id = ic_pub.category_id';
        $visibility_where = " AND c_pub.status = 'public'";

        $visibility_join2  = ' INNER JOIN ' . IMAGE_CATEGORY_TABLE . ' AS ic_pub2 ON ic_pub2.image_id = i.id'
                           . ' INNER JOIN ' . CATEGORIES_TABLE . ' AS c_pub2 ON c_pub2.id = ic_pub2.category_id';
        $visibility_where2 = " AND c_pub2.status = 'public'";
    }

    // ── Albums avec photos GPS ─────────────────────────────────────────────
    // Si on est dans un album précis, le sélecteur est inutile → on le masque
    $in_single_category = !empty($page['category']['id']);
    $albums = array();
    if (!$in_single_category) {
        $rAlbums = pwg_query('
            SELECT c.id, c.name, c.status,
                COUNT(DISTINCT i.id) AS nb_images,
                (SELECT p.name FROM ' . CATEGORIES_TABLE . ' p WHERE p.id = c.id_uppercat) AS parent_name
            FROM ' . CATEGORIES_TABLE . ' AS c
            INNER JOIN ' . IMAGE_CATEGORY_TABLE . ' AS ic ON ic.category_id = c.id
            INNER JOIN ' . IMAGES_TABLE . ' AS i ON i.id = ic.image_id
            WHERE i.latitude IS NOT NULL AND i.longitude IS NOT NULL
              AND i.latitude <> 0 AND i.longitude <> 0'
              . ($is_logged ? '' : " AND c.status = 'public'") . '
            GROUP BY c.id, c.name, c.status, c.id_uppercat
            HAVING COUNT(DISTINCT i.id) > 0
            ORDER BY c.name ASC, c.id ASC');
        while ($row = pwg_db_fetch_assoc($rAlbums)) {
            $label = $row['name'];
            if (!empty($row['parent_name'])) $label .= ' (' . $row['parent_name'] . ')';
            $label .= ' — ' . $row['nb_images'];
            $row['display_name'] = $label;
            $albums[] = $row;
        }
    }

    // ── Si mode public : vérifier qu'il existe au moins un album public ───
    if (!$is_logged && empty($albums)) {
        return; // Aucun album public avec photos GPS → rien à afficher
    }

    // ── Filtre catégorie courante ─────────────────────────────────────────
    // Si on est dans un album précis ($page['category']), on ne montre que ses photos
    $cat_join  = '';
    $cat_where = '';
    if (!empty($page['category']['id'])) {
        $cat_id   = (int)$page['category']['id'];
        $cat_join  = ' INNER JOIN ' . IMAGE_CATEGORY_TABLE . ' AS ic_cat ON ic_cat.image_id = i.id';
        $cat_where = ' AND ic_cat.category_id = ' . $cat_id;
    }

    // ── Compter les photos GPS (pour décider d'afficher ou non la carte) ──
    $count_q = 'SELECT COUNT(DISTINCT i.id) AS nb
        FROM ' . IMAGES_TABLE . ' AS i
        LEFT JOIN ' . IMAGE_CATEGORY_TABLE . ' AS ic2 ON ic2.image_id = i.id'
        . $visibility_join2
        . $cat_join . '
        WHERE i.latitude IS NOT NULL AND i.longitude IS NOT NULL
          AND i.latitude <> 0 AND i.longitude <> 0'
        . $visibility_where2
        . $cat_where;
    $count_r = pwg_query($count_q);
    $count_row = pwg_db_fetch_assoc($count_r);
    $total = (int)$count_row['nb'];
    if ($total === 0) return;
    // Les photos sont chargées via osmme-points.php (AJAX) — pas de JSON inline
    $photos = array();

    $js_url = $root . 'plugins/osm_map/js/map.js?v=' . OSM_MAP_VERSION;

    // Bouton "Créer album" uniquement pour les utilisateurs connectés
    $btn_album = $is_logged
        ? '&nbsp;<button id="osm-btn-album" title="Créer un album avec les photos visibles">&#128194; Créer album</button>'
        : '';

    $albums_html = '';
    foreach ($albums as $a) {
        $albums_html .= '<option value="' . (int)$a['id'] . '">'
            . htmlspecialchars(isset($a['display_name']) ? $a['display_name'] : $a['name'], ENT_QUOTES)
            . ' (' . (int)$a['nb_images'] . ')</option>';
    }

    $api_url     = $root . 'ws.php';
    $cat_id = !empty($page['category']['id']) ? (int)$page['category']['id'] : 0;

    $html = '<div id="osm-map-container">'

        . '<div class="osm-header">'
        . '<span class="osm-title">&#128506; Carte des photos</span>'
        . '<div class="osm-controls">'
        . ($in_single_category ? '' :
             '<label for="osm-album-filter">Votre choix &nbsp;:&nbsp;</label>'
           . '<select id="osm-album-filter">'
           . '<option value="">&#8212; Tous les albums &#8212;</option>'
           . $albums_html
           . '</select>'
           . '&nbsp;')
        . '&nbsp;<span style="font-size:13px;color:#555;white-space:nowrap">Lieu'
        . '<div class="osm-geocoder-wrapper">'
        . '<input type="text" id="osm-geocoder" placeholder="Rechercher&#8230;" autocomplete="off" />'
        . '<div id="osm-geocoder-results" class="osm-geocoder-dropdown"></div>'
        . '</div></span>'
        . '&nbsp;<button id="osm-btn-panel">&#9776; Liste</button>'
        . $btn_album
        . '</div></div>'

        . '<div class="osm-body">'
        . '<div id="osm-map" style="height:' . $height . 'px;"></div>'
        . '<div id="osm-panel" class="osm-panel">'
        . '<div class="osm-panel-header">'
        . '<span id="osm-panel-count">0 photos</span>'
        . '<button id="osm-panel-close" title="Fermer">&#x2715;</button>'
        . '</div>'
        . '<div id="osm-panel-list" class="osm-panel-list"></div>'
        . '</div>'
        . '</div>'

        . '<div class="osm-footer">'
        . '<span class="osm-stats">'
        . '<span id="osm-count-visible">' . $total . '</span>&nbsp;photos affich&eacute;es&nbsp;/&nbsp;'
        . '<span id="osm-count-total">' . $total . '</span>&nbsp;total'
        . '</span></div>'
        . '</div>'

        . '<script>'
        . 'window.OSM_POINTS_URL="' . $root . 'plugins/osm_map/osmme-points.php' . ($cat_id > 0 ? '?cat_id=' . $cat_id : '') . '";'
        . 'window.OSM_PHOTO_URL="' . $root . 'plugins/osm_map/osmme-photo.php";'
        . 'window.OSM_ROOT="' . $root . '";'
        . 'window.OSM_API_URL="' . $api_url . '";'
        . '</script>'
        . '<script src="' . $js_url . '"></script>'
        . '<script>document.addEventListener("DOMContentLoaded",function(){'
        . 'OSMMap.init({mapHeight:' . $height . ',apiUrl:window.OSM_API_URL,initZoom:' . (int)$conf['osm_map_zoom'] . ',tile:"' . ($conf['osm_map_tile'] ?? 'carto_voyager') . '"});'
        . '});</script>';

    $template->append('footer_elements', $html);
}

function osmme_inject_on_photo_page() {
    global $template, $picture;

    if (empty($picture['current']['latitude']) || empty($picture['current']['longitude'])) {
        return;
    }

    $lat   = (float)$picture['current']['latitude'];
    $lon   = (float)$picture['current']['longitude'];
    $title = htmlspecialchars(isset($picture['current']['name']) ? $picture['current']['name'] : '', ENT_QUOTES);

    $html = '<div class="osm-photo-map-wrapper">'
        . '<h4 class="osm-photo-map-title">&#128205; Localisation</h4>'
        . '<div id="osm-photo-map" style="height:220px;width:100%;"></div>'
        . '<div class="osm-photo-coords">' . $lat . ', ' . $lon
        . ' &nbsp;<a href="https://www.openstreetmap.org/?mlat=' . $lat . '&mlon=' . $lon . '&zoom=14"'
        . ' target="_blank" rel="noopener">Voir sur OpenStreetMap &#8599;</a></div></div>'
        . '<script>(function(){function init(){'
        . 'if(typeof L==="undefined"){setTimeout(init,200);return;}'
        . 'var m=L.map("osm-photo-map").setView([' . $lat . ',' . $lon . '],14);'
        . 'L.tileLayer("https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}.png",'
        . '{attribution:"&copy; OpenStreetMap &copy; CARTO",maxZoom:19}).addTo(m);'
        . 'L.marker([' . $lat . ',' . $lon . ']).addTo(m)'
        . '.bindPopup("<strong>' . $title . '</strong>").openPopup();'
        . '}init();})();</script>';

    $template->append('footer_elements', $html);
}




// ============================================================================
// GÉOTAGGAGE — Mode unitaire batch manager (hook natif Piwigo)
// ============================================================================
function osmme_loc_end_element_set_unit() {
    global $template, $conf;

    // Sécurité : ne s'exécute que si la variable template existe
    if (!isset($template) || !is_object($template)) return;

    $height     = isset($conf['osm_map_unit_height'])    ? (int)$conf['osm_map_unit_height']    : 280;
    // Relire depuis DB pour avoir la valeur la plus fraîche
    $res_tk = pwg_query("SELECT value FROM " . CONFIG_TABLE . " WHERE param = 'osm_map_tile_geotag'");
    $row_tk = pwg_db_fetch_assoc($res_tk);
    $tile_key = $row_tk ? $row_tk['value'] : (isset($conf['osm_map_tile_geotag']) ? $conf['osm_map_tile_geotag'] : 'carto_voyager');
    $tpl        = realpath(dirname(__FILE__) . '/template/batch_unit.tpl');
    if (!$tpl) return;

    // URLs avec {literal}{s}{/literal} etc. pour éviter l'interprétation Smarty
    // Accolades encodées pour éviter l'interprétation Smarty dans le .tpl
    $tiles_map = array(
        'carto_voyager'  => array('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}.png', '&copy; OpenStreetMap &copy; CARTO', 19),
        'osm_standard'   => array('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', '&copy; OpenStreetMap contributors', 19),
        'esri_satellite' => array('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', 'Tiles &copy; Esri', 18),
        'opentopo'       => array('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', '&copy; OpenStreetMap &copy; OpenTopoMap', 17),
    );
    $t = isset($tiles_map[$tile_key]) ? $tiles_map[$tile_key] : $tiles_map['carto_voyager'];

    // Injecter l'URL tile via head_elements (avant le template) pour éviter Smarty avec {s}{z}
    $tile_js = '<script>window.OSM_BATCH_TILE_URL="' . addslashes($t[0]) . '";'
             . 'window.OSM_BATCH_TILE_ATTR="' . addslashes($t[1]) . '";'
             . 'window.OSM_BATCH_TILE_MAXZOOM=' . (int)$t[2] . ';</script>';
    $template->append('head_elements', $tile_js);

    $template->assign(array(
        'OSM_UNIT_HEIGHT'  => $height,
    ));
    $template->append('PLUGINS_BATCH_MANAGER_UNIT_ELEMENT_SUBTEMPLATE', $tpl);
}



// ============================================================================
// Injection Leaflet en admin (batch manager mode unitaire uniquement)
// ============================================================================
function osmme_inject_leaflet_admin() {
    // Uniquement sur batch_manager mode unit
    if (!isset($_GET['page']) || $_GET['page'] !== 'batch_manager') return;
    if (!isset($_GET['mode']) || $_GET['mode'] !== 'unit') return;

    global $template;
    $template->append('head_elements',
        '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />' . "\n" .
        '<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>'
    );
}

// ============================================================================
// Onglet OpenStreetMap sur la page d'édition photo (comme OSM Geotag)




// ============================================================================
// Ajouter entrée de menu dans la galerie via loc_end_page_header
// ============================================================================
function osmme_add_menu_entry() {
    if (defined('OSMME_WORLDMAP_PAGE')) return;
    global $user, $template;
    if (empty($user) || !isset($user['status'])) return;
    if ($user['status'] === 'guest') return;
    $url = get_root_url() . 'plugins/osm_map/osmmap_plus.php';
    $selected = isset($_GET['osm_worldmap']) ? 'true' : 'false';
    // Injecter via JS pour éviter les problèmes de hooks
    $menu_link = htmlspecialchars($url, ENT_QUOTES);
    $js = "document.addEventListener('DOMContentLoaded',function(){";
    $js .= "var mn=null;";
    $js .= "var lists=document.querySelectorAll('dd ul');";
    $js .= "for(var i=0;i<lists.length;i++){";
    $js .= "if(lists[i].querySelector('a[href*=search],a[href*=about],a[href*=recent]')){mn=lists[i];break;}}";
    $js .= "if(!mn&&lists.length)mn=lists[lists.length-1];";
    $js .= "if(!mn)return;";
    $js .= "var li=document.createElement('li');";
    $inner = '<a href="' . $menu_link . '">&#127760; OSM Map Plus</a>';
    $js .= "li.innerHTML=" . json_encode($inner) . ";";
    $js .= "mn.appendChild(li);});";
    $template->append('head_elements', '<script>' . $js . '</script>');
}
