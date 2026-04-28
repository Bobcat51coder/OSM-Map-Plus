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
// OSM Map Enhanced - Web Service Functions v2.0.2
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

function ws_osm_photos_getGeolocated($params, &$service) {
    global $user;

    $per_page = min((int)$params['per_page'], 1000);
    $page     = max((int)$params['page'], 0);
    $offset   = $page * $per_page;

    // Filtre album (inclut sous-albums via uppercats)
    $cat_filter = '';
    if (!empty($params['cat_id'])) {
        $cat_id = (int)$params['cat_id'];
        $sub_ids = array($cat_id);
        $sub_result = pwg_query('SELECT id FROM ' . CATEGORIES_TABLE
            . ' WHERE uppercats REGEXP \'(^|,)' . $cat_id . '(,|$)\'');
        while ($sub_row = pwg_db_fetch_assoc($sub_result)) {
            $sub_ids[] = (int)$sub_row['id'];
        }
        $sub_ids = array_unique($sub_ids);
        $cat_filter = ' AND i.id IN (SELECT ic.image_id FROM '
            . IMAGE_CATEGORY_TABLE . ' AS ic WHERE ic.category_id IN ('
            . implode(',', $sub_ids) . '))';
    }

    // Requete principale - photos avec GPS
    $query = '
        SELECT
            i.id,
            i.name,
            i.file,
            i.date_creation,
            i.latitude,
            i.longitude,
            i.path,
            i.tn_ext,
            MIN(ic2.category_id) AS album_id,
            (SELECT c2.name FROM ' . CATEGORIES_TABLE . ' AS c2
             WHERE c2.id = MIN(ic2.category_id)) AS album_name
        FROM ' . IMAGES_TABLE . ' AS i
        LEFT JOIN ' . IMAGE_CATEGORY_TABLE . ' AS ic2 ON ic2.image_id = i.id
        WHERE i.latitude IS NOT NULL
          AND i.longitude IS NOT NULL
          AND i.latitude <> 0
          AND i.longitude <> 0
        ' . $cat_filter . '
        GROUP BY i.id, i.name, i.file, i.date_creation, i.latitude, i.longitude, i.path, i.tn_ext
        ORDER BY i.date_creation DESC
        LIMIT ' . $per_page . ' OFFSET ' . $offset;

    $result = pwg_query($query);
    $photos = array();
    $root   = get_root_url();

    while ($row = pwg_db_fetch_assoc($result)) {
        // URL miniature : Piwigo stocke dans upload/ avec suffixe -sq (square) ou -th
        // Le champ path est relatif ex: "2024/01/nomfichier" sans extension
        $path     = ltrim($row['path'], '/');
        $tn_ext   = $row['tn_ext'] ? $row['tn_ext'] : 'jpg';
        // Miniature carrée (sq) — toujours présente dans Piwigo
        $thumb_url = $root . 'upload/' . $path . '-sq.' . $tn_ext;

        $photos[] = array(
            'id'            => (int)$row['id'],
            'name'          => $row['name'] ? $row['name'] : pathinfo($row['file'], PATHINFO_FILENAME),
            'latitude'      => (float)$row['latitude'],
            'longitude'     => (float)$row['longitude'],
            'date'          => (string)$row['date_creation'],
            'album_id'      => (int)$row['album_id'],
            'album_name'    => (string)$row['album_name'],
            'thumbnail_url' => $thumb_url,
            'page_url'      => $root . 'index.php?/photo/' . (int)$row['id'],
        );
    }

    // Total
    $count_result = pwg_query('SELECT COUNT(*) AS total FROM ' . IMAGES_TABLE
        . ' WHERE latitude IS NOT NULL AND longitude IS NOT NULL'
        . ' AND latitude <> 0 AND longitude <> 0' . $cat_filter);
    $count_row = pwg_db_fetch_assoc($count_result);
    $total     = (int)$count_row['total'];

    return array(
        'photos'   => $photos,
        'total'    => $total,
        'page'     => $page,
        'per_page' => $per_page,
        'pages'    => $total > 0 ? (int)ceil($total / $per_page) : 0,
    );
}
