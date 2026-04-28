<?php
/*
 * This file is part of [OSM-Map-Plus].
 *
 * [Nom de ton plugin] is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 2 of the License, or
 * (at your option) any later version.
 *
 * [Nom de ton plugin] is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with [Nom de ton plugin].  If not, see <https://www.gnu.org/licenses/>.
 */
 
// OSM Map Enhanced — admin_boot.php
// Chargé uniquement en admin (IN_ADMIN), pattern identique à piwigo-openstreetmap
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

// Hook batch manager mode unitaire
add_event_handler('loc_end_element_set_unit', 'osmme_loc_end_element_set_unit');

// Injection Leaflet sur batch_manager?mode=unit
add_event_handler('loc_end_page_header', 'osmme_inject_leaflet_admin');

// Onglet OpenStreetMap sur la page d'édition photo
add_event_handler('tabsheet_before_select', 'osmme_photo_add_tab', 50, 2);
function osmme_photo_add_tab($sheets, $id)
{
    if ($id == 'photo') {
        $image_id = isset($_GET['image_id']) ? (int)$_GET['image_id'] : 0;
        $base = get_root_url() . 'admin.php?page=photo-' . $image_id;

        // Corriger les URLs natives mal construites (commencent par '-')
        foreach ($sheets as $key => &$sheet) {
            if (isset($sheet['url']) && substr($sheet['url'], 0, 1) === '-') {
                $sheet['url'] = $base . $sheet['url'];
            }
        }
        unset($sheet);

        $sheets['osmme_enhanced'] = array(
            'caption' => '&#127760; OSM Map Plus',
            'url'     => get_root_url() . 'admin.php?page=plugin&amp;section=osm_map/admin/photo_edit.php&amp;image_id=' . $image_id,
        );
    }
    return $sheets;
}
