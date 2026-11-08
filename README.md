# OSM Map Plus — Piwigo plugin

*[Version française](README.fr.md)*

Shows an interactive map of all your geotagged (GPS) photos on your Piwigo gallery, with several basemaps, a 3D terrain view, and geotagging tools in the administration.

License: [GNU GPL-2.0](LICENSE) — works with all themes.

## Features

**Gallery map**
- Automatic marker clustering, suited to large galleries (tens of thousands of photos)
- **"Basemap"** menu: Carto Voyager, OpenStreetMap, **OpenStreetMap France**, **OpenFreeMap**, Satellite (Esri), **Satellite + labels**, Topographic (the visitor's choice is remembered in their browser)
- **"3D"** button: opens a terrain view in a new tab (tilt, adjustable relief exaggeration, optional **globe** mode) with grouped photos, the chosen basemap and the current album filter. The 3D button is also available on the mini-map of photo pages, on the map of the photo edit tab and on the batch manager maps (the photo concerned is highlighted)
- Side margins around the map, and mouse-wheel zoom enabled only after a click in the map, so you can scroll the page without moving the map
- **"Thumbnails"** button: photo groups (and single photos) are displayed as thumbnails with their photo count, like Apple Photos (choice remembered in the browser)
- **"▶ Slideshow"** button in the list panel: opens a full-screen slideshow in a new tab of the photos in the visible area (and the filtered album), oldest first by default (order selectable: oldest first, newest first, random), with autoplay, keyboard arrows and a location mini-map (500 photos maximum)
- Side list panel synchronized with the visible area (follows zoom and panning; links open in a new tab)
- Album filter and place search (geocoder)
- Admin-only **"Favorite places"** menu next to the album choice: recenters the map on a saved place
- Creation of a Piwigo album from the photos visible on the map (administrators only; other users only see the lists)
- "OSM Map Plus" page: full-screen map of all photos (logged-in users)
- "Location" mini-map on photo pages (basemap menu, configurable basemap and height)
- Access control: map reserved to logged-in users, or open to the public (public albums only)

**Geotagging (administration)**
- Batch manager, single mode: map, place search, Copy / Paste coordinates buttons, favorite places menu
- "OSM Map Plus" tab on the photo edit page: map, search, favorite places, saving or clearing the GPS position
- Same basemaps as the gallery ("Basemap" menu on every map)
- GPS / EXIF tools: see below

## Requirements

- Piwigo 13 or later, PHP 7.4 or later
- A browser with **WebGL** for the OpenFreeMap and Satellite + labels basemaps and the 3D view (other basemaps work without it)
- Internet access from visitors' browsers to the map services listed below
- The PHP `exif` extension for the GPS / EXIF comparison tools (optional)
- A free **CartoDB** API key only if you use the Carto Voyager basemap (see Configuration)

## Installation

1. Install from Administration → Plugins → Add a new plugin (search "OSM Map Plus"), or unzip the archive into `plugins/osm_map/`
2. Activate the plugin in Administration → Plugins
3. Configure it from Administration → Plugins → OSM Map Plus ("Settings" button)

To update: replace the `plugins/osm_map/` folder (or use Piwigo's update), then clear the browser cache (Ctrl/Cmd + Shift + R).

## Configuration

| Option | Default | Description |
|---|---|---|
| Public display | No | Map visible to non-logged-in visitors (photos of public albums only) |
| Map height | 500 px | Between 200 and 1200 px |
| World map height | 600 px | Height of the "OSM Map Plus" page (300 to 2000 px) |
| Max number of photos | 5000 | Indicative limit for the gallery map (0 = unlimited) |
| Initial zoom | 5 | 1 = whole world, 18 = street level; the map then recenters on the photos |
| Hide if native OSM is active | No | Hides our map if the `piwigo-openstreetmap` plugin is active, to avoid two maps |
| CartoDB API key | empty | Required for the Carto Voyager basemap (see below) |
| Basemap — Gallery | Carto Voyager | Basemap shown when the gallery map opens |
| Basemap — Photo page | Carto Voyager | Basemap of the "Location" mini-map on photo pages |
| Map height — Photo page | 220 px | Height of the mini-map (150 to 800 px) |
| Basemap — Batch manager | Carto Voyager | Basemap shown when the single-mode maps open |
| Map height — Batch manager | 280 px | Height of each single-mode map (150 to 800 px) |
| Basemap — Photo edit | Carto Voyager | Basemap of the "OSM Map Plus" tab of the photo edit page |
| Map height — Photo edit | 400 px | Height of the edit tab map (200 to 1000 px) |

All basemap lists are identical. Visitors and administrators can change basemap with the "Basemap" menu; their choice remains valid as long as the default basemap is not changed in the settings.

### CartoDB API key

Since 2026-08-26, Carto Voyager tiles require a free key: <https://carto.com/basemaps/apikey/>. Paste it in the plugin settings. Other basemaps do not need one. If the **Geo Album** plugin is installed, the key entered in either plugin is used by the other.

### Notes

- In the batch manager, vector basemaps (OpenFreeMap, Satellite + labels) are only offered when the page shows 8 photos or fewer: each such map uses a WebGL context and browsers limit their number.
- If the selected basemap does not respond (for example, missing Carto key), the geotagging maps and the photo-page mini-map automatically fall back to OpenStreetMap.
- Compatibility: the plugin reuses the Leaflet already present on the page (for example the one from `piwigo-openstreetmap`) instead of loading a second one.

### How GPS coordinates are saved

OSM Map Plus saves coordinates **only in the Piwigo database** (`latitude` and `longitude` columns). It does not write to file metadata (EXIF, XMP, IPTC).

- Photo edit tab: saved directly in the database by OSM Map Plus.
- Batch manager: OSM Map Plus shows the map and fills the `osmlat-ID` and `osmlon-ID` fields; saving is handled by the `piwigo-openstreetmap` handler (same field names). Keep that plugin active for batch saving.
- To write coordinates into JPEG files, use a dedicated plugin such as `geo_tag_editor` (one photo at a time).
- Note: a metadata synchronization from the files may overwrite coordinates saved only in the database. The tools below help to spot such differences.

## GPS / EXIF tools (administration)

Buttons at the top of the plugin settings page (under the statistics). Reading the GPS of the files (JPEG/TIFF) is read-only and requires the PHP `exif` extension.

- **Database / file comparison**: on the photo edit tab, a banner compares the database position with the file's EXIF GPS (distance in m or km), with a second "file" map and a "Use the file position" button. In the batch manager, an orange banner flags the photos concerned.
- **GPS / EXIF differences** (gallery audit): scans all JPEG/TIFF files in batches (distance threshold of your choice) and lists the differences with thumbnails, pagination (24 to 200 rows) and sorting (scan order, shooting date, distance, name). You can apply the file position to the database, row by row or in bulk, or validate the difference.
- **Validated differences**: "Validate" keeps the database position and stops flagging it, as long as neither the database nor the file changes. Table `osm_map_exif_ok`, automatic backup in `_data/osm_map_validated.json` (kept on uninstall, reloaded on reinstall), JSON export/import.
- **Photos without GPS**: paginated list (24 to 200 per page) of photos without a database position, with album filter and date sorting; clicking a thumbnail opens the position editor in a new tab. A marker flags photos whose file already contains a GPS position.
- **Favorite places**: flat list (add, rename, fix, delete), a map with address search to create a place, and a general map of all places. "Favorite places" menu in the photo edit tab (with "Save this place"), in the batch manager and, for administrators, next to the album choice on the general map. Table `osm_map_places`, automatic backup in `_data/osm_map_places.json` (reloaded on reinstall), JSON export/import, and import of the places of `piwigo-openstreetmap` if installed (duplicates ignored).

## External services used

Maps load their resources from the visitor's browser:

- [jsDelivr](https://www.jsdelivr.com/) and [unpkg](https://unpkg.com/) — Leaflet libraries (and icons), Leaflet.markercluster, MapLibre GL
- [OpenStreetMap](https://www.openstreetmap.org/), [OpenStreetMap France](https://www.openstreetmap.fr/), [OpenTopoMap](https://opentopomap.org/) — tiles
- [CARTO](https://carto.com/) — Voyager tiles (API key)
- [OpenFreeMap](https://openfreemap.org/) — vector basemap and place names
- [Esri World Imagery](https://www.arcgis.com/) — satellite imagery
- [AWS Terrain Tiles](https://registry.opendata.aws/terrain-tiles/) — 3D view relief
- [Nominatim](https://nominatim.org/) — geocoding (place search)

Please respect each service's terms of use, especially for high-traffic galleries.

## Credits

**Design, specifications and testing**
Bobcat-Fr

**AI-assisted development**
Code generated by [Claude](https://claude.ai) (Anthropic) in an iterative development session — specifications, fixes and validation by Bobcat-Fr.

**Libraries**
- [Leaflet](https://leafletjs.com/) 1.9.4 — interactive map (BSD 2-Clause)
- [Leaflet.markercluster](https://github.com/Leaflet/Leaflet.markercluster) 1.5.3 — clustering (MIT)
- [MapLibre GL JS](https://maplibre.org/) 4.7.1 and [maplibre-gl-leaflet](https://github.com/maplibre/maplibre-gl-leaflet) 0.0.22 — vector basemaps and 3D view (BSD 3-Clause / ISC)
- Map data © [OpenStreetMap](https://www.openstreetmap.org/copyright) contributors (ODbL)

## License

GNU GPL-2.0 or later — see the [LICENSE](LICENSE) file.

## Changelog

- **1.0.8** — Photo edit: comparison of the database position with the file's EXIF GPS (JPEG, read-only): warning banner with the distance, second "file" map, "Use the file position" button. Difference alert in the batch manager, "GPS / EXIF differences" audit page and "Photos without GPS" page (paginated) covering the whole gallery, validation of differences, and favorite places (own tables + backup in `_data`). New "Slideshow" button (full-screen slideshow of the photos in the visible area, in a new tab). "Thumbnails" button: photo groups and single photos displayed as thumbnails with their count (like Apple Photos). The list panel now follows zoom and panning, with wider thumbnails (96×72); photo links open in a new tab. 3D view: photos taken at the same spot stay grouped with their count, and a click lists all photos of the group. Fixes: the "OSM Map Plus" page loaded an old cached stylesheet; "countEl is not defined" error when zooming on an area without photos.
- **1.0.7** — "Basemap" menu on the gallery map and on the geotagging maps: added OpenStreetMap France, OpenFreeMap and Satellite + labels. "3D" button (terrain view in a new tab). The 3D view has a "Globe" checkbox: the Earth as a zoomable sphere with atmosphere (MapLibre GL JS 5). In the administration, if another plugin forces an old Leaflet (< 1.0), OpenFreeMap is not offered and "Satellite + labels" falls back to the Esri raster version. The settings offer the same basemap list for the gallery and geotagging. Compatibility with `piwigo-openstreetmap` (a single Leaflet instance on the page). Compliance with Piwigo recommendations: `index.php` files, `maintain.class.php` (install, update, clean uninstall of settings), language files loaded, `en_UK` folder. More robust geotagging maps and photo-page mini-map (size recalculation, automatic fallback to OpenStreetMap if the basemap does not respond). Fixed a gallery page freeze when the default basemap was OpenFreeMap or Satellite + labels. Side margins and wheel zoom after click on the maps. Album creation restricted to administrators.
- **1.0.6** — CartoDB API key shared with the Geo Album plugin
- **1.0.5** — "Settings" button in the plugin list
- **1.0.4** — Adaptation to the CARTO API key, minor bug fix
- **1.0.3** — Adaptation to CARTO: API key required for Voyager tiles
- **1.0.2** — Album list fix, multi-criteria search for places and albums, header fixes for publication
- **1.0.1** — Version numbering change for publication
