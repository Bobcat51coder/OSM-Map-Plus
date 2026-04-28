Ce plugin est distribué sous licence [GNU GPL v2](LICENSE).

# OSM Map Enhanced — Plugin Piwigo

Affiche une carte OpenStreetMap interactive sur votre galerie Piwigo, avec toutes les photos géolocalisées (GPS).

## Fonctionnalités

- Carte OpenStreetMap avec clustering automatique des marqueurs
- Panneau liste latéral synchronisé avec la zone visible
- Filtrage par album
- Géocodeur (recherche de lieu)
- Création d'un album Piwigo depuis les photos visibles sur la carte
- Mini-carte sur les pages photo individuelles
- Contrôle d'accès : carte réservée aux connectés ou ouverte au public (albums publics uniquement)
- Page d'administration avec options configurables

## Installation

1. Décompresser dans `plugins/osm_map/`
2. Activer depuis Administration → Plugins
3. Configurer depuis Administration → Plugins → OSM Map Enhanced (roue dentée)

## Configuration

| Option | Défaut | Description |
|---|---|---|
| Affichage public | Non | Carte visible par les visiteurs non connectés |
| Hauteur de la carte | 500 px | Entre 200 et 1200 px |
| Nombre max de photos | 2000 | Entre 100 et 10 000 |
| Zoom initial | 5 | 1 = monde, 18 = rue |

## Crédits

**Conception, cahier des charges et tests**
Bobcat-Fr

**Développement assisté par IA**
Code généré par [Claude](https://claude.ai) (Anthropic) via une session de développement itératif —
spécifications, corrections et validation assurées par Bobcat-Fr.

**Librairies utilisées**
- [Leaflet](https://leafletjs.com/) 1.9.4 — carte interactive (BSD 2-Clause)
- [Leaflet.markercluster](https://github.com/Leaflet/Leaflet.markercluster) 1.5.3 — clustering (MIT)
- [OpenStreetMap](https://www.openstreetmap.org/) — tuiles cartographiques (ODbL)
- [CartoDB](https://carto.com/) — style de tuiles Voyager
- [Nominatim](https://nominatim.org/) — géocodage

## Licence

MIT — libre d'utilisation, de modification et de redistribution avec mention des crédits.

## Historique

| Version | Description |
|---|---|
| 2.5.0 | Filtre par catégorie courante, page admin complète |
| 2.4.x | Page d'administration, contrôle d'accès public/privé |
| 2.3.x | Sécurité : masquage carte si non connecté |
| 2.2.x | Création d'album depuis la carte |
| 2.1.x | Correction URLs, compatibilité Safari |
| 2.0.x | Version initiale : carte, clustering, panneau liste |
