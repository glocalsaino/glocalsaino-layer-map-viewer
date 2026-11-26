# GlocalSaino Layer Map Viewer

Mapas interactivos a partir de archivos KML, con capas de colores, filtros por campo y transparencia. Publicado en WordPress.org. **Repo público.**

- Versión 5.17.0. Shortcode `[glocalsaino_map]`, REST `glocalsaino-map/v1`, menú de admin `glocalsaino-maps`.
- Estructura: `glocalsaino-layer-map-viewer.php`, `admin/`, `assets/`.
- Sin SDK de Freemius (eliminado en 2026-09). Add-on de pago: `glocalsaino-external-data-layers`, que detecta este plugin con `function_exists('kml_map_tile_dir')`: no renombrar esa función.
- Tiene página Extensions.
- Zip: `git -C ~/proyectos/wordpress/glocalsaino-layer-map-viewer archive --format=zip --prefix=glocalsaino-layer-map-viewer/ -o ~/Desktop/glocalsaino-layer-map-viewer-<versión>.zip HEAD` (ver ~/.claude/CLAUDE.md).
