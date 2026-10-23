# OSM Map Plus — Plugin Piwigo

Affiche une carte interactive sur votre galerie Piwigo avec toutes les photos géolocalisées (GPS), propose plusieurs fonds de carte, une vue 3D en relief, et permet de géotagger les photos depuis l'administration.

Licence : [GNU GPL-2.0](LICENSE) — Compatible tous thèmes.

## Fonctionnalités

**Carte de la galerie**
- Clustering automatique des marqueurs, adapté aux grandes galeries (plusieurs dizaines de milliers de photos)
- Menu **« Fond »** : Carto Voyager, OpenStreetMap, **OpenStreetMap France**, **OpenFreeMap**, Satellite (Esri), **Satellite + noms**, Topographique (le choix du visiteur est mémorisé dans son navigateur)
- Bouton **« 3D »** : ouvre dans un nouvel onglet une vue en relief (inclinaison, exagération du relief réglable), avec les photos regroupées, le fond au choix et le filtre d'album en cours. Le bouton « 3D » est aussi présent sur la mini-carte des pages photo, sur la carte de l'onglet d'édition photo et sur les cartes de la gestion par lot (la photo concernée est mise en évidence)
- Marges latérales autour de la carte et zoom à la molette activé seulement après un clic dans la carte : on peut faire défiler la page sans déplacer la carte
- Panneau liste latéral synchronisé avec la zone visible
- Filtrage par album, géocodeur (recherche de lieu)
- Création d'un album Piwigo depuis les photos visibles sur la carte (réservé aux administrateurs ; les autres utilisateurs ne voient que les listes)
- Page « OSM Map Plus » : carte plein écran de toutes les photos (utilisateurs connectés)
- Mini-carte « Localisation » sur les pages photo (menu « Fond », fond et hauteur réglables)
- Contrôle d'accès : carte réservée aux connectés ou ouverte au public (albums publics uniquement)

**Géotaggage (administration)**
- Mode unitaire de la gestion par lot : carte, recherche de lieu, boutons Copier / Coller les coordonnées
- Onglet « OSM Map Plus » sur la fiche d'édition d'une photo : carte, recherche, enregistrement ou effacement du GPS
- Mêmes fonds de carte que la galerie (menu « Fond » sur chaque carte)

## Prérequis

- Piwigo 13 ou supérieur, PHP 7.4 ou supérieur
- Un navigateur avec **WebGL** pour les fonds OpenFreeMap, Satellite + noms et la vue 3D (les autres fonds fonctionnent sans)
- Un accès Internet depuis le navigateur des visiteurs vers les services de cartes listés plus bas
- Une clé API **CartoDB** gratuite uniquement si vous utilisez le fond Carto Voyager (voir Configuration)

## Installation

1. Installer depuis Administration → Plugins → Ajouter un nouveau plugin (recherche « OSM Map Plus »), ou décompresser l'archive dans `plugins/osm_map/`
2. Activer le plugin depuis Administration → Plugins
3. Configurer depuis Administration → Plugins → OSM Map Plus (bouton « Paramètres »)

Pour mettre à jour : remplacer le dossier `plugins/osm_map/` (ou utiliser la mise à jour de Piwigo), puis vider le cache du navigateur (Ctrl/Cmd + Maj + R).

## Configuration

| Option | Défaut | Description |
|---|---|---|
| Affichage public | Non | Carte visible par les visiteurs non connectés (photos des albums publics uniquement) |
| Hauteur de la carte | 500 px | Entre 200 et 1200 px |
| Hauteur carte mondiale | 600 px | Hauteur de la page « OSM Map Plus » (300 à 2000 px) |
| Nombre max de photos | 5000 | Limite indicative pour la carte galerie (0 = illimité) |
| Zoom initial | 5 | 1 = monde entier, 18 = détail rue ; la carte se recentre ensuite sur les photos |
| Masquer si OSM natif actif | Non | Masque notre carte si le plugin `piwigo-openstreetmap` est actif, pour éviter deux cartes |
| Clé API CartoDB | vide | Nécessaire pour le fond Carto Voyager (voir ci-dessous) |
| Fond de carte — Galerie | Carto Voyager | Fond affiché à l'ouverture de la carte de la galerie |
| Fond de carte — Page d'une photo | Carto Voyager | Fond de la mini-carte « Localisation » des pages photo |
| Hauteur de la carte — Page d'une photo | 220 px | Hauteur de la mini-carte (150 à 800 px) |
| Fond de carte — Gestion par lot | Carto Voyager | Fond affiché à l'ouverture des cartes du mode unitaire |
| Hauteur de la carte — Gestion par lot | 280 px | Hauteur de chaque carte du mode unitaire (150 à 800 px) |
| Fond de carte — Édition d'une photo | Carto Voyager | Fond de l'onglet « OSM Map Plus » de la page d'édition d'une photo |
| Hauteur de la carte — Édition d'une photo | 400 px | Hauteur de la carte de l'onglet d'édition (200 à 1000 px) |

