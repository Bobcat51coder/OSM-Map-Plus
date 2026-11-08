<?php
/**
 * OSM Map Plus — Diaporama des photos de la zone visible sur la carte
 * Ouvert dans un nouvel onglet depuis le bouton « Diaporama » du panneau liste.
 *
 * Paramètres GET : s, w, n, e (zone géographique), cat_id (filtre album), order (date|date_desc)
 */
if (!defined('PHPWG_ROOT_PATH'))
    define('PHPWG_ROOT_PATH', '../../');

ob_start();
include_once(PHPWG_ROOT_PATH . 'include/common.inc.php');
ob_end_clean();

foreach (array('include/derivative_params.inc.php', 'include/derivative_std_params.inc.php', 'include/functions_picture.inc.php') as $f) {
    if (file_exists(PHPWG_ROOT_PATH . $f)) include_once(PHPWG_ROOT_PATH . $f);
}

// Mêmes règles d'accès que osmme-points.php
$is_guest  = empty($user) || !isset($user['status']) || $user['status'] === 'guest';
$allow_pub = !empty($conf['osm_map_public']);
if ($is_guest && !$allow_pub) {
    redirect(make_index_url());
}

$MAX = 500;
$s = isset($_GET['s']) ? max(-90, min(90, (float)$_GET['s'])) : -90;
$n = isset($_GET['n']) ? max(-90, min(90, (float)$_GET['n'])) : 90;
$w = isset($_GET['w']) ? (float)$_GET['w'] : -180;
$e = isset($_GET['e']) ? (float)$_GET['e'] : 180;
$cat_id = isset($_GET['cat_id']) ? (int)$_GET['cat_id'] : 0;
$order  = (isset($_GET['order']) && in_array($_GET['order'], array('asc', 'desc', 'rand'), true)) ? $_GET['order'] : 'asc';
$desc   = ($order === 'desc');

// Longitudes : si la zone dépasse -180/180 (carte répétée), on prend tout le globe en longitude
if (($e - $w) >= 360 || $w < -180 || $e > 180) { $w = -180; $e = 180; }

$cat_join = $cat_where = '';
if ($cat_id > 0) {
    $cat_join  = ' INNER JOIN ' . IMAGE_CATEGORY_TABLE . ' AS ic_f ON ic_f.image_id = i.id';
    $cat_where = ' AND ic_f.category_id = ' . $cat_id;
}
$pub_join = $pub_where = '';
if ($is_guest) {
    $pub_join  = ' INNER JOIN ' . IMAGE_CATEGORY_TABLE . ' AS ic_pub ON ic_pub.image_id = i.id'
               . ' INNER JOIN ' . CATEGORIES_TABLE    . ' AS c_pub  ON c_pub.id = ic_pub.category_id';
    $pub_where = " AND c_pub.status = 'public'";
}
$forbidden_where = '';
if (!$is_guest && !empty($user['forbidden_categories'])) {
    $forbidden_ids = implode(',', array_map('intval', explode(',', $user['forbidden_categories'])));
    $forbidden_where = ' AND i.id IN (SELECT image_id FROM ' . IMAGE_CATEGORY_TABLE
                     . ' WHERE category_id NOT IN (' . $forbidden_ids . '))';
}

$dir = $desc ? 'DESC' : 'ASC';
$query = 'SELECT DISTINCT i.*
    FROM ' . IMAGES_TABLE . ' AS i' . $pub_join . $cat_join . '
    WHERE i.latitude IS NOT NULL AND i.longitude IS NOT NULL AND i.latitude <> 0 AND i.longitude <> 0
      AND i.latitude BETWEEN ' . $s . ' AND ' . $n . '
      AND i.longitude BETWEEN ' . $w . ' AND ' . $e
    . $pub_where . $forbidden_where . $cat_where . '
    ORDER BY ' . ($order === 'rand' ? 'RAND()' : '(i.date_creation IS NULL), i.date_creation ' . $dir . ', i.id ' . $dir) . '
    LIMIT ' . ($MAX + 1);

