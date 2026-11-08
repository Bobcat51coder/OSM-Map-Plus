<?php
/*
 * This file is part of OSM Map Plus.
 * OSM Map Plus is free software distributed under the GNU General Public License, version 2 or later.
 */
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

/**
 * Installation / mise à jour / désinstallation du plugin (Piwigo 2.7+).
 * La classe porte le nom du dossier du plugin : osm_map_maintain.
 */
class osm_map_maintain extends PluginMaintain
{
    private $default_conf = array(
        'osm_map_public'        => '0',
        'osm_map_height'        => '500',
        'osm_map_plus_height'   => '600',
        'osm_map_max_photos'    => '5000',
        'osm_map_zoom'          => '5',
        'osm_map_tile'          => 'carto_voyager',
        'osm_map_tile_geotag'   => 'carto_voyager',
        'osm_map_tile_edit'     => 'carto_voyager',
        'osm_map_edit_height'   => '400',
        'osm_map_unit_height'   => '280',
        'osm_map_tile_photo'    => 'carto_voyager',
        'osm_map_photo_height'  => '220',
        'osm_map_hide_if_osm'   => '0',
        'osm_map_carto_api_key' => '',
    );

    function install($plugin_version, &$errors = array())
    {
        global $conf;
        foreach ($this->default_conf as $key => $val) {
            if (!isset($conf[$key])) {
                conf_update_param($key, $val);
                $conf[$key] = $val;
            }
        }
        // Table des écarts GPS/EXIF validés + rechargement de la sauvegarde de _data (réinstallation)
        include_once(dirname(__FILE__) . '/include/exif_gps.inc.php');
        osmme_ok_ensure();
        osmme_ok_restore();
        include_once(dirname(__FILE__) . '/include/places.inc.php');
        osmme_pl_ensure();
        osmme_pl_restore();
    }

    function update($old_version, $new_version, &$errors = array())
    {
        // Ajoute les paramètres apparus dans les versions récentes, sans écraser les réglages existants
        $this->install($new_version, $errors);
    }

    function activate($plugin_version, &$errors = array())
    {
    }

    function deactivate()
    {
    }

    function uninstall()
    {
        // Supprime les réglages du plugin
        conf_delete_param(array_keys($this->default_conf));
        // Écarts validés : sauvegarde dans _data/osm_map_validated.json (relue à la réinstallation), puis suppression de la table
        include_once(dirname(__FILE__) . '/include/exif_gps.inc.php');
        osmme_ok_backup();
        pwg_query('DROP TABLE IF EXISTS `' . osmme_ok_table() . '`');
        // Lieux favoris : même principe
        include_once(dirname(__FILE__) . '/include/places.inc.php');
        osmme_pl_backup();
        pwg_query('DROP TABLE IF EXISTS `' . osmme_pl_table() . '`');
    }
}
