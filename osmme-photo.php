<?php
/**
 * OSM Map Plus — Endpoint détail photo (appelé au clic sur un marqueur)
 * Retourne nom, miniature, album, date pour un id donné
 */
define('PHPWG_ROOT_PATH', '../../');
ob_start();
include_once(PHPWG_ROOT_PATH . 'include/common.inc.php');
ob_end_clean();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=3600');
header('X-Content-Type-Options: nosniff');

$is_logged    = (isset($user) && isset($user['id']) && (int)$user['id'] > 0
                  && isset($user['status']) && $user['status'] !== 'guest');
$allow_public = !empty($conf['osm_map_public']);

if (!$is_logged && !$allow_public) {
    echo json_encode(array('error' => 'forbidden'));
    exit;
}

// Charger les helpers Piwigo pour les derivatives
$root_path = PHPWG_ROOT_PATH;
foreach (['include/derivative_params.inc.php','include/derivative_std_params.inc.php','include/functions_picture.inc.php'] as $f) {
    if (file_exists($root_path . $f)) include_once($root_path . $f);
}

// Ce script est dans plugins/osm_map/ : get_root_url() y vaut "../../", chemin faux
// pour la page qui affiche la carte (ex. /piwigo/ -> /_data/...). On force des URL
// absolues (racine du site comprise), y compris celles de make_picture_url().
set_make_full_url();
$base_url = get_root_url();
include_once(dirname(__FILE__) . '/include/picture_url.inc.php');
$prefer_cat = isset($_GET['cat_id']) ? (int)$_GET['cat_id'] : 0;   // album filtré sur la carte (contexte de navigation)

// Helper : construire les données d'une photo
function osmme_build_photo_data($row, $base_url, $album = null) {
    $thumb = '';
    try {
        $src   = new SrcImage($row);
        $deriv = new DerivativeImage(ImageStdParams::get_by_type(IMG_THUMB), $src);
        $url   = $deriv->get_url();
        $thumb = (strpos($url, 'http') === 0) ? $url : $base_url . ltrim($url, './');
    } catch (Exception $e) {
        if (!empty($row['path'])) {
            $path     = preg_replace('/^\.\//', '', $row['path']);
            $dir      = pathinfo($path, PATHINFO_DIRNAME);
            $basename = pathinfo($path, PATHINFO_FILENAME);
            $ext      = pathinfo($path, PATHINFO_EXTENSION);
            $thumb    = $base_url . '_data/i/' . $dir . '/' . $basename . '-th.' . $ext;
        }
    }
    $title    = $row['name'] ? $row['name'] : pathinfo($row['file'], PATHINFO_FILENAME);
    $page_url = osmme_picture_page_url($row['id'], $row['file'], $album, $base_url);
    return array(
        'id'            => (int)$row['id'],
        'name'          => htmlspecialchars($title, ENT_QUOTES),
        'date'          => $row['date_creation'] ? substr($row['date_creation'], 0, 10) : '',
        'album_name'    => (string)$row['album_name'],
        'thumbnail_url' => $thumb,
        'page_url'      => $page_url,
    );
}

// Mode batch : ids=1,2,3,...  (max 100)
if (!empty($_GET['ids'])) {
    $ids = array_slice(
        array_filter(array_map('intval', explode(',', $_GET['ids']))),
        0, 100
    );
    if (empty($ids)) { echo '[]'; exit; }

    $query = 'SELECT i.id, i.name, i.file, i.path, i.date_creation,
            MIN(ic.category_id) AS album_id,
            (SELECT c.name FROM ' . CATEGORIES_TABLE . ' AS c WHERE c.id = MIN(ic.category_id)) AS album_name
        FROM ' . IMAGES_TABLE . ' AS i
        LEFT JOIN ' . IMAGE_CATEGORY_TABLE . ' AS ic ON ic.image_id = i.id
        WHERE i.id IN (' . implode(',', $ids) . ')
        GROUP BY i.id, i.name, i.file, i.path, i.date_creation
        ORDER BY FIELD(i.id, ' . implode(',', $ids) . ')';

    $result = pwg_query($query);
    $photos = array();
    $albums = osmme_pick_albums($ids, $prefer_cat);
    while ($row = pwg_db_fetch_assoc($result)) {
        $album = isset($albums[(int)$row['id']]) ? $albums[(int)$row['id']] : null;
        // le nom d'album affiché est celui du contexte de navigation
        if ($album) $row['album_name'] = $album['name'];
        $photos[] = osmme_build_photo_data($row, $base_url, $album);
    }
    echo json_encode($photos);
    exit;
}

// Mode unitaire : id=X
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    echo json_encode(array('error' => 'missing id'));
    exit;
}

$query = 'SELECT i.id, i.name, i.file, i.path, i.date_creation,
        MIN(ic.category_id) AS album_id,
        (SELECT c.name FROM ' . CATEGORIES_TABLE . ' AS c WHERE c.id = MIN(ic.category_id)) AS album_name
    FROM ' . IMAGES_TABLE . ' AS i
    LEFT JOIN ' . IMAGE_CATEGORY_TABLE . ' AS ic ON ic.image_id = i.id
    WHERE i.id = ' . $id . '
    GROUP BY i.id, i.name, i.file, i.path, i.date_creation';

$result = pwg_query($query);
$row    = pwg_db_fetch_assoc($result);

if (!$row) { echo json_encode(array('error' => 'not found')); exit; }

$albums = osmme_pick_albums(array($id), $prefer_cat);
$album  = isset($albums[$id]) ? $albums[$id] : null;
if ($album) $row['album_name'] = $album['name'];
echo json_encode(osmme_build_photo_data($row, $base_url, $album));
exit;
