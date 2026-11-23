<?php
/**
 * OSM Map Plus — enregistrement du réglage d'affichage de la carte pour un album
 * (appelé par la page « Propriétés » de l'album, Piwigo 16+ : enregistrement en AJAX)
 *   POST cat_id, pos ('' = réglage général | top | bottom | none), pwg_token
 */
define('PHPWG_ROOT_PATH', '../../');
ob_start();
include_once(PHPWG_ROOT_PATH . 'include/common.inc.php');
ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if (!function_exists('is_admin') || !is_admin()) { echo json_encode(array('error' => 'forbidden')); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') { echo json_encode(array('error' => 'bad request')); exit; }
check_pwg_token();

$cat_id = isset($_POST['cat_id']) ? (int)$_POST['cat_id'] : 0;
$pos    = isset($_POST['pos']) ? (string)$_POST['pos'] : '';
if ($cat_id <= 0 || !in_array($pos, array('', 'top', 'bottom', 'none'), true)) { echo json_encode(array('error' => 'invalid')); exit; }

$all = json_decode(isset($conf['osm_map_album_pos']) ? $conf['osm_map_album_pos'] : '{}', true);
if (!is_array($all)) $all = array();
if ($pos === '') { unset($all[$cat_id]); } else { $all[$cat_id] = $pos; }
conf_update_param('osm_map_album_pos', json_encode((object)$all));
echo json_encode(array('ok' => true, 'pos' => $pos));
