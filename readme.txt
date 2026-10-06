=== GlocalSaino Layer Map Viewer ===
Contributors: glocalsaino, rafammoo
Tags: kml, map, leaflet, gis, kmz
Requires at least: 5.8
Tested up to: 7.1.2
Stable tag: 5.15.0
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Upload KML files and display them as interactive maps with colored layers, opacity control and field filtering, unlimited and free.

== Description ==

GlocalSaino Layer Map Viewer turns any KML file (the format exported by QGIS, Google Earth, and most GIS software) into an interactive map you can embed in any page or post with a shortcode.

Built from the ground up for large KML files (tens of thousands of objects): instead of making the browser download and process the entire file, the plugin analyzes it in the background on upload and builds an index; the viewer then requests objects from the server page by page, without freezing the tab even on low-end mobile devices.

Every feature below is included, unlimited, in the free plugin — there is no map cap and nothing is locked behind a license.

= Key features =

* Create as many maps as you want, each with one or several KML layers.
* Add more KML layers to a map that already exists, without recreating it.
* Each layer is drawn in its own color; both the color and the layer's name can be changed at any time, not just when it was uploaded.
* Adjustable fill opacity per layer, from fully transparent (outline only) to fully opaque.
* A legend next to the filter bar shows the color and name of every layer on the map.
* For point layers, choose the marker size (a circle in the layer's color) at upload time.
* Point markers that end up close together are automatically grouped into clusters, instead of stacking on top of each other; they split apart as you zoom in.
* Choose which KML field is used for filtering and which fields show in the popup.
* Interactive filter by that field (for example, a parcel code or a municipality), with the dropdown already computed server-side.
* Customize the colors of the filter bar and the "Clear filter" button.
* Popup with the object's data on click.
* OpenStreetMap and satellite (Esri) base layers; more can be added by an add-on via the `glocalsaino_map_base_layers` filter, without modifying this plugin.
* Background analysis and index building (WP-Cron): uploading a KML with thousands of objects never blocks the admin panel.
* Paginated loading in the browser: objects appear progressively without hanging the tab, and once loaded they don't disappear when zooming.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install it from the admin panel (Plugins → Add New).
2. Activate it from the "Plugins" menu.
3. Go to the new "Mapas KML" menu in the admin panel and create your first map by uploading one or more `.kml` files.
4. Copy the shortcode shown (for example `[glocalsaino_map id="1"]`) and paste it into any page or post.

= Shortcode parameters =

* `id` (required) — the map's ID, shown next to each map in the admin panel.
* `height` — height in pixels (default: 550).
* `zoom` — initial zoom level (default: 8, only used together with `lat`/`lng`, or to force the zoom on top of the automatic layer fit).
* `lat` / `lng` — initial map center. When set, they take priority over the automatic fit-to-layers view.

Example: `[glocalsaino_map id="1" height="700" zoom="12" lat="40.4168" lng="-3.7038"]`

== Frequently Asked Questions ==

= Does it support KMZ files? =

Not yet, only `.kml` for now (KMZ is a KML compressed inside a ZIP; you'd need to decompress it first).

= How many objects can a KML file handle? =

It has been tested with layers of more than 60,000 objects. The file is analyzed in the background on upload, and the viewer loads objects page by page, so the file size never blocks the admin panel or the visitor's browser.

= What server settings do I need for very large KML files? =

For small and medium files, the defaults on most hosts are fine. For very large ones (tens of thousands of objects, or files of dozens of MB), check with your host or server admin:

* **`upload_max_filesize` and `post_max_size`** (PHP): the file can't be uploaded past whichever of these two is smaller. The admin panel shows your current effective limit above the upload button.
* **`memory_limit`** (PHP): 256 MB or more is recommended for very large layers. The plugin already asks WordPress to raise it for its own background processing (`wp_raise_memory_limit()`), but that can't exceed your host's hard limit.
* **WP-Cron must be able to run**: the actual analysis happens in the background via WP-Cron, triggered right after upload and again on your next visits to the admin panel until it's done. If you've disabled WordPress's built-in cron (`DISABLE_WP_CRON`), make sure a real server cron job hits `wp-cron.php` periodically, or background analysis will never start.
* If a background pass gets interrupted (e.g. by a server-level time limit), it's safe: nothing partial is served, and the next attempt starts clean.

= Why don't objects appear right after uploading the file? =

The KML is analyzed in the background (WP-Cron) so the upload itself isn't blocked. While that finishes, the map shows a general view and a "Processing layers in the background" badge; if it takes longer than expected, the "Analyze now" button forces the analysis immediately.

= Which field is used for the filter if I don't configure one? =

None by default: the filter bar only appears once you choose a field under "Popup Fields" in the admin panel for that map.

= How many maps can I create? =

As many as you want — there is no limit.

== External services ==

This plugin uses Esri's World Imagery service to provide the optional "satellite" base map layer.

* What it is and what it's used for: a tile service that supplies satellite/aerial imagery tiles, so visitors can switch the map between a standard OpenStreetMap view and a satellite view.
* What data is sent and when: whenever a visitor views a map with the satellite layer selected, their browser requests map tiles directly from Esri's servers for the visible area (standard web-map tile requests: approximate viewport coordinates and zoom level, plus the visitor's own IP address as part of any HTTP request). No personal data is collected or sent by the plugin itself.
* Provider: Esri ("World Imagery" service, `server.arcgisonline.com`). [Terms of Use](https://www.esri.com/en-us/legal/terms/web-site-service) — [Privacy Policy](https://www.esri.com/en-us/privacy/overview).

== Screenshots ==

1. Interactive map on the front end, with several layers, an open feature popup, the filter box, and the layer legend.
2. Admin panel: the "Create new map" section.
3. Admin panel: "Create new map" section with a KML file already selected, showing the layer color, transparency, and marker size options.
4. Admin panel: "Create new map" and "Available maps" sections, with editable layer name and color, and the "Add more KML layers", "Popup fields", and "Filter box appearance" options collapsed.
5. Admin panel: "Available maps" section showing the expanded content of the "Add more KML layers", "Popup fields", and "Filter box appearance" options.

== Changelog ==

= 5.15.0 =
* New "Filter links" section in each map's admin panel: a ready-to-copy link for every value of the filter field, which opens the map with that value already selected — share it for a "detail" view of one category instead of the full map. The link is built automatically from the page where the shortcode is already pasted.
* Confirmed compatible with WordPress 7.1.2 (the current release).

= 5.14.0 =
* Removed the Freemius SDK entirely. This plugin has never had a premium version or paid plans of its own, and the "Extensions" submenu it enabled (for promoting the External Data Layers add-on and the rest of the GlocalSaino plugin family) has been self-built since it was introduced, so the SDK had no remaining purpose — External Data Layers is now registered in Freemius as its own independent product instead of a linked add-on of this one. No functional change for site owners; the plugin zip is smaller.

= 5.13.2 =
* Added a logo to each card in the "Extensions" submenu.

= 5.13.1 =
* Redesigned the "Extensions" submenu: the External Data Layers add-on is now a compact card (matching the format used across the other GlocalSaino plugins) instead of a long banner and feature write-up, and it now also cross-promotes GlocalSaino Auctions Displayed by Shortcodes and GlocalSaino WebPagesPassworded — each showing "Active" automatically when already installed.

= 5.13.0 =
* A layer's name and color can now be edited at any time from the map's layer list, not just when it was first uploaded.
* Added a legend (color + name of every layer added to the map, base layers not included) next to the filter bar, on the right on desktop and below the filter on narrower screens. It always lists every layer regardless of which ones are currently shown or hidden via the layers control on the map.

= 5.12.5 =
* Fixed a data-loss bug: if a map's layer data was ever unreadable for any reason (however rare), several actions — "Add more KML layers", changing the filter field, "Analyze now", and simply opening the admin screen — would silently treat the map as if it had no layers yet, and the next save would overwrite the real (if temporarily unreadable) data with an empty list, permanently losing every layer. All of these now detect that case, leave the existing data untouched, and show a clear warning instead. Add-ons using `kml_map_save_layers()` to add layers (e.g. External Data Layers) get the same protection via a new `kml_map_get_layers()` helper.

= 5.12.4 =
* Popup field values that are a plain http/https URL are now shown as a clickable link (opens in a new tab), instead of plain text. Only whole-value URLs are linked — a URL embedded inside a longer text is left as-is — and any other scheme (e.g. `javascript:`) is never linked.
* Removed the "Layer:" line that used to appear at the bottom of every popup.

= 5.12.3 =
* Added an "Extensions" submenu (Layer Map Viewer → Extensions) presenting the GlocalSaino External Data Layers add-on, with a link to https://glocalsaino.com/externaldatalayers/. Pinned last in the admin sidebar.
* Disabled the Freemius pricing tab in this plugin's own menu, since it stays 100% free and add-ons are promoted through the new Extensions page instead.

= 5.12.2 =
* Updated "Tested up to" to WordPress 7.1.
* Added the plugin's website (`Plugin URI`): https://glocalsaino.com/layermapviewer/
* Replaced all 3 screenshots with 5 new ones reflecting the current English admin UI.

= 5.12.1 =
* All admin screens and front-end text were originally written in Spanish as the source language; the plugin's source strings are now written in English throughout (translation into other languages via .po/.mo files is unaffected).

= 5.12.0 =
* The "live layer" last-updated badge now collapses into a dropdown when there's more than one live layer on the same map, instead of stacking a line per layer and taking up a lot of space. With just one live layer it still shows directly, same as before.

= 5.11.2 =
* Silenced a Plugin Check warning about the `error_log()` call added in 5.11.1 (only ever runs if the site already has `WP_DEBUG_LOG` on; no functional change).

= 5.11.1 =
* Added `kml_map_save_layers()`, a small safety net around saving a map's layers: if the data somehow couldn't be encoded to valid JSON, the existing layers are left untouched instead of being overwritten with an empty result. Used everywhere this plugin saves a map's layers, and available for add-ons to use too.

= 5.11.0 =
* Base map layers (OpenStreetMap, satellite) are now built from a filterable list (`glocalsaino_map_base_layers`) instead of being hardcoded, so an add-on can offer more of them (e.g. topographic, alternate styles) without modifying this plugin. No visible change without such an add-on installed.

= 5.10.1 =
* A layer type registered by an add-on (`glocalsaino_map_layer_types`, e.g. the external data-source add-on) can now report why it found no data (bad URL, unexpected response format, etc.) as a `last_error` stored on the layer itself, so add-ons can surface it in their own admin UI instead of it only ever reaching the debug log. No effect on regular KML layers.

= 5.10.0 =
* The filter field dropdown (under "Campos del popup") now has a "— Sin filtro —" option, so a map that already had a filter field chosen can go back to having no filter. Previously there was no way to unset it once chosen: the dropdown always had a real field selected, and even editing the underlying data directly wouldn't have worked, since saving an empty value was silently ignored.

= 5.9.2 =
* Fixed "live" layers (layers with a refresh interval, e.g. from the external data-source add-on) sometimes not showing a moved object's new position after a scheduled refresh: the objects endpoint sends `Cache-Control: public, max-age=60` so regular KML layers can be cached, but that same caching could make the browser, a CDN or the hosting's own cache serve a stale response for a live layer's poll instead of hitting the server again. Live-layer requests now explicitly ask for an uncached response.

= 5.9.1 =
* Fixed marker clusters sometimes requiring an unreasonably deep zoom to split apart and click on an individual marker, past the point where the base map (OpenStreetMap/satellite) stops rendering tiles and turns blank. The map's zoom is now capped at the base layers' own maximum (19), and clusters that are still tight at that zoom simply show every marker individually instead of trying to zoom in further.

= 5.9.0 =
* Point markers that end up close together are now automatically grouped into clusters (bundling Leaflet.markercluster), instead of stacking on top of each other with no way to tell them apart or click the one underneath. They split apart automatically as you zoom in.

= 5.8.0 =
* Removed the emoji/text marker option added in 5.5.0: a point is now always drawn as a circle in the layer's color, with just its size configurable. Simpler and clearer than choosing between a circle and a custom glyph.

= 5.7.2 =
* Clearer marker options when uploading a KML with points: renamed "Icono (si son puntos)" to "Marcador", added visible labels above the type/emoji/size fields (previously only shown as a hover tooltip) and widened the size field so the number is actually readable. The color picker now shows the exact hex value next to the swatch as you pick it.

= 5.7.1 =
* Minor wording tweak in the upload form's help text.

= 5.7.0 =
* New optional shortcode parameters: `height` (default 550px), `zoom` (default 8) and `lat`/`lng`, to set the map's height and initial view instead of always fitting to the layers' bounds. See "Shortcode parameters" below.
* Fixed a bug (introduced in 5.6.0) where a map with no layers yet never actually initialized on the front-end, showing a blank space instead of the base map.

= 5.6.0 =
* A map can now be created without uploading any KML file, showing just the base map (OpenStreetMap/satellite) until layers are added — for example, from a data-source add-on. KML layers can still be added and removed at any time, same as before.

= 5.5.0 =
* For layers whose objects turn out to be points (for example, a live external data source), you can now also choose the marker icon at upload time: a plain colored circle (using the same layer color) or an emoji/text glyph, plus its size. No effect on polygon/line layers.

= 5.4.0 =
* Added a generic "live layer" indicator: any layer whose data can include an optional refresh interval (used by add-ons with periodically-updating data sources) is now re-fetched automatically in the browser on that schedule, with an on-map badge showing when each such layer was last updated. No effect on regular KML layers, which don't set this.

= 5.3.1 =
* Fixed a bug where deleting a layer left its fields "orphaned" forever in the "Popup Fields" list and filter dropdown, as if a layer still provided them. Deleting a layer now removes any field no longer provided by a remaining layer, and resets the filter field if it was the one removed.

= 5.3.0 =
* Added extension points (`glocalsaino_map_layer_types` filter, `glocalsaino_map_after_add_layers_form` and `glocalsaino_map_layer_row_after` actions) so add-ons can register additional layer types — for example, a live external data source — without modifying this plugin's code. No effect on sites without such an add-on installed.

= 5.2.1 =
* Fixed the "loading objects…" badge overlapping the base map attribution (OpenStreetMap/Esri) on narrow (mobile) screens, making both unreadable. Moved the badge next to the zoom control instead, where Leaflet stacks controls without overlapping regardless of screen width.

= 5.2.0 =
* Popup fields and the filter field dropdown now show which layer(s) each field comes from (for example "nombre (Capa A, Capa B)"), so it's clear when two layers happen to share a field name but not necessarily its meaning. Existing maps pick this up the next time they're reanalyzed (e.g. via "Analyze now").
* Added rafammoo as a contributor.

= 5.1.2 =
* Fixed new Plugin Check ERRORs introduced by 5.1.0's rewrite: raw `fopen()`/`fwrite()`/`fclose()` aren't allowed by WordPress.org guidelines (`WordPress.WP.AlternativeFunctions.file_system_operations_*`). Switched the incremental index writer to `file_put_contents()` with `FILE_APPEND` (still memory-safe, one object at a time, never disallowed) and the reader to `file_get_contents()` per cell file, freeing each cell's content before moving to the next.
* Sanitized/wrapped the new file-size check added in 5.1.0 to fix its own `InputNotSanitized` warning.

= 5.1.1 =
* Documented the server/WordPress settings that matter for very large KML files (`upload_max_filesize`/`post_max_size`, `memory_limit`, WP-Cron) in a new FAQ entry, and added a short pointer to it next to the upload form.

= 5.1.0 =
* Fixed a bug affecting KML layers with a very large number of objects: building the spatial index used to hold every object of the layer in memory before writing anything to disk, which could exhaust PHP's memory limit partway through and silently leave the layer with no servable objects (while popup fields/filter values, calculated separately, still appeared correctly). Objects are now written to disk as they're read, never held in memory all at once. Existing indexes rebuild automatically in the background.
* The "Analyze now" button no longer runs the analysis inside the same request (which could exceed the web server's own time/memory limits on very large files and return a 500 error); it now forces the layer to be reprocessed in the background, same as after uploading.
* Added a specific error message when an uploaded file exceeds the server's `upload_max_filesize`/`post_max_size` limit, instead of the misleading "files must have a .kml extension" message.

= 5.0.3 =
* Renamed the admin menu from "Mapas" to "Layer Map Viewer".

= 5.0.2 =
* Fixed `InputNotSanitized` warnings on `$_FILES['kml_files']` that came back after adding the opacity parameter: with 4 arguments, Freemius's packaging tool now wraps the `kml_map_upload_files()` call across multiple lines, which again separated the `phpcs:ignore` comment from the actual flagged line. Fixed by extracting the `$_FILES` access into its own single-line variable, immune to argument-count-driven line wrapping.
* Renamed the admin menu from "Mapas KML" to "Mapas", since upcoming add-ons will support layer formats beyond KML.

= 5.0.1 =
* Removed `wp_org_gatekeeper` from the Freemius config: it only mattered for generating a stripped-down free package from a premium source, which no longer applies now that there's no premium code at all. Set `has_addons` back to `false` until a real add-on product exists to link.

= 5.0.0 =
* Renamed the plugin to "GlocalSaino Layer Map Viewer" (previously "KML-Map").
* The plugin is now 100% free with no limits: removed the 3-map cap and unlocked "add more layers", "popup fields" and "filter bar appearance" for everyone, in compliance with WordPress.org's guideline against locking built-in functionality behind a license. Paid add-ons (more layer formats, more base maps) will be offered separately in the future, not bundled with this plugin.
* New feature: adjustable fill opacity per layer.
* Moved the inline admin `<script>` block to a properly enqueued script file (`assets/js/admin-page.js`), scoped only to this plugin's own admin page.
* Added disclosure of the Esri World Imagery external service to this readme.
* Renamed internal identifiers (shortcode, post type, REST namespace, admin menu, script/style handles) to avoid collisions with other plugins.

= 4.5.1 =
* Fixed a bug where the "add more layers" / "popup fields" / "filter bar appearance" upsell notices always showed as locked in the free package, even for users with an active trial or paid license.

= 4.5.0 =
* Moved the Freemius SDK to `vendor/freemius/wordpress-sdk/`, loaded via `vendor/autoload.php`, per WordPress.org's guidance.

= 4.4.0 and earlier =
* See the plugin's GitHub repository for the full history prior to the WordPress.org submission.
