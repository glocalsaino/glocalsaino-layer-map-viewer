=== GlocalSaino Layer Map Viewer ===
Contributors: glocalsaino, rafammoo
Tags: kml, map, leaflet, gis, kmz
Requires at least: 5.8
Tested up to: 7.0
Stable tag: 5.2.1
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
* Each layer is drawn in its own color, chosen at upload time.
* Adjustable fill opacity per layer, from fully transparent (outline only) to fully opaque.
* Choose which KML field is used for filtering and which fields show in the popup.
* Interactive filter by that field (for example, a parcel code or a municipality), with the dropdown already computed server-side.
* Customize the colors of the filter bar and the "Clear filter" button.
* Popup with the object's data on click.
* OpenStreetMap and satellite base layers.
* Background analysis and index building (WP-Cron): uploading a KML with thousands of objects never blocks the admin panel.
* Paginated loading in the browser: objects appear progressively without hanging the tab, and once loaded they don't disappear when zooming.

== Installation ==

1. Upload the plugin folder to `/wp-content/plugins/` or install it from the admin panel (Plugins → Add New).
2. Activate it from the "Plugins" menu.
3. Go to the new "Mapas KML" menu in the admin panel and create your first map by uploading one or more `.kml` files.
4. Copy the shortcode shown (for example `[glocalsaino_map id="1"]`) and paste it into any page or post.

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

None by default: the filter bar only appears once you choose a field under "Campos del popup" (Popup Fields) in the admin panel for that map.

= How many maps can I create? =

As many as you want — there is no limit.

== External services ==

This plugin uses Esri's World Imagery service to provide the optional "satellite" base map layer.

* What it is and what it's used for: a tile service that supplies satellite/aerial imagery tiles, so visitors can switch the map between a standard OpenStreetMap view and a satellite view.
* What data is sent and when: whenever a visitor views a map with the satellite layer selected, their browser requests map tiles directly from Esri's servers for the visible area (standard web-map tile requests: approximate viewport coordinates and zoom level, plus the visitor's own IP address as part of any HTTP request). No personal data is collected or sent by the plugin itself.
* Provider: Esri ("World Imagery" service, `server.arcgisonline.com`). [Terms of Use](https://www.esri.com/en-us/legal/terms/web-site-service) — [Privacy Policy](https://www.esri.com/en-us/privacy/overview).

== Screenshots ==

1. Interactive map on the front-end, with several layers and a field filter.
2. Admin panel: map creation form and listing of an existing map.
3. Admin panel: layer options expanded (add more layers, popup fields, and filter bar appearance).

== Changelog ==

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