$res = pwg_query($query);
$base_url = get_root_url();
$slides = array();
$truncated = false;
while ($row = pwg_db_fetch_assoc($res)) {
    if (count($slides) >= $MAX) { $truncated = true; break; }
    $img = '';
    try {
        $src   = new SrcImage($row);
        $deriv = new DerivativeImage(ImageStdParams::get_by_type(IMG_XLARGE), $src);
        $url   = $deriv->get_url();
        $img   = (strpos($url, 'http') === 0) ? $url : $base_url . ltrim($url, './');
    } catch (Exception $ex) {
        $img = '';
    }
    if ($img === '') continue;
    $title = $row['name'] ? $row['name'] : pathinfo($row['file'], PATHINFO_FILENAME);
    // Attention : ne pas nommer cette variable $page (globale de Piwigo, utilisée par get_root_url())
    $pic_url = make_picture_url(array('image_id' => (int)$row['id'], 'image_file' => $row['file']));
    if (strpos($pic_url, 'http') !== 0) $pic_url = $base_url . ltrim($pic_url, '/');
    $slides[] = array(
        'id'   => (int)$row['id'],
        'img'  => $img,
        'name' => $title,
        'date' => $row['date_creation'] ? substr($row['date_creation'], 0, 10) : '',
        'page' => $pic_url,
        'lat'  => round((float)$row['latitude'], 5),
        'lng'  => round((float)$row['longitude'], 5),
    );
}
$cfg = array('order' => $order, 'slides' => $slides, 'truncated' => $truncated, 'max' => $MAX, 'home' => make_index_url());
$title = (isset($conf['gallery_title']) ? $conf['gallery_title'] : 'Piwigo');
header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex, nofollow');
header('Cache-Control: no-store, max-age=0');
?><!doctype html>
<html lang="fr">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Diaporama — <?= htmlspecialchars($title) ?></title>
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.css">
<style>
  html, body { margin: 0; height: 100%; background: #000; color: #fff; font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; overflow: hidden; }
  #stage { position: absolute; inset: 0 0 64px 0; display: flex; align-items: center; justify-content: center; }
  #stage img { max-width: 100%; max-height: 100%; object-fit: contain; transition: opacity .35s; }
  #bar { position: absolute; left: 0; right: 0; bottom: 0; height: 64px; background: rgba(20,20,22,.96); display: flex; align-items: center; gap: 14px; padding: 0 16px; box-sizing: border-box; }
  #info { flex: 1; min-width: 0; }
  #info a { color: #fff; font-weight: 600; text-decoration: none; display: block; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
  #info a:hover { text-decoration: underline; }
  #info span { color: #aaa; font-size: 13px; }
  .ctl { display: flex; align-items: center; gap: 8px; }
  button, select { background: #2b2b30; color: #fff; border: 1px solid #44444a; border-radius: 6px; padding: 7px 12px; font-size: 14px; cursor: pointer; }
  button:hover { background: #3a3a41; }
  #minimap { position: absolute; right: 14px; bottom: 78px; width: 220px; height: 160px; border: 2px solid #fff; border-radius: 8px; overflow: hidden; box-shadow: 0 2px 10px rgba(0,0,0,.6); z-index: 5; }
  #msg { position: absolute; top: 14px; left: 50%; transform: translateX(-50%); background: rgba(0,0,0,.75); padding: 8px 14px; border-radius: 6px; font-size: 13px; z-index: 6; display: none; }
  #empty { position: absolute; inset: 0; display: none; align-items: center; justify-content: center; font-size: 18px; color: #bbb; text-align: center; padding: 20px; }
  @media (max-width: 640px) { #minimap { display: none; } #bar { flex-wrap: wrap; height: auto; min-height: 64px; padding: 8px 12px; } #stage { bottom: 96px; } }
</style>
</head>
<body>
<div id="stage"><img id="pic" alt=""></div>
<div id="minimap"></div>
<div id="msg"></div>
<div id="empty">Aucune photo dans cette zone.</div>
<div id="bar">
  <div id="info"><a id="ttl" href="#" target="_blank" rel="noopener"></a><span id="meta"></span></div>
  <div class="ctl">
    <button id="prev" title="Précédente (←)">&#9664;</button>
    <button id="play" title="Lecture / pause (espace)">&#10074;&#10074;</button>
    <button id="next" title="Suivante (→)">&#9654;</button>
    <select id="order" title="Ordre des photos">
      <option value="asc" selected>Date &#8593;</option><option value="desc">Date &#8595;</option><option value="rand">Aléatoire</option>
    </select>
    <select id="speed" title="Durée d'affichage">
      <option value="3">3 s</option><option value="5" selected>5 s</option><option value="8">8 s</option><option value="12">12 s</option>
    </select>
    <button id="fs" title="Plein écran (F)">&#9974;</button>
    <button id="close" title="Fermer">&#10005;</button>
  </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/leaflet@1.9.4/dist/leaflet.min.js"></script>
<script>
(function () {
  'use strict';
  var CFG = <?= json_encode($cfg, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
  var S = CFG.slides, idx = 0, playing = true, timer = null, delay = 5000;
  var pic = document.getElementById('pic'), ttl = document.getElementById('ttl'), meta = document.getElementById('meta');
  var playBtn = document.getElementById('play');

  if (!S.length) {
    document.getElementById('empty').style.display = 'flex';
    document.getElementById('bar').style.display = 'none';
    document.getElementById('minimap').style.display = 'none';
    return;
  }
  if (CFG.truncated) {
    var m = document.getElementById('msg');
    m.textContent = 'Zone très riche : seules les ' + CFG.max + ' premières photos sont lues. Zoomez pour affiner.';
    m.style.display = 'block';
    setTimeout(function () { m.style.display = 'none'; }, 6000);
  }

  // Mini-carte de localisation (facultative : le diaporama fonctionne sans)
  var mm = null, mk = null;
  try {
    if (window.L) {
      mm = L.map('minimap', { zoomControl: false, attributionControl: false, dragging: false, scrollWheelZoom: false, doubleClickZoom: false, boxZoom: false, keyboard: false, touchZoom: false });
      L.tileLayer('https://{s}.tile.openstreetmap.fr/osmfr/{z}/{x}/{y}.png', { maxZoom: 18 }).addTo(mm);
      mk = L.circleMarker([S[0].lat, S[0].lng], { radius: 7, color: '#fff', weight: 2, fillColor: '#e8412e', fillOpacity: 1 }).addTo(mm);
    } else { document.getElementById('minimap').style.display = 'none'; }
  } catch (e) { document.getElementById('minimap').style.display = 'none'; }

  // Ordre : date croissante (défaut), décroissante, ou aléatoire
  var ORIG = S.slice();
  function sortSlides(mode) {
    var cur = S[idx] ? S[idx].id : null;
    if (mode === 'rand') {
      S = ORIG.slice();
      for (var i = S.length - 1; i > 0; i--) { var j = Math.floor(Math.random() * (i + 1)); var t = S[i]; S[i] = S[j]; S[j] = t; }
    } else {
      S = ORIG.slice().sort(function (a, b) {
        var da = a.date || '9999', db = b.date || '9999';
        var c = da < db ? -1 : (da > db ? 1 : a.id - b.id);
        return mode === 'desc' ? -c : c;
      });
    }
    show(0);
  }
  var ordSel = document.getElementById('order');
  ordSel.value = CFG.order || 'asc';
  ordSel.addEventListener('change', function () { sortSlides(this.value); });

  function preload(i) { if (S[i]) { var im = new Image(); im.src = S[i].img; } }

  function show(i) {
    idx = (i + S.length) % S.length;
    var s = S[idx];
    pic.style.opacity = 0;
    var tmp = new Image();
    tmp.onload = tmp.onerror = function () { pic.src = s.img; pic.style.opacity = 1; };
    tmp.src = s.img;
    ttl.textContent = s.name; ttl.href = s.page;
    meta.textContent = (s.date ? s.date + ' · ' : '') + (idx + 1) + ' / ' + S.length;
    document.title = s.name + ' — Diaporama';
    if (mm && mk) { mk.setLatLng([s.lat, s.lng]); mm.setView([s.lat, s.lng], 9, { animate: false }); }
    preload(idx + 1); preload(idx + 2);
    schedule();
  }
  function schedule() {
    clearTimeout(timer);
    if (playing) timer = setTimeout(function () { show(idx + 1); }, delay);
  }
  function setPlaying(p) {
    playing = p;
    playBtn.innerHTML = p ? '&#10074;&#10074;' : '&#9654;&#65038;';
    schedule();
  }
  document.getElementById('prev').addEventListener('click', function () { show(idx - 1); });
  document.getElementById('next').addEventListener('click', function () { show(idx + 1); });
  playBtn.addEventListener('click', function () { setPlaying(!playing); });
  document.getElementById('speed').addEventListener('change', function () { delay = (parseInt(this.value, 10) || 5) * 1000; schedule(); });
  function toggleFs() {
    if (document.fullscreenElement) document.exitFullscreen();
    else if (document.documentElement.requestFullscreen) document.documentElement.requestFullscreen();
  }
  document.getElementById('fs').addEventListener('click', toggleFs);
  document.getElementById('close').addEventListener('click', function () {
    window.close();
    setTimeout(function () { window.location.href = CFG.home; }, 200);
  });
  document.addEventListener('keydown', function (e) {
    if (e.key === 'ArrowRight') show(idx + 1);
    else if (e.key === 'ArrowLeft') show(idx - 1);
    else if (e.key === ' ') { e.preventDefault(); setPlaying(!playing); }
    else if (e.key === 'f' || e.key === 'F') toggleFs();
  });
  document.getElementById('stage').addEventListener('click', function () { show(idx + 1); });

  setPlaying(true);
  sortSlides(CFG.order || 'asc');
})();
</script>
</body>
</html>
