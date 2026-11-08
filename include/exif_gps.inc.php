<?php
/*
 * This file is part of OSM Map Plus (GPL-2.0-or-later).
 * Lecture (seule) du GPS EXIF d'un fichier et comparaison avec la base.
 */
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

function osmme_exif_rat($v) {
    if (is_array($v)) { return null; }
    if (is_numeric($v)) { return (float)$v; }
    if (is_string($v) && strpos($v, '/') !== false) {
        list($n, $d) = array_pad(explode('/', $v, 2), 2, 1);
        return ((float)$d != 0.0) ? (float)$n / (float)$d : null;
    }
    return null;
}
function osmme_exif_dms($arr, $ref) {
    if (!is_array($arr) || count($arr) < 3) { return null; }
    $d = osmme_exif_rat($arr[0]); $m = osmme_exif_rat($arr[1]); $sec = osmme_exif_rat($arr[2]);
    if ($d === null || $m === null || $sec === null) { return null; }
    $v = $d + $m / 60 + $sec / 3600;
    $ref = strtoupper(trim((string)$ref));
    if ($ref === 'S' || $ref === 'W') { $v = -$v; }
    return $v;
}
function osmme_read_exif_gps($file) {
    if (!function_exists('exif_read_data') || !is_file($file) || !is_readable($file)) { return null; }
    $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
    if (!in_array($ext, array('jpg', 'jpeg', 'tif', 'tiff'))) { return null; }
    $ex = @exif_read_data($file, 'GPS', false);
    if (!is_array($ex) || empty($ex['GPSLatitude']) || empty($ex['GPSLongitude'])) { return null; }
    $la = osmme_exif_dms($ex['GPSLatitude'],  isset($ex['GPSLatitudeRef'])  ? $ex['GPSLatitudeRef']  : 'N');
    $lo = osmme_exif_dms($ex['GPSLongitude'], isset($ex['GPSLongitudeRef']) ? $ex['GPSLongitudeRef'] : 'E');
    if ($la === null || $lo === null || abs($la) > 90 || abs($lo) > 180) { return null; }
    if ($la == 0 && $lo == 0) { return null; }
    return array('lat' => round($la, 6), 'lon' => round($lo, 6));
}
function osmme_distance_m($la1, $lo1, $la2, $lo2) {
    $r = 6371000; $p1 = deg2rad($la1); $p2 = deg2rad($la2);
    $dp = $p2 - $p1; $dl = deg2rad($lo2 - $lo1);
    $a = sin($dp/2)*sin($dp/2) + cos($p1)*cos($p2)*sin($dl/2)*sin($dl/2);
    return 2 * $r * asin(min(1, sqrt($a)));
}

// Condition SQL limitant aux fichiers lisibles en EXIF
function osmme_exif_sql_ext($col = 'path') {
    return "(LOWER($col) LIKE '%.jpg' OR LOWER($col) LIKE '%.jpeg' OR LOWER($col) LIKE '%.tif' OR LOWER($col) LIKE '%.tiff')";
}

// ── Écarts « validés » (on garde la base, on ne les resignale plus) ──────
function osmme_ok_table() { global $prefixeTable; return $prefixeTable . 'osm_map_exif_ok'; }

function osmme_ok_ensure() {
    static $done = false;
    if ($done) { return; }
    $done = true;
    pwg_query('CREATE TABLE IF NOT EXISTS `' . osmme_ok_table() . '` (
        `image_id` mediumint(8) unsigned NOT NULL,
        `lat_db` double DEFAULT NULL, `lon_db` double DEFAULT NULL,
        `lat_exif` double NOT NULL, `lon_exif` double NOT NULL,
        `validated_on` datetime NOT NULL,
        PRIMARY KEY (`image_id`)
    ) DEFAULT CHARSET=utf8');
}

// Lignes validées (id => ligne), pour une liste d'ids ou toutes si null
function osmme_ok_load($ids = null) {
    osmme_ok_ensure();
    $q = 'SELECT * FROM `' . osmme_ok_table() . '`';
    if (is_array($ids)) {
        $ids = array_map('intval', $ids);
        if (!$ids) { return array(); }
        $q .= ' WHERE image_id IN (' . implode(',', $ids) . ')';
    }
    $out = array();
    $res = pwg_query($q);
    while ($r = pwg_db_fetch_assoc($res)) { $out[(int)$r['image_id']] = $r; }
    return $out;
}

function osmme_close($a, $b) {   // ~2 m
    if ($a === null || $b === null || $a === '' || $b === '') { return ($a === null || $a === '') && ($b === null || $b === ''); }
    return abs((float)$a - (float)$b) < 0.00002;
}

