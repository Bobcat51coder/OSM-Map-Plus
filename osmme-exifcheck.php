<?php
/**
 * OSM Map Plus — Contrôle base / EXIF (administrateurs uniquement)
 *   ?ids=1,2,3                       -> état de ces photos
 *   ?scan=1&after=0&limit=100&threshold=15&dbempty=1  -> analyse par paquets
 *   POST apply=1&ids=1,2&pwg_token=  -> copie le GPS EXIF du fichier vers la base
 */
define('PHPWG_ROOT_PATH', '../../');
ob_start();
include_once(PHPWG_ROOT_PATH . 'include/common.inc.php');
ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if (!function_exists('is_admin') || !is_admin()) {
    echo json_encode(array('error' => 'forbidden'));
    exit;
}
include_once(dirname(__FILE__) . '/include/exif_gps.inc.php');
foreach (array('include/derivative_params.inc.php','include/derivative_std_params.inc.php','include/functions_picture.inc.php') as $f_inc) {
    if (file_exists(PHPWG_ROOT_PATH . $f_inc)) { include_once(PHPWG_ROOT_PATH . $f_inc); }
}
function osmme_thumb_url($row) {
    try {
        $u = (new DerivativeImage(ImageStdParams::get_by_type(IMG_SQUARE), new SrcImage($row)))->get_url();
        return (strpos($u, 'http') === 0 || $u[0] === '/') ? $u : get_root_url() . ltrim($u, './');
    } catch (Throwable $e) { return ''; }
}

function osmme_ids_param($v) {
    $out = array();
    foreach (explode(',', (string)$v) as $x) { $x = (int)$x; if ($x > 0) { $out[$x] = $x; } }
    return array_slice(array_values($out), 0, 300);
}
$threshold = isset($_REQUEST['threshold']) ? max(1, min(100000, (int)$_REQUEST['threshold'])) : 15;
$exif_ok   = function_exists('exif_read_data');

