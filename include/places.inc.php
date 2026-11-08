<?php
/*
 * This file is part of OSM Map Plus (GPL-2.0-or-later).
 * Lieux favoris : table propre au plugin, sauvegarde dans _data, import depuis piwigo-openstreetmap.
 */
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

function osmme_pl_table() { global $prefixeTable; return $prefixeTable . 'osm_map_places'; }

function osmme_pl_ensure() {
    static $done = false;
    if ($done) { return; }
    $done = true;
    pwg_query('CREATE TABLE IF NOT EXISTS `' . osmme_pl_table() . '` (
        `id` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
        `latitude` double NOT NULL,
        `longitude` double NOT NULL,
        `name` varchar(255) NOT NULL,
        `parent_id` mediumint(8) unsigned DEFAULT NULL,
        PRIMARY KEY (`id`)
    ) DEFAULT CHARSET=utf8');
}

// Liste à plat, triée par nom
function osmme_pl_list() {
    osmme_pl_ensure();
    $out = array();
    $res = pwg_query('SELECT id, name, latitude, longitude, parent_id FROM `' . osmme_pl_table() . '` ORDER BY name ASC, id ASC');
    while ($r = pwg_db_fetch_assoc($res)) {
        $out[] = array('id' => (int)$r['id'], 'name' => $r['name'],
                       'lat' => (float)$r['latitude'], 'lon' => (float)$r['longitude'],
                       'parent' => $r['parent_id'] === null ? null : (int)$r['parent_id']);
    }
    return $out;
}

// Valide et normalise (nom, lat, lon) ; renvoie null si invalide
function osmme_pl_clean($name, $lat, $lon) {
    $name = trim(preg_replace('/\s+/u', ' ', strip_tags((string)$name)));
    if ($name === '') { return null; }
    if (function_exists('mb_substr')) { $name = mb_substr($name, 0, 255, 'UTF-8'); } else { $name = substr($name, 0, 255); }
    $lat = str_replace(',', '.', trim((string)$lat)); $lon = str_replace(',', '.', trim((string)$lon));
    if (!is_numeric($lat) || !is_numeric($lon)) { return null; }
    $lat = (float)$lat; $lon = (float)$lon;
    if (abs($lat) > 90 || abs($lon) > 180) { return null; }
    return array('name' => $name, 'lat' => round($lat, 6), 'lon' => round($lon, 6));
}

function osmme_pl_exists($name, $lat, $lon) {
    $r = pwg_db_fetch_assoc(pwg_query('SELECT COUNT(*) AS n FROM `' . osmme_pl_table() . '` WHERE name = \'' . pwg_db_real_escape_string($name)
        . '\' AND ABS(latitude - ' . (float)$lat . ') < 0.000002 AND ABS(longitude - ' . (float)$lon . ') < 0.000002'));
    return (int)$r['n'] > 0;
}

function osmme_pl_add($name, $lat, $lon, $parent = null) {
    osmme_pl_ensure();
    $c = osmme_pl_clean($name, $lat, $lon);
    if (!$c) { return 0; }
    pwg_query('INSERT INTO `' . osmme_pl_table() . '` (latitude, longitude, name, parent_id) VALUES (' . $c['lat'] . ',' . $c['lon'] . ',\''
        . pwg_db_real_escape_string($c['name']) . '\',' . ($parent ? (int)$parent : 'NULL') . ')');
    return (int)pwg_db_insert_id();
}

function osmme_pl_update($id, $name, $lat, $lon) {
    osmme_pl_ensure();
    $c = osmme_pl_clean($name, $lat, $lon);
    if (!$c || (int)$id <= 0) { return false; }
    pwg_query('UPDATE `' . osmme_pl_table() . '` SET name=\'' . pwg_db_real_escape_string($c['name']) . '\', latitude=' . $c['lat']
        . ', longitude=' . $c['lon'] . ' WHERE id=' . (int)$id);
    return true;
}

function osmme_pl_delete($id) {
    osmme_pl_ensure();
    pwg_query('DELETE FROM `' . osmme_pl_table() . '` WHERE id=' . (int)$id);
    pwg_query('UPDATE `' . osmme_pl_table() . '` SET parent_id=NULL WHERE parent_id=' . (int)$id);
}

function osmme_pl_file() {
    global $conf;
    $dir = isset($conf['data_location']) ? $conf['data_location'] : PHPWG_ROOT_PATH . '_data/';
    return rtrim($dir, '/') . '/osm_map_places.json';
}
function osmme_pl_dump() { return json_encode(array('plugin' => 'osm_map', 'version' => 1, 'places' => osmme_pl_list())); }
function osmme_pl_backup() {
    $f = osmme_pl_file();
    if (@is_dir(dirname($f))) { @file_put_contents($f, osmme_pl_dump(), LOCK_EX); }
}

// Import d'un JSON (sauvegarde ou export) ; ignore les doublons (nom + position). Renvoie le nombre ajouté.
function osmme_pl_import($json) {
    $d = json_decode($json, true);
    if (!is_array($d) || empty($d['places']) || !is_array($d['places'])) { return 0; }
    osmme_pl_ensure();
    $n = 0; $map = array();
    foreach ($d['places'] as $p) {
        if (!isset($p['name'], $p['lat'], $p['lon'])) { continue; }
        $c = osmme_pl_clean($p['name'], $p['lat'], $p['lon']);
        if (!$c || osmme_pl_exists($c['name'], $c['lat'], $c['lon'])) { continue; }
        $id = osmme_pl_add($c['name'], $c['lat'], $c['lon']);
        if ($id) { $n++; if (isset($p['id'])) { $map[(int)$p['id']] = $id; } }
    }
    foreach ($d['places'] as $p) {   // rétablit les parents
        if (!empty($p['parent']) && isset($p['id'], $map[(int)$p['id']], $map[(int)$p['parent']])) {
            pwg_query('UPDATE `' . osmme_pl_table() . '` SET parent_id=' . (int)$map[(int)$p['parent']] . ' WHERE id=' . (int)$map[(int)$p['id']]);
        }
    }
    return $n;
}

// Réinstallation : recharge _data/osm_map_places.json si la table est vide
function osmme_pl_restore() {
    osmme_pl_ensure();
    $r = pwg_db_fetch_assoc(pwg_query('SELECT COUNT(*) AS n FROM `' . osmme_pl_table() . '`'));
    $f = osmme_pl_file();
    if ((int)$r['n'] === 0 && @is_file($f)) { return osmme_pl_import(@file_get_contents($f)); }
    return 0;
}

// Table des lieux de piwigo-openstreetmap (si présente)
function osmme_pl_osm_table() {
    global $prefixeTable;
    $t = $prefixeTable . 'osm_places';
    $r = pwg_query('SHOW TABLES LIKE \'' . pwg_db_real_escape_string($t) . '\'');
    return pwg_db_fetch_assoc($r) ? $t : null;
}
function osmme_pl_import_osm() {
    $t = osmme_pl_osm_table();
    if (!$t) { return array('found' => false, 'imported' => 0); }
    $places = array();
    $res = pwg_query('SELECT id, name, latitude, longitude, parentId FROM `' . $t . '`');
    while ($r = pwg_db_fetch_assoc($res)) {
        $places[] = array('id' => (int)$r['id'], 'name' => $r['name'], 'lat' => $r['latitude'], 'lon' => $r['longitude'],
                          'parent' => $r['parentId'] === null ? null : (int)$r['parentId']);
    }
    return array('found' => true, 'imported' => osmme_pl_import(json_encode(array('places' => $places))), 'total' => count($places));
}
