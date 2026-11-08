<?php
/*
 * This file is part of OSM Map Plus (GPL-2.0-or-later).
 * Audit : écarts entre la position en base et le GPS EXIF des fichiers (JPEG/TIFF), lecture seule
 * sauf action explicite « Appliquer la position du fichier » (EXIF -> base).
 */
if (!defined('PHPWG_ROOT_PATH')) die('Hacking attempt!');
check_status(ACCESS_ADMINISTRATOR);
global $template;

$root  = get_root_url();
$ep    = $root . 'plugins/osm_map/osmme-exifcheck.php';
$edit  = $root . 'admin.php?page=plugin&section=osm_map/admin/photo_edit.php&image_id=';
$token = get_pwg_token();
$exif_ext = function_exists('exif_read_data');

$html = '
<style>
#osm-audit { padding:20px; max-width:1100px; }
#osm-audit h3 { margin:0 0 12px; }
#osm-audit .bar { display:flex; gap:14px; align-items:center; flex-wrap:wrap; margin-bottom:12px; }
#osm-audit select, #osm-audit button { padding:6px 10px; border:1px solid #ccc; border-radius:4px; background:#fff; cursor:pointer; }
#osm-audit button.primary { background:#1a73e8; color:#fff; border-color:#1a73e8; font-weight:600; }
#osm-audit button:disabled { opacity:.5; cursor:default; }
#osm-prog { height:10px; background:#e0e0e0; border-radius:5px; overflow:hidden; margin:6px 0; }
#osm-prog > div { height:100%; width:0; background:#1a73e8; transition:width .2s; }
#osm-audit table { width:100%; border-collapse:collapse; margin-top:10px; }
#osm-audit th, #osm-audit td { padding:6px 8px; border-bottom:1px solid #e5e5e5; text-align:left; font-size:0.88rem; }
#osm-audit code { font-size:0.85em; }
.osm-pager { display:flex; gap:6px; flex-wrap:wrap; align-items:center; margin:8px 0; }
.osm-pager a, .osm-pager strong { padding:4px 10px; border:1px solid #d0d0d0; border-radius:4px; text-decoration:none; background:#fff; cursor:pointer; }
.osm-pager strong { background:#1a73e8; color:#fff; border-color:#1a73e8; }
#osm-audit td img { width:64px; height:64px; object-fit:cover; border-radius:3px; display:block; border:1px solid #d0d0d0; }
.osm-warn { background:#fff4e5; border:1px solid #f5c27a; border-radius:4px; padding:8px 12px; margin-bottom:12px; }
</style>
<div id="osm-audit">
  <p><a href="' . $root . 'admin.php?page=plugin-osm_map">&larr; Retour aux paramètres d\'OSM Map Plus</a></p>
  <h3>&#127760; Écarts entre la base et le GPS EXIF des fichiers</h3>';
if (!$exif_ext) {
    $html .= '<div class="osm-warn">L\'extension PHP <code>exif</code> n\'est pas active sur ce serveur : l\'analyse est impossible.</div>';
}
$html .= '
  <p>Analyse les photos JPEG/TIFF de la galerie (lecture seule) et liste celles dont la position en base diffère du GPS écrit dans le fichier. Aucun fichier n\'est modifié ; seule l\'action « Appliquer » modifie la <strong>base</strong> (en copiant la position du fichier).</p>
  <div class="bar">
    <label>Écart minimal :
      <select id="osm-thr">
        <option value="15" selected>15 m</option><option value="50">50 m</option><option value="200">200 m</option>
        <option value="1000">1 km</option><option value="10000">10 km</option>
      </select></label>
    <label><input type="checkbox" id="osm-showvalid"> Afficher aussi les écarts déjà validés</label>
    <label><input type="checkbox" id="osm-dbempty" checked> Inclure « base vide mais GPS dans le fichier »</label>
    <button type="button" class="primary" id="osm-go"' . ($exif_ext ? '' : ' disabled') . '>&#9654; Lancer l\'analyse</button>
    <button type="button" id="osm-stop" disabled>&#9209; Arrêter</button>
  </div>
  <div id="osm-prog"><div></div></div>
  <div id="osm-status" style="font-size:0.88rem;color:#555;"></div>
  <div class="bar" id="osm-bulk" style="display:none;margin-top:10px;">
    <label>Tri : <select id="osm-sort">
      <option value="id">Ordre d\'analyse (ajout)</option>
      <option value="date_asc">Date de prise de vue (anciennes d\'abord)</option>
      <option value="date_desc">Date de prise de vue (récentes d\'abord)</option>
      <option value="dist_desc">Écart (le plus grand d\'abord)</option>
      <option value="name">Nom</option>
    </select></label>
    <label>Par page : <select id="osm-pp"><option>24</option><option selected>48</option><option>96</option><option>200</option></select></label>
    <button type="button" id="osm-all">Tout cocher / décocher (toutes pages)</button>
    <button type="button" class="primary" id="osm-apply">Appliquer la position du fichier aux lignes cochées</button>
    <button type="button" id="osm-validate">Valider l\'écart des lignes cochées (garder la base)</button>
    <button type="button" id="osm-unvalidate">Retirer la validation des lignes cochées</button>
    <span id="osm-bulkmsg" style="font-size:0.88rem;"></span>
  </div>
  <div class="osm-pager" id="osm-pg1"></div>
  <div class="bar" style="font-size:0.88rem;">
    <span>Écarts validés (sauvegarde automatique dans <code>_data/osm_map_validated.json</code>) :</span>
    <a href="' . $root . 'plugins/osm_map/osmme-exifcheck.php?export=1">&#11015; Exporter</a>
    <label>&#11014; Importer : <input type="file" id="osm-imp" accept=".json,application/json"></label>
    <span id="osm-impmsg"></span>
  </div>
  <table id="osm-tbl" style="display:none;"><thead><tr>
    <th></th><th></th><th>Photo</th><th>Date</th><th>Base de données</th><th>Fichier (EXIF)</th><th>Écart</th><th></th>
  </tr></thead><tbody></tbody></table>
  <div class="osm-pager" id="osm-pg2"></div>
</div>
<script>
(function() {
  var EP = ' . json_encode($ep) . ', EDIT = ' . json_encode($edit) . ', TOKEN = ' . json_encode($token) . ';
  var running = false, all = [], byId = {}, page = 1;
  var $ = function(i) { return document.getElementById(i); };
  function fmt(e) { return e ? e.lat.toFixed(6) + ", " + e.lon.toFixed(6) : "—"; }
  function dist(m) { return m === null ? "—" : (m >= 1000 ? (m / 1000).toFixed(1).replace(".", ",") + " km" : m + " m"); }
  function esc(s) { var d = document.createElement("div"); d.textContent = s; return d.innerHTML; }
  function perPage() { return parseInt($("osm-pp").value, 10) || 48; }

  function renderRow(r) {
    var tr = document.createElement("tr");
    tr.id = "osm-r-" + r.id;
    if (r.applied) tr.style.background = "#e6f4ea";
    tr.innerHTML = "<td><input type=\"checkbox\" class=\"osm-ck\" value=\"" + r.id + "\"" + (r.checked ? " checked" : "") + (r.applied ? " disabled" : "") + "></td>"
      + "<td>" + (r.thumb ? "<a href=\"" + EDIT + r.id + "\" target=\"_blank\" rel=\"noopener\"><img src=\"" + esc(r.thumb) + "\" alt=\"\" loading=\"lazy\"></a>" : "") + "</td>"
      + "<td><a href=\"" + EDIT + r.id + "\" target=\"_blank\" rel=\"noopener\">" + esc(r.name) + "</a> <small>#" + r.id + "</small></td>"
 + "<td>" + (r.date ? esc(r.date) : "\u2014") + "</td>"
      + "<td>" + (r.db ? "<code>" + fmt(r.db) + "</code>" : "<em>vide</em>") + "</td>"
      + "<td><code>" + fmt(r.exif) + "</code></td><td>" + dist(r.dist) + "</td>"
      + "<td>" + (r.applied ? "✓ appliqué" : (r.validated ? "✓ validé " : "") + "<button type=\"button\" class=\"osm-b-apply\">Appliquer</button>" + (r.validated ? "" : " <button type=\"button\" class=\"osm-b-valid\" title=\"Garder la position de la base et ne plus signaler cet écart\">Valider</button>")) + "</td>";
    var ck = tr.querySelector(".osm-ck");
    ck.addEventListener("change", function() { r.checked = ck.checked; });
    var b = tr.querySelector(".osm-b-apply");
    if (b) b.addEventListener("click", function() { apply([r.id]); });
    var bv = tr.querySelector(".osm-b-valid");
    if (bv) bv.addEventListener("click", function() { setValid([r.id], true); });
    return tr;
  }

  function pager() {
    var pp = perPage(), pages = Math.max(1, Math.ceil(all.length / pp)), set = {}, h = "";
    if (page > pages) page = pages;
    [1, pages, page - 2, page - 1, page, page + 1, page + 2].forEach(function(n) { if (n >= 1 && n <= pages) set[n] = 1; });
    var nums = Object.keys(set).map(Number).sort(function(a, b) { return a - b; }), prev = 0;
    if (pages > 1) {
      if (page > 1) h += "<a data-p=\"" + (page - 1) + "\">‹ Précédente</a>";
      nums.forEach(function(n) {
        if (prev && n > prev + 1) h += "<span>…</span>";
        h += n === page ? "<strong>" + n + "</strong>" : "<a data-p=\"" + n + "\">" + n + "</a>";
        prev = n;
      });
      if (page < pages) h += "<a data-p=\"" + (page + 1) + "\">Suivante ›</a>";
    }
    ["osm-pg1", "osm-pg2"].forEach(function(id) { $(id).innerHTML = h; });
  }

  function sorted() {
    var m = $("osm-sort").value, a = all.slice();
    var byDate = function(x, y, dir) {
      if (!x.date && !y.date) return x.id - y.id;
      if (!x.date) return 1; if (!y.date) return -1;   // sans date en dernier
      return x.date === y.date ? x.id - y.id : (x.date < y.date ? -dir : dir);
    };
    if (m === "date_asc") a.sort(function(x, y) { return byDate(x, y, 1); });
    else if (m === "date_desc") a.sort(function(x, y) { return byDate(x, y, -1); });
    else if (m === "dist_desc") a.sort(function(x, y) { return (y.dist === null ? 1e12 : y.dist) - (x.dist === null ? 1e12 : x.dist) || x.id - y.id; });
    else if (m === "name") a.sort(function(x, y) { return x.name.localeCompare(y.name) || x.id - y.id; });
    return a;
  }

  function render() {
    var pp = perPage(), tb = $("osm-tbl").querySelector("tbody");
    tb.innerHTML = "";
    sorted().slice((page - 1) * pp, page * pp).forEach(function(r) { tb.appendChild(renderRow(r)); });
    $("osm-tbl").style.display = all.length ? "" : "none";
    $("osm-bulk").style.display = all.length ? "" : "none";
    pager();
  }
  ["osm-pg1", "osm-pg2"].forEach(function(id) {
    $(id).addEventListener("click", function(e) {
      var p = e.target.getAttribute && e.target.getAttribute("data-p");
      if (p) { page = parseInt(p, 10); render(); window.scrollTo({top: $("osm-audit").offsetTop, behavior: "smooth"}); }
    });
  });
  $("osm-pp").addEventListener("change", function() { page = 1; render(); });
  $("osm-sort").addEventListener("change", function() { page = 1; render(); });

  function apply(ids, skip) {
    if (!ids.length) return;
    if (!skip && !confirm("Remplacer la position en base par celle du fichier pour " + ids.length + " photo(s) ?")) return;
    var fd = new FormData();
    fd.append("apply", "1"); fd.append("pwg_token", TOKEN); fd.append("ids", ids.join(","));
    fetch(EP, {method: "POST", body: fd, credentials: "same-origin"}).then(function(r) { return r.json(); }).then(function(d) {
      (d.applied || []).forEach(function(id) { var r = byId[id]; if (r) { r.applied = true; r.checked = false; } });
      $("osm-bulkmsg").textContent = (d.applied || []).length + " photo(s) mise(s) à jour.";
      render();
    }).catch(function() { $("osm-bulkmsg").textContent = "Erreur réseau."; });
  }

  function setValid(ids, on) {
    if (!ids.length) return;
    var fd = new FormData();
    fd.append(on ? "validate" : "unvalidate", "1"); fd.append("pwg_token", TOKEN); fd.append("ids", ids.join(","));
    fetch(EP, {method: "POST", body: fd, credentials: "same-origin"}).then(function(r) { return r.json(); }).then(function(d) {
      var show = $("osm-showvalid").checked;
      (d.done || []).forEach(function(id) {
        var r = byId[id]; if (!r) return;
        r.checked = false; r.validated = on;
        if (on && !show) { delete byId[id]; all = all.filter(function(x) { return x.id !== id; }); }
      });
      $("osm-bulkmsg").textContent = (d.done || []).length + " écart(s) " + (on ? "validé(s)." : "remis à signaler.");
      render();
    }).catch(function() { $("osm-bulkmsg").textContent = "Erreur réseau."; });
  }
  $("osm-validate").addEventListener("click", function() {
    setValid(all.filter(function(r) { return r.checked && !r.applied && !r.validated; }).map(function(r) { return r.id; }), true);
  });
  $("osm-unvalidate").addEventListener("click", function() {
    setValid(all.filter(function(r) { return r.checked && r.validated; }).map(function(r) { return r.id; }), false);
  });
  $("osm-imp").addEventListener("change", function() {
    var f = this.files[0]; if (!f) return;
    var fd = new FormData(); fd.append("import", "1"); fd.append("pwg_token", TOKEN); fd.append("file", f);
    fetch(EP, {method: "POST", body: fd, credentials: "same-origin"}).then(function(r) { return r.json(); })
      .then(function(d) { $("osm-impmsg").textContent = (d.imported || 0) + " ligne(s) importée(s) — relancez l\'analyse."; })
      .catch(function() { $("osm-impmsg").textContent = "Erreur d\'import."; });
    this.value = "";
  });

  function step(after, scanned, found, total) {
    if (!running) { finish("Analyse arrêtée."); return; }
    var url = EP + "?scan=1&after=" + after + "&limit=100&threshold=" + $("osm-thr").value + "&dbempty=" + ($("osm-dbempty").checked ? 1 : 0) + "&showvalid=" + ($("osm-showvalid").checked ? 1 : 0);
    fetch(url, {credentials: "same-origin"}).then(function(r) { return r.json(); }).then(function(d) {
      if (d.error) { finish("Accès refusé."); return; }
      if (d.total !== null && d.total !== undefined) total = d.total;
      scanned += d.scanned; found += d.rows.length;
      d.rows.forEach(function(r) { all.push(r); byId[r.id] = r; });
      if (d.rows.length) render();
      if (total) { $("osm-prog").firstChild.style.width = Math.min(100, scanned * 100 / total) + "%"; }
      $("osm-status").textContent = scanned + " / " + (total || "?") + " photos analysées — " + found + " écart(s)";
      if (d.done) { finish("Analyse terminée : " + scanned + " photos, " + found + " écart(s)."); }
      else { step(d.last, scanned, found, total); }
    }).catch(function() { finish("Erreur réseau — analyse interrompue."); });
  }
  function finish(msg) { running = false; $("osm-go").disabled = false; $("osm-stop").disabled = true; $("osm-status").textContent = msg; if (msg.indexOf("termin") > -1) $("osm-prog").firstChild.style.width = "100%"; }

  $("osm-go").addEventListener("click", function() {
    running = true; $("osm-go").disabled = true; $("osm-stop").disabled = false;
    all = []; byId = {}; page = 1; render();
    $("osm-bulkmsg").textContent = ""; $("osm-prog").firstChild.style.width = "0";
    step(0, 0, 0, 0);
  });
  $("osm-stop").addEventListener("click", function() { running = false; });
  $("osm-all").addEventListener("click", function() {
    var cand = all.filter(function(r) { return !r.applied; });
    var allOn = cand.length && cand.every(function(r) { return r.checked; });
    cand.forEach(function(r) { r.checked = !allOn; });
    render();
  });
  $("osm-apply").addEventListener("click", function() {
    var ids = all.filter(function(r) { return r.checked && !r.applied; }).map(function(r) { return r.id; });
    if (!ids.length) { $("osm-bulkmsg").textContent = "Aucune ligne cochée."; return; }
    if (!confirm("Remplacer la position en base par celle du fichier pour " + ids.length + " photo(s) ?")) return;
    var i = 0; (function next() { if (i >= ids.length) return; var chunk = ids.slice(i, i + 100); i += 100; apply(chunk, true); setTimeout(next, 300); })();
  });
})();
</script>';

$template->assign('OSM_PHOTO_EDIT_HTML', $html);
$template->set_filenames(array('plugin_admin_content' => dirname(__FILE__).'/../template/photo_edit_wrap.tpl'));
$template->assign_var_from_handle('ADMIN_CONTENT', 'plugin_admin_content');