// ── Validation / retrait de validation / export / import ──────────────────
if (!empty($_POST['validate']) || !empty($_POST['unvalidate'])) {
    check_pwg_token();
    osmme_ok_ensure();
    $ids = osmme_ids_param(isset($_POST['ids']) ? $_POST['ids'] : '');
    $done = array();
    if ($ids) {
        if (!empty($_POST['validate'])) {
            $res = pwg_query('SELECT * FROM ' . IMAGES_TABLE . ' WHERE id IN (' . implode(',', $ids) . ')');
            while ($row = pwg_db_fetch_assoc($res)) {
                $e = osmme_read_exif_gps(PHPWG_ROOT_PATH . $row['path']);
                if (!$e) { continue; }
                $has = ($row['latitude'] !== null && $row['latitude'] !== '' && $row['longitude'] !== null && $row['longitude'] !== '');
                pwg_query('REPLACE INTO `' . osmme_ok_table() . '` (image_id, lat_db, lon_db, lat_exif, lon_exif, validated_on) VALUES ('
                    . (int)$row['id'] . ',' . ($has ? (float)$row['latitude'] : 'NULL') . ',' . ($has ? (float)$row['longitude'] : 'NULL') . ','
                    . (float)$e['lat'] . ',' . (float)$e['lon'] . ',NOW())');
                $done[] = (int)$row['id'];
            }
        } else {
            pwg_query('DELETE FROM `' . osmme_ok_table() . '` WHERE image_id IN (' . implode(',', $ids) . ')');
            $done = $ids;
        }
        osmme_ok_backup();
    }
    echo json_encode(array('done' => $done));
    exit;
}
if (!empty($_GET['export'])) {
    header('Content-Type: application/json; charset=utf-8');
    header('Content-Disposition: attachment; filename="osm_map_ecarts_valides.json"');
    echo osmme_ok_dump();
    exit;
}
if (!empty($_POST['import'])) {
    check_pwg_token();
    $json = isset($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name']) ? file_get_contents($_FILES['file']['tmp_name']) : '';
    $n = $json !== '' ? osmme_ok_import($json) : 0;
    if ($n) { osmme_ok_backup(); }
    echo json_encode(array('imported' => $n));
    exit;
}

// ── Application : EXIF -> base (valeurs relues côté serveur) ──────────────
if (!empty($_POST['apply'])) {
    check_pwg_token();
    $ids = osmme_ids_param(isset($_POST['ids']) ? $_POST['ids'] : '');
    $done = array();
    if ($ids) {
        $res = pwg_query('SELECT id, path, latitude, longitude FROM ' . IMAGES_TABLE . ' WHERE id IN (' . implode(',', $ids) . ')');
        while ($row = pwg_db_fetch_assoc($res)) {
            $e = osmme_read_exif_gps(PHPWG_ROOT_PATH . $row['path']);
            if ($e) {
                pwg_query('UPDATE ' . IMAGES_TABLE . ' SET latitude=' . (float)$e['lat'] . ', longitude=' . (float)$e['lon'] . ' WHERE id=' . (int)$row['id']);
                $done[] = (int)$row['id'];
            }
        }
        if (function_exists('invalidate_user_cache')) { invalidate_user_cache(); }
    }
    echo json_encode(array('applied' => $done));
    exit;
}

// ── Analyse de toute la galerie, par paquets ──────────────────────────────
if (!empty($_GET['scan'])) {
    $after = max(0, (int)(isset($_GET['after']) ? $_GET['after'] : 0));
    $limit = max(10, min(300, (int)(isset($_GET['limit']) ? $_GET['limit'] : 100)));
    $inc_dbempty = !empty($_GET['dbempty']);
    $show_valid  = !empty($_GET['showvalid']);
    $okall = osmme_ok_load();
    $total = null;
    if ($after === 0) {
        $r = pwg_db_fetch_assoc(pwg_query('SELECT COUNT(*) AS n FROM ' . IMAGES_TABLE . ' WHERE ' . osmme_exif_sql_ext()));
        $total = (int)$r['n'];
    }
    $t0 = microtime(true);
    $res = pwg_query('SELECT * FROM ' . IMAGES_TABLE
        . ' WHERE id > ' . $after . ' AND ' . osmme_exif_sql_ext() . ' ORDER BY id ASC LIMIT ' . $limit);
    $rows = array(); $scanned = 0; $last = $after; $n = 0; $cut = false;
    while ($row = pwg_db_fetch_assoc($res)) {
        $n++;
        if (microtime(true) - $t0 > 6) { $cut = true; break; }   // budget de temps
        $scanned++; $last = (int)$row['id'];
        $c = osmme_exif_compare($row, $threshold, $okall);
        if (!empty($c['validated']) && !$show_valid) { continue; }
        if ($c['state'] === 'diff' || ($inc_dbempty && $c['state'] === 'dbempty')) {
            $rows[] = array(
                'id' => (int)$row['id'],
                'name' => $row['name'] !== null && $row['name'] !== '' ? $row['name'] : pathinfo($row['file'], PATHINFO_FILENAME),
                'state' => $c['state'],
                'thumb' => osmme_thumb_url($row),
                'date' => !empty($row['date_creation']) ? substr($row['date_creation'], 0, 10) : '',
                'db' => ($c['state'] === 'diff') ? array('lat' => (float)$row['latitude'], 'lon' => (float)$row['longitude']) : null,
                'exif' => $c['exif'],
                'dist' => isset($c['dist']) ? $c['dist'] : null,
                'validated' => !empty($c['validated']),
            );
        }
    }
    echo json_encode(array('rows' => $rows, 'scanned' => $scanned, 'last' => $last,
        'done' => (!$cut && $n < $limit), 'total' => $total, 'exif_ext' => $exif_ok));
    exit;
}

// ── État de quelques photos (gestion par lot) ─────────────────────────────
$ids = osmme_ids_param(isset($_GET['ids']) ? $_GET['ids'] : '');
$out = array();
if ($ids) {
    $okmap = osmme_ok_load($ids);
    $res = pwg_query('SELECT id, path, latitude, longitude FROM ' . IMAGES_TABLE . ' WHERE id IN (' . implode(',', $ids) . ')');
    while ($row = pwg_db_fetch_assoc($res)) {
        $c = osmme_exif_compare($row, $threshold, $okmap);
        $c['id'] = (int)$row['id'];
        $out[] = $c;
    }
}
echo json_encode(array('items' => $out, 'exif_ext' => $exif_ok));
