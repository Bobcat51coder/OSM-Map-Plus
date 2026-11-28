<?php
/*
 * This file is part of OSM Map Plus (GPL-2.0-or-later).
 *
 * Liens vers les pages photo AVEC un album de contexte.
 * Sans album (picture.php?/ID), Piwigo ouvre la photo dans la liste « à plat » de toute la galerie
 * (index.php?/categories/flat/start-N) : le chevron « haut » mène alors à cette liste globale et non à
 * un album. On choisit donc un album auquel la photo appartient ET que le visiteur a le droit de voir.
 */
defined('PHPWG_ROOT_PATH') or die('Hacking attempt!');

// ids de photos => ligne d'album (id, name, permalink) : l'album préféré s'il convient, sinon le premier accessible
function osmme_pick_albums(array $ids, $prefer_cat = 0) {
    global $user;
    $ids = array_values(array_unique(array_filter(array_map('intval', $ids))));
    if (!$ids) return array();
    $where_forb = '';
    if (!empty($user['forbidden_categories'])) {
        $forb = array_filter(array_map('intval', explode(',', $user['forbidden_categories'])));
        if ($forb) $where_forb = ' AND c.id NOT IN (' . implode(',', $forb) . ')';
    }
    $prefer_cat = (int)$prefer_cat;
    $res = pwg_query('SELECT ic.image_id, c.id, c.name, c.permalink FROM ' . IMAGE_CATEGORY_TABLE . ' AS ic
        INNER JOIN ' . CATEGORIES_TABLE . ' AS c ON c.id = ic.category_id
        WHERE ic.image_id IN (' . implode(',', $ids) . ')' . $where_forb . '
        ORDER BY ic.image_id, (c.id = ' . $prefer_cat . ') DESC, c.id ASC');
    $out = array();
    while ($r = pwg_db_fetch_assoc($res)) {
        $iid = (int)$r['image_id'];
        if (!isset($out[$iid])) {
            $out[$iid] = array('id' => (int)$r['id'], 'name' => $r['name'], 'permalink' => $r['permalink']);
        }
    }
    return $out;
}

// URL absolue de la page photo ; $album = ligne renvoyée par osmme_pick_albums() (ou null : contexte global)
function osmme_picture_page_url($image_id, $file, $album, $base_url) {
    $params = array('image_id' => (int)$image_id, 'image_file' => $file);
    if (is_array($album) && !empty($album['id'])) $params['category'] = $album;
    $u = make_picture_url($params);
    if (strpos($u, 'http') !== 0) $u = $base_url . ltrim($u, '/');
    return $u;
}