Toutes les listes de fonds sont identiques. Les visiteurs et administrateurs peuvent changer de fond avec le menu « Fond » ; leur choix reste valable tant que le fond par défaut n'est pas modifié dans les paramètres.

### Clé API CartoDB

Depuis le 26/08/2026, les tuiles Carto Voyager nécessitent une clé gratuite : <https://carto.com/basemaps/apikey/>. Collez-la dans les paramètres du plugin. Les autres fonds n'en ont pas besoin. Si le plugin **Geo Album** est installé, la clé renseignée dans l'un des deux plugins est utilisée par l'autre.

### Remarques

- Dans la gestion par lot, les fonds vectoriels (OpenFreeMap, Satellite + noms) ne sont proposés que si la page affiche 8 photos ou moins : chaque carte de ce type consomme un contexte WebGL et les navigateurs en limitent le nombre.
- Si le fond choisi ne répond pas (par exemple clé Carto absente), les cartes de géotaggage et la mini-carte des pages photo basculent automatiquement sur OpenStreetMap.
- Compatibilité : le plugin réutilise le Leaflet déjà présent sur la page (par exemple celui de `piwigo-openstreetmap`) au lieu d'en charger un second.

## Services externes utilisés

Les cartes chargent leurs ressources depuis le navigateur du visiteur :

- [jsDelivr](https://www.jsdelivr.com/) et [unpkg](https://unpkg.com/) — bibliothèques Leaflet (et ses icônes), Leaflet.markercluster, MapLibre GL
- [OpenStreetMap](https://www.openstreetmap.org/), [OpenStreetMap France](https://www.openstreetmap.fr/), [OpenTopoMap](https://opentopomap.org/) — tuiles
- [CARTO](https://carto.com/) — tuiles Voyager (clé API)
- [OpenFreeMap](https://openfreemap.org/) — fond vectoriel et noms de lieux
- [Esri World Imagery](https://www.arcgis.com/) — imagerie satellite
- [AWS Terrain Tiles](https://registry.opendata.aws/terrain-tiles/) — relief de la vue 3D
- [Nominatim](https://nominatim.org/) — géocodage (recherche de lieu)

Respectez les conditions d'utilisation de chaque service, notamment pour les galeries à fort trafic.

## Crédits

**Conception, cahier des charges et tests**
Bobcat-Fr

**Développement assisté par IA**
Code généré par [Claude](https://claude.ai) (Anthropic) via une session de développement itératif — spécifications, corrections et validation assurées par Bobcat-Fr.

**Bibliothèques utilisées**
- [Leaflet](https://leafletjs.com/) 1.9.4 — carte interactive (BSD 2-Clause)
- [Leaflet.markercluster](https://github.com/Leaflet/Leaflet.markercluster) 1.5.3 — clustering (MIT)
- [MapLibre GL JS](https://maplibre.org/) 4.7.1 et [maplibre-gl-leaflet](https://github.com/maplibre/maplibre-gl-leaflet) 0.0.22 — fonds vectoriels et vue 3D (BSD 3-Clause / ISC)
- Données cartographiques © contributeurs [OpenStreetMap](https://www.openstreetmap.org/copyright) (ODbL)

## Licence

GNU GPL-2.0 ou ultérieure — voir le fichier [LICENSE](LICENSE).

## Changelog

- **1.0.7** — Menu « Fond » sur la carte de la galerie et sur les cartes de géotaggage : ajout d'OpenStreetMap France, OpenFreeMap et Satellite + noms. Bouton « 3D » (vue en relief dans un nouvel onglet). La vue 3D propose une case « Globe » : la Terre en sphère zoomable avec atmosphère (MapLibre GL JS 5). Dans l'administration, si un autre plugin impose un ancien Leaflet (< 1.0), OpenFreeMap n'est pas proposé et « Satellite + noms » passe en version raster Esri. Les paramètres proposent la même liste de fonds pour la galerie et le géotaggage. Compatibilité avec `piwigo-openstreetmap` (une seule instance de Leaflet sur la page). Conformité aux recommandations Piwigo : fichiers `index.php`, `maintain.class.php` (installation, mise à jour, désinstallation propre des réglages), fichiers de langue chargés, dossier `en_UK`. Cartes de géotaggage et mini-carte des pages photo plus robustes (recalcul de taille, bascule automatique sur OpenStreetMap si le fond ne répond pas). Correction d'un blocage de la page galerie lorsque le fond par défaut était OpenFreeMap ou Satellite + noms. Marges latérales et zoom à la molette après clic sur les cartes. Création d'album réservée aux administrateurs.
- **1.0.6** — Clé API CartoDB partagée avec le plugin Geo Album
- **1.0.5** — Bouton « Paramètres » dans la liste des plugins
- **1.0.4** — Adaptation à la clé API CARTO, correction de bug mineur
- **1.0.3** — Adaptation à CARTO : clé API nécessaire pour les tuiles Voyager
- **1.0.2** — Correction de la liste des albums, recherche multicritère lieux et albums, correction des en-têtes pour publication
- **1.0.1** — Changement de numérotation pour la publication
