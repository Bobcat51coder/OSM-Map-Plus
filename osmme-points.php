<?php
/**
 * OSM Map Plus — Endpoint points GPS
 * Retourne [[id, lat, lng], ...] SANS AUCUNE LIMITE
 * Même logique que piwigo-openstreetmap
 */
if (!defined('PHPWG_ROOT_PATH'))
    define('PHPWG_ROOT_PATH', '../../');

ob_start();
include_once(PHPWG_ROOT_PATH . 'include/common.inc.php');
ob_end_clean();

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, max-age=300');
header('X-Content-Type-Options: nosniff');

// Auth via objet $user Piwigo (identique à tous les plugins natifs)
$is_guest  = empty($user) || !isset($user['status']) || $user['status'] === 'guest';
$allow_pub = !empty($conf['osm_map_public']);

if ($is_guest && !$allow_pub) {
    echo '[]';
    exit;
}

// Filtre album optionnel
$cat_id    = isset($_GET['cat_id']) ? (int)$_GET['cat_id'] : 0;
$cat_join  = '';
$cat_where = '';
if ($cat_id > 0) {
    $cat_join  = ' INNER JOIN ' . IMAGE_CATEGORY_TABLE . ' AS ic_f ON ic_f.image_id = i.id';
    $cat_where = ' AND ic_f.category_id = ' . $cat_id;
}

// Pour les visiteurs non connectés : albums publics seulement
$pub_join  = '';
$pub_where = '';
if ($is_guest) {
    $pub_join  = ' INNER JOIN ' . IMAGE_CATEGORY_TABLE . ' AS ic_pub ON ic_pub.image_id = i.id'
               . ' INNER JOIN ' . CATEGORIES_TABLE    . ' AS c_pub  ON c_pub.id = ic_pub.category_id';
    $pub_where = " AND c_pub.status = 'public'";
}

// Pour les utilisateurs connectés : logique exacte de Piwigo
// Exclure une photo seulement si TOUTES ses catégories sont interdites
// (même logique que l'API native Piwigo)
$forbidden_where = '';
if (!$is_guest && !empty($user['forbidden_categories'])) {
    $forbidden_ids = implode(',', array_map('intval', explode(',', $user['forbidden_categories'])));
    // Photo exclue si elle n'apparaît dans AUCUN album autorisé
    $forbidden_where = ' AND i.id IN ('
        . 'SELECT image_id FROM ' . IMAGE_CATEGORY_TABLE
        . ' WHERE category_id NOT IN (' . $forbidden_ids . '))';
}

// Requête sans LIMIT — toutes les photos GPS accessibles
$query = 'SELECT DISTINCT i.id, i.latitude, i.longitude
    FROM ' . IMAGES_TABLE . ' AS i'
    . $pub_join
    . $cat_join
    . ' WHERE i.latitude IS NOT NULL AND i.longitude IS NOT NULL
      AND i.latitude <> 0 AND i.longitude <> 0'
    . $pub_where
    . $forbidden_where
    . $cat_where
    . ' ORDER BY i.id';

$result = pwg_query($query);
$points = array();
while ($row = pwg_db_fetch_assoc($result)) {
    $points[] = array(
        (int)$row['id'],
        round((float)$row['latitude'], 6),
        round((float)$row['longitude'], 6)
    );
}

echo json_encode($points);
exit;
