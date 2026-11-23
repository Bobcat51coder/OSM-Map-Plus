<?php
/*
 * This file is part of OSM Map Plus (GPL-2.0-or-later).
 * Barre d'onglets commune aux pages d'administration du plugin.
 */
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

function osmme_admin_tabs($active) {
    $root = get_root_url() . 'admin.php?page=';
    $tabs = array(
        'settings' => array('&#9881;&#65039; Param&egrave;tres',            $root . 'plugin-osm_map'),
        'places'   => array('&#11088; Lieux favoris',                         $root . 'plugin&amp;section=osm_map/admin/places.php'),
        'no_gps'   => array('&#128205; Photos sans GPS',                      $root . 'plugin&amp;section=osm_map/admin/no_gps.php'),
        'audit'    => array('&#128270; &Eacute;carts GPS / EXIF',            $root . 'plugin&amp;section=osm_map/admin/exif_audit.php'),
    );
    $h = '<style>'
       . '.osmme-tabs{display:flex;gap:4px;flex-wrap:wrap;margin:0 0 16px;padding:0;border-bottom:2px solid #ddd;list-style:none;}'
       . '.osmme-tabs li{margin:0;}'
       . '.osmme-tabs a{display:block;padding:9px 16px;margin-bottom:-2px;border:2px solid transparent;border-radius:6px 6px 0 0;color:#555;text-decoration:none;font-weight:600;font-size:0.95rem;}'
       . '.osmme-tabs a:hover{background:#f3f3f3;}'
       . '.osmme-tabs li.on a{background:#fff;color:#1a73e8;border-color:#ddd #ddd #fff;}'
       . '</style><ul class="osmme-tabs">';
    foreach ($tabs as $k => $t) {
        $h .= '<li' . ($k === $active ? ' class="on"' : '') . '><a href="' . $t[1] . '">' . $t[0] . '</a></li>';
    }
    return $h . '</ul>';
}