// La validation ne vaut que tant que la base ET le fichier n'ont pas changé
function osmme_ok_matches($okrow, $row, $exif) {
    if (!$okrow || !$exif) { return false; }
    return osmme_close($okrow['lat_exif'], $exif['lat']) && osmme_close($okrow['lon_exif'], $exif['lon'])
        && osmme_close($okrow['lat_db'], $row['latitude']) && osmme_close($okrow['lon_db'], $row['longitude']);
}

function osmme_ok_file() {
    global $conf;
    $dir = isset($conf['data_location']) ? $conf['data_location'] : PHPWG_ROOT_PATH . '_data/';
    return rtrim($dir, '/') . '/osm_map_validated.json';
}

function osmme_ok_dump() {
    $items = array();
    foreach (osmme_ok_load() as $r) {
        $items[] = array('id' => (int)$r['image_id'],
            'lat_db' => $r['lat_db'] === null ? null : (float)$r['lat_db'], 'lon_db' => $r['lon_db'] === null ? null : (float)$r['lon_db'],
            'lat_exif' => (float)$r['lat_exif'], 'lon_exif' => (float)$r['lon_exif'], 'on' => $r['validated_on']);
    }
    return json_encode(array('plugin' => 'osm_map', 'version' => 1, 'items' => $items));
}

// Sauvegarde automatique dans _data (relue à la réinstallation)
function osmme_ok_backup() {
    $f = osmme_ok_file();
    if (@is_dir(dirname($f))) { @file_put_contents($f, osmme_ok_dump(), LOCK_EX); }
}

// Importe un JSON (sauvegarde ou export manuel) ; renvoie le nombre de lignes importées
function osmme_ok_import($json) {
    $d = json_decode($json, true);
    if (!is_array($d) || empty($d['items']) || !is_array($d['items'])) { return 0; }
    osmme_ok_ensure();
    $n = 0;
    foreach ($d['items'] as $it) {
        if (!isset($it['id'], $it['lat_exif'], $it['lon_exif']) || !is_numeric($it['id']) || !is_numeric($it['lat_exif']) || !is_numeric($it['lon_exif'])) { continue; }
        $ld = (isset($it['lat_db']) && is_numeric($it['lat_db'])) ? (float)$it['lat_db'] : 'NULL';
        $lo = (isset($it['lon_db']) && is_numeric($it['lon_db'])) ? (float)$it['lon_db'] : 'NULL';
        pwg_query('REPLACE INTO `' . osmme_ok_table() . '` (image_id, lat_db, lon_db, lat_exif, lon_exif, validated_on) VALUES ('
            . (int)$it['id'] . ',' . $ld . ',' . $lo . ',' . (float)$it['lat_exif'] . ',' . (float)$it['lon_exif'] . ',NOW())');
        $n++;
    }
    return $n;
}

// Réinstallation : recharge la sauvegarde de _data si la table est vide
function osmme_ok_restore() {
    osmme_ok_ensure();
    $r = pwg_db_fetch_assoc(pwg_query('SELECT COUNT(*) AS n FROM `' . osmme_ok_table() . '`'));
    $f = osmme_ok_file();
    if ((int)$r['n'] === 0 && @is_file($f)) { return osmme_ok_import(@file_get_contents($f)); }
    return 0;
}

// État d'une photo : 'ok' | 'diff' | 'dbempty' | 'noexif' (+ 'validated' => true si l'écart est validé)
function osmme_exif_compare($row, $threshold = 15, $okmap = null) {
    $e = osmme_read_exif_gps(PHPWG_ROOT_PATH . $row['path']);
    $has_db = ($row['latitude'] !== null && $row['latitude'] !== '' && $row['longitude'] !== null && $row['longitude'] !== ''
               && !((float)$row['latitude'] == 0 && (float)$row['longitude'] == 0));
    if (!$e) { return array('state' => 'noexif'); }
    if (!$has_db) {
        $c = array('state' => 'dbempty', 'exif' => $e);
    } else {
        $d = osmme_distance_m((float)$row['latitude'], (float)$row['longitude'], $e['lat'], $e['lon']);
        $c = array('state' => ($d > $threshold ? 'diff' : 'ok'), 'exif' => $e, 'dist' => round($d));
    }
    if ($okmap !== null && $c['state'] !== 'ok' && isset($okmap[(int)$row['id']]) && osmme_ok_matches($okmap[(int)$row['id']], $row, $e)) {
        $c['validated'] = true;
    }
    return $c;
}
