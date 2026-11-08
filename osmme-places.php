<?php
/**
 * OSM Map Plus — Lieux favoris (administrateurs uniquement)
 *   GET  ?list=1 | ?export=1
 *   POST add / update / delete / import (fichier) / import_osm  (+ pwg_token)
 */
define('PHPWG_ROOT_PATH', '../../');
ob_start();
include_once(PHPWG_ROOT_PATH . 'include/common.inc.php');
ob_end_clean();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');

if (!function_exists('is_admin') || !is_admin()) { echo json_encode(array('error' => 'forbidden')); exit; }
include_once(dirname(__FILE__) . '/include/places.inc.php');

if (!empty($_GET['export'])) {
    header('Content-Disposition: attachment; filename="osm_map_lieux_favoris.json"');
    echo osmme_pl_dump();
    exit;
}
if (!empty($_GET['list'])) { echo json_encode(array('places' => osmme_pl_list())); exit; }

$out = array('error' => 'bad request');
if (!empty($_POST['add']) || !empty($_POST['update']) || !empty($_POST['delete']) || !empty($_POST['import']) || !empty($_POST['import_osm'])) {
    check_pwg_token();
    $g = function($k) { return isset($_POST[$k]) ? $_POST[$k] : ''; };
    if (!empty($_POST['add'])) {
        $id = osmme_pl_add($g('name'), $g('lat'), $g('lon'));
        $out = $id ? array('ok' => true, 'id' => $id) : array('error' => 'invalid');
    } elseif (!empty($_POST['update'])) {
        $out = osmme_pl_update((int)$g('id'), $g('name'), $g('lat'), $g('lon')) ? array('ok' => true) : array('error' => 'invalid');
    } elseif (!empty($_POST['delete'])) {
        osmme_pl_delete((int)$g('id'));
        $out = array('ok' => true);
    } elseif (!empty($_POST['import'])) {
        $json = isset($_FILES['file']['tmp_name']) && is_uploaded_file($_FILES['file']['tmp_name']) ? file_get_contents($_FILES['file']['tmp_name']) : '';
        $out = array('imported' => $json !== '' ? osmme_pl_import($json) : 0);
    } else {
        $out = osmme_pl_import_osm();
    }
    osmme_pl_backup();
}
echo json_encode($out);
