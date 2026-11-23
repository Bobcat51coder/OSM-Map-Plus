<?php
/*
Plugin Name: OSM Map Plus
Version: 1.1.0
Description: Carte OpenStreetMap pour Piwigo : clustering, 7 fonds de carte, vue 3D, vignettes, diaporama, géotaggage par lot et unitaire, lieux favoris, audit GPS/EXIF, affichage haut/bas/masqué par album. Compatible tous thèmes.
Plugin URI: https://piwigo.org/ext/extension_view.php?eid=1073
Author: Bobcat-Fr
Author URI: 
Has Settings: true
*/
// +-----------------------------------------------------------------------+
// | OSM Map Plus - Plugin for Piwigo                                      |                                                       |
// +-----------------------------------------------------------------------+
// | Conception, cahier des charges et tests : Bobcat-Fr                  |
// | Développement : Claude (Anthropic) — https://claude.ai               |
// | Librairies    : Leaflet, MarkerCluster, MapLibre GL, OpenStreetMap   |
// | Licence       : GPL 2                                               |
// +-----------------------------------------------------------------------+
/*
 * This file is part of OSM Map Plus.
 *
 * OSM Map Plus is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * OSM Map Plus is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with OSM Map Plus.  If not, see <https://www.gnu.org/licenses/>.
 */
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');
// --- Définition du plugin ---
define('OSM_MAP_PATH', PHPWG_PLUGINS_PATH . 'osm_map/');
define('OSM_MAP_VERSION', '1.1.0');
// Paramètre anti-cache des fichiers JS/CSS (change à chaque mise à jour des fichiers)
define('OSM_MAP_ASSET_V', OSM_MAP_VERSION . '.' . (int)@filemtime(__FILE__));

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
    // Fichiers de langue du plugin (language/<code>/plugin.lang.php)
    load_language('plugin.lang', OSM_MAP_PATH);
    $defaults = array(
        'osm_map_public'      => '0',
        'osm_map_height'      => '500',
        'osm_map_max_photos'  => '5000',
        'osm_map_zoom'        => '5',
        'osm_map_tile'        => 'carto_voyager',
        'osm_map_tile_geotag' => 'carto_voyager',
        'osm_map_tile_edit'   => 'carto_voyager',
        'osm_map_edit_height' => '400',
        'osm_map_unit_height' => '280',
        'osm_map_tile_photo'  => 'carto_voyager',
        'osm_map_photo_height' => '220',
        'osm_map_hide_if_osm' => '0',
        'osm_map_position'    => 'bottom',
        'osm_map_carto_api_key' => '',
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
// ── Résout la clé API CartoDB à utiliser, indépendamment du plugin qui l'a
// configurée : priorité à la clé propre d'OSM Map Plus si elle est renseignée,
// sinon repli automatique sur celle de Geo Album (si ce plugin est installé et
// que sa clé est renseignée) — sans se soucier de savoir lequel des deux
// plugins est actuellement actif, seule la présence d'une valeur compte.
// Nettoie aussi les caractères Unicode invisibles (U+2028 etc.) qu'un simple
// trim() PHP ne retire pas et qu'un copier-coller peut introduire.
function osmme_clean_key($key) {
    return trim(preg_replace('/[\x{2028}\x{2029}\x{200B}\x{FEFF}]/u', '', (string)$key));
}
function osmme_resolve_carto_key() {
    global $conf;
    $own = osmme_clean_key($conf['osm_map_carto_api_key'] ?? '');
    if ($own !== '') return $own;
    return osmme_clean_key($conf['geoalbum_carto_api_key'] ?? '');
}

// ── Ajoute la clé API CartoDB (si configurée) à une URL de tuile Carto ───
// Depuis le 26/08/2026, les tuiles raster basemaps.cartocdn.com nécessitent
// une clé API gratuite (https://carto.com/basemaps/apikey/), ajoutée en
// paramètre ?key=VOTRE_CLE. Cf. https://docs.carto.com/faqs/carto-basemaps
function osmme_carto_tile_url($url) {
    $key = osmme_resolve_carto_key();
    if ($key === '') return $url;
    $sep = (strpos($url, '?') === false) ? '?' : '&';
    return $url . $sep . 'key=' . urlencode($key);
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


// ── Chargeur Leaflet unique ──────────────────────────────────────────────
// Évite les conflits avec d'autres plugins de cartes (ex. piwigo-openstreetmap) : on n'injecte plus
// notre propre Leaflet dans <head>. Une fois la page chargée, on réutilise le L global s'il existe,
// sinon on charge Leaflet (et MarkerCluster si besoin) ; il n'y a ainsi qu'une seule instance de Leaflet.
function osmme_loader_js() {
    return "window.osmmeLeaflet=window.osmmeLeaflet||function(nc,cb){"
        . "var LJS='https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js',"
        . "MJS='https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/leaflet.markercluster.js';"
        . "function ld(s,n){var e=document.createElement('script');e.src=s;e.onload=n;e.onerror=n;document.head.appendChild(e);}"
        . "function st2(){if(nc&&window.L&&!window.L.markerClusterGroup){ld(MJS,cb);}else{cb();}}"
        . "function go(){if(window.L&&window.L.map){st2();}else{ld(LJS,st2);}}"
        . "if(document.readyState==='complete'){go();}else{window.addEventListener('load',go);}};";
}

function osmme_inject_assets() {
    if (defined('OSMME_WORLDMAP_PAGE')) return;
    global $template, $conf;
    $url = get_root_url() . 'plugins/osm_map/';
    // Forcer le menu Piwigo au-dessus des cartes Leaflet
    // Ne PAS toucher aux z-index internes de Leaflet (marker, tile, etc.)
    $template->append('head_elements',
        '<style>' .
        '.leaflet-container { z-index: 0 !important; }' .
        '.leaflet-container .leaflet-tile{opacity:1!important;visibility:visible!important;max-width:none!important;max-height:none!important;}.leaflet-tile-pane{opacity:1!important;visibility:visible!important;}' .
        '</style>'
    );
    // Clé API CartoDB (basemaps.cartocdn.com), lue par map.js / geotag.js
    $carto_key = osmme_resolve_carto_key();
    $template->append('head_elements',
        '<script>window.OSM_CARTO_API_KEY=' . osmme_js($carto_key) . ';</script>'
    );
    // jsDelivr = CDN plus rapide et fiable qu'unpkg pour les assets Leaflet
    $template->append('head_elements',
        '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css" />' . "\n" .
        '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/MarkerCluster.css" />' . "\n" .
        '<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet.markercluster@1.5.3/dist/MarkerCluster.Default.css" />' . "\n" .
        '<link rel="stylesheet" href="' . $url . 'css/map.css?v=' . OSM_MAP_ASSET_V . '" />' . "\n" .
        // Leaflet n'est plus chargé ici : osmmeLeaflet() réutilise le Leaflet déjà présent (autre plugin) ou charge le sien,
        // pour qu'une seule instance de Leaflet existe sur la page (conflit avec piwigo-openstreetmap)
        '<script>' . osmme_loader_js() . '</script>'
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

// JSON sûr pour être inséré dans un bloc <script> : « < > & \' " » sont échappés, donc un nom de lieu ou
// une valeur contenant « </script> » ne peut pas sortir du script (XSS)
function osmme_js($v) {
    return json_encode($v, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT);
}

// piwigo-openstreetmap est-il actif ? (dans ce cas notre carte reste en bas de page)
function osmme_native_osm_active() {
    global $pwg_loaded_plugins;
    return isset($pwg_loaded_plugins['piwigo-openstreetmap']);
}

// JS : déplace la carte en haut du contenu de la page (repli : reste en bas si aucun conteneur reconnu)
function osmme_move_to_top_js() {
    return '(function(){var w=document.getElementById("osm-map-container");if(!w)return;'
        . 'var s=["#content","#main-content","main",".content"],h=null,i;'
        . 'for(i=0;i<s.length&&!h;i++){h=document.querySelector(s[i]);}'
        . 'if(h&&!h.contains(w)){h.insertBefore(w,h.firstChild);w.style.marginTop="10px";}})();';
}

// Positions possibles de la grande carte : en haut, en bas, ou pas d'affichage
function osmme_valid_pos($v) { return in_array($v, array('top', 'bottom', 'none'), true); }

// Réglages par album (JSON {id_album: position}) ; absent = suit le réglage général
function osmme_album_positions() {
    global $conf;
    $j = json_decode(isset($conf['osm_map_album_pos']) ? $conf['osm_map_album_pos'] : '{}', true);
    return is_array($j) ? $j : array();
}

// Position effective de la grande carte pour un album : le réglage de l'album l'emporte sur le réglage
// général ; « en haut » devient « en bas » si piwigo-openstreetmap est actif (il affiche déjà sa carte en haut).
function osmme_map_position($cat_id = 0) {
    global $conf;
    $pos = (isset($conf['osm_map_position']) && osmme_valid_pos($conf['osm_map_position'])) ? $conf['osm_map_position'] : 'bottom';
    if ($cat_id > 0) {
        $a = osmme_album_positions();
        if (isset($a[$cat_id]) && osmme_valid_pos($a[$cat_id])) $pos = $a[$cat_id];
    }
    if ($pos === 'top' && osmme_native_osm_active()) $pos = 'bottom';
    return $pos;
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

    // Position (ou absence d'affichage) : réglage de l'album, sinon réglage général
    $osmme_cat = !empty($page['category']['id']) ? (int)$page['category']['id'] : 0;
    $osmme_pos = osmme_map_position($osmme_cat);
    if ($osmme_pos === 'none') return;

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

    $js_url = $root . 'plugins/osm_map/js/map.js?v=' . OSM_MAP_ASSET_V;

    // Bouton "Créer album" uniquement pour les utilisateurs connectés
    // « Créer album » : administrateurs uniquement (les autres utilisateurs ne voient que la carte et la liste)
    $btn_album = (function_exists('is_admin') && is_admin())
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
        . 'window.OSM_FAV_PLACES=' . osmme_js((function_exists('is_admin') && is_admin()) ? osmme_places_for_batch() : array()) . ';'
        . 'window.OSM_POINTS_URL="' . $root . 'plugins/osm_map/osmme-points.php' . ($cat_id > 0 ? '?cat_id=' . $cat_id : '') . '";'
        . 'window.OSM_PHOTO_URL="' . $root . 'plugins/osm_map/osmme-photo.php";'
        . 'window.OSM_ROOT="' . $root . '";'
        . 'window.OSM_IS_ADMIN=' . ((function_exists('is_admin') && is_admin()) ? 'true' : 'false') . ';'
        . 'window.OSM_API_URL="' . $api_url . '";'
        . '</script>'
        . '<script src="' . $js_url . '"></script>'
        . '<script>document.addEventListener("DOMContentLoaded",function(){'
        . ($osmme_pos === 'top' ? osmme_move_to_top_js() : '')
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

    // Réglages : fond par défaut et hauteur (Paramètres du plugin)
    global $conf;
    $photo_height = isset($conf['osm_map_photo_height']) ? max(150, min(800, (int)$conf['osm_map_photo_height'])) : 220;
    $photo_tile   = isset($conf['osm_map_tile_photo']) ? $conf['osm_map_tile_photo'] : 'carto_voyager';
    $plugin_url   = get_root_url() . 'plugins/osm_map/';

    // Vignette de la photo utilisée comme repère sur la carte (repli : repère standard)
    $thumb = '';
    try { $thumb = osmme_build_thumb_url($picture['current'], get_root_url()); } catch (Throwable $e) { $thumb = ''; }

    $cfg = array(
        'lat'    => $lat,
        'lon'    => $lon,
        'title'  => isset($picture['current']['name']) ? (string)$picture['current']['name'] : '',
        'thumb'  => $thumb,
        'tile'   => $photo_tile,
        'pid'    => (int)$picture['current']['id'],
        'root'   => get_root_url(),
        'inPanel' => !osmme_native_osm_active(),
        'cartoUrl' => osmme_carto_tile_url('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}.png'),
    );
    $label_title = l10n('Photo location');
    $label_osm   = l10n('View in OpenStreetMap');

    $html = '<style>'
        . '.osmme-pin{background:none;border:none;}'
        . '.osmme-pin img{width:60px;height:60px;object-fit:cover;display:block;border:2px solid #fff;border-radius:3px;box-shadow:0 1px 6px rgba(0,0,0,.55);}'
        . '#osmme-pm.osmme-in-panel{width:100%;box-sizing:border-box;flex:0 0 100%;min-width:0;}'
        . '#osmme-pm.osmme-in-panel #osm-photo-map{width:100%!important;margin:0!important;}'
        . '#osmme-pm{margin:10px 0 14px;}'
        . '#osmme-pm .osmme-pm-head{display:flex;align-items:center;justify-content:space-between;gap:8px;flex-wrap:wrap;margin:0 0 6px;}'
        . '#osmme-pm .osmme-pm-title{font-weight:700;}'
        . '#osmme-pm .osmme-pm-tools select,#osmme-pm .osmme-pm-tools button{font-size:0.8rem;padding:2px 6px;border:1px solid #ccc;border-radius:4px;background:#fff;color:#333;cursor:pointer;}'
        . '#osmme-pm .osmme-pm-link{display:block;margin-top:4px;opacity:.75;}'
        . '</style>'
        . '<div id="osmme-pm" class="osm-photo-map-wrapper">'
        . '<div class="osmme-pm-head"><span class="osmme-pm-title">' . htmlspecialchars($label_title) . '</span>'
        . '<span class="osmme-pm-tools"><select id="osm-photo-basemap" title="Fond de carte"></select> '
        . '<button type="button" id="osm-photo-3d" title="Ouvrir la carte en relief 3D dans un nouvel onglet">&#127956;&#65039; 3D</button></span></div>'
        . '<div id="osm-photo-map" style="height:' . $photo_height . 'px;width:calc(100% - 60px);margin:0 30px;box-sizing:border-box;"></div>'
        . '<a class="osmme-pm-link" href="https://www.openstreetmap.org/?mlat=' . $lat . '&amp;mlon=' . $lon . '#map=15/' . $lat . '/' . $lon . '"'
        . ' target="_blank" rel="noopener">' . htmlspecialchars($label_osm) . '</a>'
        . '<div class="osm-photo-coords">' . htmlspecialchars($label_title) . ' : ' . $lat . ', ' . $lon . '</div></div>'
        . '<script src="' . $plugin_url . 'js/basemaps.js?v=' . OSM_MAP_ASSET_V . '"></script>';

    $js = <<<'JSEOF'
(function(){
  var C = __CFG__;
  var wrap = document.getElementById('osmme-pm');
  if (!wrap) return;

  // Place la carte dans le panneau « Information » du thème (sinon elle reste en bas de page)
  function place() {
    if (!C.inPanel) return true; // piwigo-openstreetmap actif : sa carte occupe le panneau, la nôtre reste en bas
    if (wrap.getAttribute('data-placed')) return true;
    var sels = ['.imageInfoTable', '#infoTable', '#info-tab .card-body', '#theImageInfos .card-body', '#theImageInfos', '#info-content', '#Information', '.card-info'];
    var host = null, i;
    for (i = 0; i < sels.length && !host; i++) { host = document.querySelector(sels[i]); }
    if (host && !host.contains(wrap)) {
      // Liste de rubriques (dl / tableau d'infos) : on se place AVANT elle plutôt qu'à l'intérieur,
      // sinon certains thèmes (modus…) la traitent comme une colonne et écrasent la carte
      if (/^(DL|TABLE|UL)$/.test(host.tagName) || host.classList.contains('imageInfoTable')) { host.parentNode.insertBefore(wrap, host); }
      else { host.insertBefore(wrap, host.firstChild); }
    } else if (!host) {
      var labs = document.querySelectorAll('dt, .imageInfoLabel, .imageInfo .name, .imageInfo > span');
      for (i = 0; i < labs.length; i++) {
        if (/^\s*(Créée le|Created on|Albums?|Visites|Visits|Date de création)\s*:?\s*$/i.test(labs[i].textContent)) {
          var row = labs[i].closest('.imageInfo') || labs[i].parentElement;
          if (row && row.parentNode) { row.parentNode.insertBefore(wrap, row); host = row.parentNode; }
          break;
        }
      }
    }
    if (host) { wrap.classList.add('osmme-in-panel'); wrap.setAttribute('data-placed', '1'); return true; }
    return false;
  }
  // Garde-fou : si le thème écrase la carte dans le panneau (largeur < 220 px), on la place sous la photo, pleine largeur
  function rescue() {
    if (!wrap.getAttribute('data-placed') || wrap.getBoundingClientRect().width >= 220) return;
    var t = document.getElementById('comments'), r = document.getElementById('theImageAndInfos');
    if (t && t.parentNode) { t.parentNode.insertBefore(wrap, t); }
    else if (r && r.parentNode) { r.parentNode.insertBefore(wrap, r.nextSibling); }
    else { return; }
    wrap.classList.remove('osmme-in-panel');
  }
  [700, 1800].forEach(function(t) { setTimeout(rescue, t); });
  var tries = 0;
  (function again() { if (!place() && tries++ < 5) setTimeout(again, 400); })();

  function init() {
    var L = window.L; if (!L) return;
    var el = document.getElementById('osm-photo-map');
    var m = L.map(el, {fadeAnimation:false, zoomAnimation:false, markerZoomAnimation:false}).setView([C.lat, C.lon], 14);
    if (window.OSMBasemaps) {
      OSMBasemaps.attach(m, {key:C.tile, select:document.getElementById('osm-photo-basemap'), button:document.getElementById('osm-photo-3d'), root:C.root, pid:C.pid, storageKey:'osmme_basemap_photo'});
    } else {
      var b3 = document.getElementById('osm-photo-3d'); if (b3) b3.style.display = 'none';
      var bs = document.getElementById('osm-photo-basemap'); if (bs) bs.style.display = 'none';
      L.tileLayer(C.cartoUrl, {attribution:'&copy; OpenStreetMap &copy; CARTO', maxZoom:19}).addTo(m);
    }
    var mk = L.marker([C.lat, C.lon]);
    if (C.thumb) {
      var img = new Image();
      img.onload = function() {
        mk.setIcon(L.divIcon({className:'osmme-pin', html:'<img src="' + C.thumb.replace(/"/g, '&quot;') + '" alt="">', iconSize:[64, 64], iconAnchor:[32, 32]}));
      };
      img.src = C.thumb;
    }
    mk.addTo(m);
    if (C.title) { mk.bindPopup('<strong></strong>'); mk.on('popupopen', function(e) { e.popup.getElement().querySelector('strong').textContent = C.title; }); }
    [150, 500, 1200, 3000].forEach(function(t) { setTimeout(function() { m.invalidateSize(); }, t); });
    if (window.ResizeObserver) new ResizeObserver(function() { m.invalidateSize(); }).observe(el);
    if (window.IntersectionObserver) new IntersectionObserver(function(en) { if (en[0].isIntersecting) m.invalidateSize(); }).observe(el);
  }
  (window.osmmeLeaflet || function(n, c) { c(); })(false, init);
})();
JSEOF;
    $html .= '<script>' . str_replace('__CFG__', json_encode($cfg, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP), $js) . '</script>';

    $template->append('footer_elements', $html);
}




// ============================================================================
// GÉOTAGGAGE — Mode unitaire batch manager (hook natif Piwigo)
// ============================================================================
function osmme_places_for_batch() {
    include_once(OSM_MAP_PATH . 'include/places.inc.php');
    try { return osmme_pl_list(); } catch (Throwable $e) { return array(); }
}

// Variables JS partagées par le mode unitaire et l'action par lot (fond, lieux favoris, clé Carto…)
function osmme_batch_head_script() {
    global $conf;
    // Relire depuis DB pour avoir la valeur la plus fraîche
    $res_tk = pwg_query("SELECT value FROM " . CONFIG_TABLE . " WHERE param = 'osm_map_tile_geotag'");
    $row_tk = pwg_db_fetch_assoc($res_tk);
    $tile_key = $row_tk ? $row_tk['value'] : (isset($conf['osm_map_tile_geotag']) ? $conf['osm_map_tile_geotag'] : 'carto_voyager');
    $tiles_map = array(
        'carto_voyager'  => array(osmme_carto_tile_url('https://{s}.basemaps.cartocdn.com/rastertiles/voyager/{z}/{x}/{y}.png'), '&copy; OpenStreetMap &copy; CARTO', 19),
        'osm_standard'   => array('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', '&copy; OpenStreetMap contributors', 19),
        'esri_satellite' => array('https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}', 'Tiles &copy; Esri', 18),
        'opentopo'       => array('https://{s}.tile.opentopomap.org/{z}/{x}/{y}.png', '&copy; OpenStreetMap &copy; OpenTopoMap', 17),
        'osm_france'     => array('https://{s}.tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png', '&copy; OpenStreetMap France | &copy; OpenStreetMap contributors', 20),
    );
    $t = isset($tiles_map[$tile_key]) ? $tiles_map[$tile_key] : $tiles_map['carto_voyager'];
    return '<script src="' . get_root_url() . 'plugins/osm_map/js/basemaps.js?v=' . OSM_MAP_ASSET_V . '"></script>'
         . '<script>window.OSM_BATCH_TILE_KEY=' . osmme_js($tile_key) . ';'
         . 'window.OSM_BATCH_ROOT=' . osmme_js(get_root_url()) . ';'
         . 'window.OSM_PLACES=' . osmme_js(osmme_places_for_batch()) . ';'
         . 'window.OSM_CARTO_API_KEY=' . osmme_js(osmme_resolve_carto_key()) . ';'
         . 'window.OSM_BATCH_TOKEN=' . osmme_js(get_pwg_token()) . ';'
         . 'window.OSM_BATCH_TILE_URL="' . addslashes($t[0]) . '";'
         . 'window.OSM_BATCH_TILE_ATTR="' . addslashes($t[1]) . '";'
         . 'window.OSM_BATCH_TILE_MAXZOOM=' . (int)$t[2] . ';</script>';
}

function osmme_loc_end_element_set_unit() {
    global $template, $conf;

    // Sécurité : ne s'exécute que si la variable template existe
    if (!isset($template) || !is_object($template)) return;

    $height = isset($conf['osm_map_unit_height']) ? (int)$conf['osm_map_unit_height'] : 280;
    $tpl    = realpath(dirname(__FILE__) . '/template/batch_unit.tpl');
    if (!$tpl) return;

    $template->append('head_elements', osmme_batch_head_script());

    $template->assign(array(
        'OSM_UNIT_HEIGHT'  => $height,
    ));
    $template->append('PLUGINS_BATCH_MANAGER_UNIT_ELEMENT_SUBTEMPLATE', $tpl);
}



// ============================================================================
// GÉOTAGGAGE — Mode global de la gestion par lot : action « OSM Map Plus »
// (grande carte, recherche de lieu, lieux favoris, fonds de carte ; une même
// position est appliquée à toutes les photos sélectionnées)
// ============================================================================
function osmme_loc_end_element_set_global() {
    global $template, $conf;
    if (!isset($template) || !is_object($template)) return;
    $tpl = realpath(dirname(__FILE__) . '/template/batch_global.tpl');
    if (!$tpl) return;

    $height = isset($conf['osm_map_edit_height']) ? (int)$conf['osm_map_edit_height'] : 400;
    $height = max(300, min(640, $height));

    $template->append('head_elements', osmme_batch_head_script());
    $template->set_filename('osmme_batch_global', $tpl);
    $template->assign(array('OSMME_G_HEIGHT' => $height, 'OSMME_G_TOKEN' => get_pwg_token()));
    $template->append('element_set_global_plugins_actions', array(
        'ID'      => 'osmmap_plus',
        'NAME'    => '&#127760; OSM Map Plus',
        'CONTENT' => $template->parse('osmme_batch_global', true),
    ));
}

function osmme_element_set_global_action($action, $collection) {
    global $page;
    if ($action !== 'osmmap_plus') return;
    if (!function_exists('is_admin') || !is_admin()) return;
    check_pwg_token();
    $ids = array_values(array_filter(array_map('intval', (array)$collection)));
    if (!$ids) { $page['warnings'][] = 'OSM Map Plus : aucune photo sélectionnée.'; return; }
    $in = implode(',', $ids);
    $lat = isset($_POST['osmme_g_lat']) ? trim($_POST['osmme_g_lat']) : '';
    $lon = isset($_POST['osmme_g_lon']) ? trim($_POST['osmme_g_lon']) : '';

    if (!empty($_POST['osmme_g_clear'])) {
        pwg_query('UPDATE ' . IMAGES_TABLE . ' SET latitude=NULL, longitude=NULL WHERE id IN (' . $in . ')');
        $page['infos'][] = 'OSM Map Plus : GPS effacé pour ' . count($ids) . ' photo(s).';
        return;
    }
    $lat = str_replace(',', '.', $lat);
    $lon = str_replace(',', '.', $lon);
    if ($lat === '' || $lon === '') {
        $page['errors'][] = 'OSM Map Plus : choisissez une position sur la carte (latitude/longitude vides).';
        return;
    }
    if (!is_numeric($lat) || !is_numeric($lon)
        || (float)$lat < -90 || (float)$lat > 90 || (float)$lon < -180 || (float)$lon > 180) {
        $page['errors'][] = 'OSM Map Plus : valeurs invalides (latitude -90→90, longitude -180→180).';
        return;
    }
    pwg_query('UPDATE ' . IMAGES_TABLE . ' SET latitude=' . (float)$lat . ', longitude=' . (float)$lon . ' WHERE id IN (' . $in . ')');
    $page['infos'][] = 'OSM Map Plus : position ' . (float)$lat . ', ' . (float)$lon . ' enregistrée pour ' . count($ids) . ' photo(s).';
}

// ============================================================================
// Injection Leaflet en admin (batch manager mode unitaire uniquement)
// ============================================================================
function osmme_inject_leaflet_admin() {
    // Uniquement sur batch_manager mode unit
    if (!isset($_GET['page']) || $_GET['page'] !== 'batch_manager') return;
    // mode unitaire, ou mode global (par défaut quand « mode » est absent) pour l'action « OSM Map Plus »
    $mode = isset($_GET['mode']) ? $_GET['mode'] : 'global';
    if ($mode !== 'unit' && $mode !== 'global') return;

    global $template;
    $template->append('head_elements',
        '<script>window.OSM_CARTO_API_KEY=' . osmme_js(osmme_resolve_carto_key()) . ';</script>' .
        '<style>.leaflet-container .leaflet-tile{opacity:1!important;visibility:visible!important;max-width:none!important;max-height:none!important;}.leaflet-tile-pane{opacity:1!important;visibility:visible!important;}</style>' .
        '<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />' . "\n" .
        '<script>' . osmme_loader_js() . '</script>'
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
    // Injecté via JS (menus très différents selon les thèmes : modus/default = « dd ul », Bootstrap = menu déroulant « Explorer »)
    $inner = '&#127760; OSM Map Plus';
    $js = <<<'JSEOF'
document.addEventListener('DOMContentLoaded', function () {
  var href = __HREF__, label = __LABEL__;
  if (document.querySelector('a[data-osmme-menu]')) return;
  function mk(tag, cls, inner) { var e = document.createElement(tag); if (cls) e.className = cls; e.innerHTML = inner; return e; }
  var keys = 'a[href*="search.php"],a[href*="about.php"],a[href*="tags.php"],a[href*="random.php"],a[href*="comments.php"],a[href*="notification.php"],a[href*="/most_visited"],a[href*="/recent_pics"],a[href*="/recent_cats"],a[href*="/favorites"],a[href*="calendar"]';

  // 1) Thèmes classiques (modus, default, elegant…) : liste « dd ul » du menu
  var lists = document.querySelectorAll('dd ul'), mn = null, i;
  for (i = 0; i < lists.length; i++) {
    if (lists[i].querySelector('a[href*=search],a[href*=about],a[href*=recent]')) { mn = lists[i]; break; }
  }
  if (mn) {
    var li = mk('li', '', '<a href="' + href + '" data-osmme-menu="1">' + label + '</a>');
    mn.appendChild(li);
    return;
  }

  // 2) Thèmes Bootstrap (Bootstrap Darkroom, Bootstrap Default…) : menu déroulant « Explorer »
  var drops = document.querySelectorAll('.navbar .dropdown-menu, nav .dropdown-menu, .dropdown-menu'), best = null, bestN = 0;
  // a) menu dont le titre est « Découvrir / Explorer / Explore / Discover »
  for (i = 0; i < drops.length && !best; i++) {
    var tg = drops[i].parentNode && drops[i].parentNode.querySelector('.dropdown-toggle');
    if (tg && /d[e\u00e9]couvrir|explor|discover/i.test(tg.textContent)) { best = drops[i]; }
  }
  // b) sinon le menu qui contient le plus de liens « de navigation » (recherche, à propos, favoris, au hasard…)
  if (!best) {
    for (i = 0; i < drops.length; i++) {
      var n = drops[i].querySelectorAll(keys).length;
      if (n > bestN) { best = drops[i]; bestN = n; }
    }
    if (bestN < 3) { best = null; }
  }
  if (best) {
    var sample = best.querySelector('a.dropdown-item') || best.querySelector('a');
    var isList = best.tagName === 'UL' || best.tagName === 'OL';
    var a = mk('a', sample ? sample.className : 'dropdown-item', label);
    a.href = href; a.setAttribute('data-osmme-menu', '1');
    if (isList) { var l2 = mk('li', '', ''); l2.appendChild(a); best.appendChild(l2); }
    else { best.appendChild(a); }
    return;
  }

  // 3) Dernier recours : entrée dans la barre de navigation Bootstrap
  var nav = document.querySelector('.navbar-nav');
  if (nav) {
    var item = mk('li', 'nav-item', '<a class="nav-link" href="' + href + '" data-osmme-menu="1">' + label + '</a>');
    nav.appendChild(item);
    return;
  }
  // 4) Aucun menu reconnu : dernière liste « dd ul » si elle existe
  if (lists.length) { lists[lists.length - 1].appendChild(mk('li', '', '<a href="' + href + '" data-osmme-menu="1">' + label + '</a>')); }
});
JSEOF;
    $js = str_replace(array('__HREF__', '__LABEL__'), array(osmme_js($url), osmme_js($inner)), $js);
    $template->append('head_elements', '<script>' . $js . '</script>');
}


// ============================================================================
// Page « Propriétés » d'un album : option d'affichage de la carte (prend la main sur le réglage général)
// ============================================================================
function osmme_cat_modify() {
    global $template, $conf;
    if (!isset($template) || !is_object($template)) return;
    if (!function_exists('is_admin') || !is_admin()) return;
    $cat_id = isset($_GET['cat_id']) ? (int)$_GET['cat_id'] : 0;
    if ($cat_id <= 0) return;

    // Enregistrement (avant l'affichage de la valeur courante)
    if (isset($_POST['submit']) && isset($_POST['osmme_album_display'])
        && isset($_POST['pwg_token']) && $_POST['pwg_token'] === get_pwg_token()) {
        $v = $_POST['osmme_album_display'];
        $all = osmme_album_positions();
        if (osmme_valid_pos($v)) { $all[$cat_id] = $v; } else { unset($all[$cat_id]); }
        $json = json_encode((object)$all);
        conf_update_param('osm_map_album_pos', $json);
        $conf['osm_map_album_pos'] = $json;
    }

    $all = osmme_album_positions();
    $cur = (isset($all[$cat_id]) && osmme_valid_pos($all[$cat_id])) ? $all[$cat_id] : '';
    $labels = array('top' => 'En haut', 'bottom' => 'En bas', 'none' => 'Pas d\'affichage');
    $gen = (isset($conf['osm_map_position']) && osmme_valid_pos($conf['osm_map_position'])) ? $conf['osm_map_position'] : 'bottom';

    $opts = '<option value=""' . ($cur === '' ? ' selected' : '') . '>Réglage général (actuellement : ' . $labels[$gen] . ')</option>';
    foreach ($labels as $k => $l) {
        $opts .= '<option value="' . $k . '"' . ($cur === $k ? ' selected' : '') . '>' . $l . '</option>';
    }
    $note = osmme_native_osm_active() ? ' (piwigo-openstreetmap est actif : « En haut » est traité comme « En bas ».)' : '';
    $cfg = array(
        'catId' => $cat_id,
        'token' => get_pwg_token(),
        'url'   => get_root_url() . 'plugins/osm_map/osmme-albumpos.php',
        'title' => 'OSM Map Plus — carte de l\'album',
        'help'  => 'Prend la main sur le réglage général d\'OSM Map Plus pour cet album.' . $note,
        'opts'  => $opts,
        'saved' => 'Enregistré',
    );

    $js = <<<'JSEOF'
document.addEventListener('DOMContentLoaded', function () {
  if (document.getElementById('osmme-cat-row')) return;
  var C = __CFG__;
  function esc(t) { var d = document.createElement('div'); d.textContent = t; return d.innerHTML; }

  // Piwigo 16+ : page sans <form>, enregistrement en AJAX -> on enregistre le choix dès qu'il change
  var box = document.querySelector('.cat-modify-form');
  if (box) {
    var row = document.createElement('div');
    row.className = 'cat-modify-input-container';
    row.id = 'osmme-cat-row';
    row.innerHTML = '<label for="osmme-album-display">&#127760; ' + esc(C.title) + '</label>'
      + '<select id="osmme-album-display" style="width:100%;padding:12px 14px;border:none;background:#f3f3f3;font-size:1rem;color:inherit;">' + C.opts + '</select>'
      + '<span style="display:block;margin-top:6px;color:#777;font-size:0.9em;">' + esc(C.help) + ' <b id="osmme-cat-msg" style="color:#2e7d32;"></b></span>';
    var sw = box.querySelectorAll('.cat-modify-switch-container'), last = sw.length ? sw[sw.length - 1] : null;
    if (last && last.parentNode === box) { box.insertBefore(row, last.nextSibling); } else { box.appendChild(row); }
    var sel = document.getElementById('osmme-album-display'), msg = document.getElementById('osmme-cat-msg');
    sel.addEventListener('change', function () {
      var fd = new FormData();
      fd.append('cat_id', C.catId); fd.append('pos', sel.value); fd.append('pwg_token', C.token);
      msg.style.color = '#777'; msg.textContent = '…';
      fetch(C.url, {method: 'POST', body: fd, credentials: 'same-origin'})
        .then(function (r) { return r.json(); })
        .then(function (d) {
          if (d && d.ok) { msg.style.color = '#2e7d32'; msg.textContent = '✓ ' + C.saved; }
          else { msg.style.color = '#c62828'; msg.textContent = '✗ Erreur'; }
        })
        .catch(function () { msg.style.color = '#c62828'; msg.textContent = '✗ Erreur'; });
    });
    return;
  }

  // Anciennes versions de Piwigo : formulaire classique, enregistré avec le bouton « Valider »
  var f = document.getElementById('catModify') || document.querySelector('form[action*="cat_modify"], form[action*="album-"]');
  if (!f) { var b0 = document.querySelector('input[name="submit"]'); f = b0 && b0.form; }
  if (!f) return;
  var p = document.createElement('p');
  p.id = 'osmme-cat-row'; p.style.margin = '16px 0';
  p.innerHTML = '<strong>&#127760; ' + esc(C.title) + '</strong><br><select name="osmme_album_display" style="margin-top:4px;">' + C.opts + '</select><br><span style="color:#777;font-size:0.9em;">' + esc(C.help) + '</span>';
  var btn = f.querySelector('input[name="submit"], button[name="submit"]');
  var anchor = btn ? (btn.closest('p, .formButtons, .buttons, div') || btn) : null;
  if (anchor && anchor.parentNode && f.contains(anchor)) { anchor.parentNode.insertBefore(p, anchor); } else { f.appendChild(p); }
});
JSEOF;
    $js = str_replace('__CFG__', json_encode($cfg, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE), $js);
    $template->append('head_elements', '<script>' . $js . '</script>');
}
