=== GPX Route Map ===
Contributors: sirlouen
Tags: gpx, map, openstreetmap, elevation, route
Requires at least: 6.6
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Attach a GPX track to any post or page and render it as an interactive OpenStreetMap map with waypoints, distance and an elevation profile.

== Description ==

GPX Route Map turns a GPX file into an interactive map on the front end of your site. Drop the block into any post or page, pick a `.gpx` file, and visitors get:

* An interactive OpenStreetMap map powered by MapLibre GL.
* The track drawn as a route line with start and end markers.
* Support for both GPX tracks (`<trk>`) and GPX routes (`<rte>`), e.g. Garmin Connect course exports.
* Waypoint markers with popups for any `<wpt>` points in the file.
* A stats bar: distance, elevation gain, elevation loss and max elevation, in metric or imperial units.
* A hand-drawn elevation profile you can scrub with mouse or touch, the position syncs onto the map.

The map loads lazily (only when it scrolls into view) so it never slows down your page, and multiple maps can live on the same page.

You can author tracks in any GPX tool, for example the free [gpx.studio](https://gpx.studio) editor, then upload the resulting file to your Media Library.

= OpenStreetMap tile usage =

By default the map uses OpenStreetMap's public tile server, an external service. When a page with a map is viewed, the visitor's browser loads map tiles directly from `tile.openstreetmap.org`, which means that server receives the visitor's IP address, browser User-Agent and the coordinates of the map area viewed. See the [OpenStreetMap Tile Usage Policy](https://operations.osmfoundation.org/policies/tiles/) and the [OSMF Privacy Policy](https://osmfoundation.org/wiki/Privacy_Policy). The same data is sent to any custom tile provider you configure instead.

The public OSM tile server is rate-limited and is not intended for high-traffic sites. For busy sites, set a custom tile provider in the block's Map tiles panel (or via the `gpxrm_tile_url` filter). Attribution to OpenStreetMap is always shown, as the license requires.

= Source code =

The front-end JavaScript in `build/` is minified (the block editor script is not, so that its strings stay translatable). The human-readable source lives in the plugin's `src/` directory, which is included in this plugin, and is also available with full build instructions at the public repository: [https://github.com/SirLouen/gpx-route-map](https://github.com/SirLouen/gpx-route-map). The plugin is built with Vite (`pnpm install && pnpm run build`).

== Installation ==

1. Upload the `gpx-route-map` folder to `/wp-content/plugins/`, or install through the Plugins screen.
2. Activate the plugin.
3. Add the **GPX Route Map** block to a post or page and select a GPX file, or use the shortcode.

== Usage ==

Block: search for "GPX Route Map" in the block inserter, then choose a GPX file.

Shortcode: `[gpx_route_map]`

Examples:

`[gpx_route_map id="123"]` - render the GPX attachment with ID 123.
`[gpx_route_map gpx="https://example.com/route.gpx" height="520" elevation="false"]`

Shortcode attributes:

* `id` - Attachment ID of a .gpx file uploaded to the Media Library.
* `gpx` - Attachment ID, or an absolute URL to a .gpx file (alternative to `id`).
* `height` - Map height in pixels, from 200 to 1200. Default: 480.
* `stats` - Show the stats bar (distance, elevation, waypoints). `true` or `false`. Default: true.
* `elevation` - Show the interactive elevation profile. `true` or `false`. Default: true.
* `maxzoom` - Maximum zoom level, from 1 to 22. Default: 17.
* `tile` - Custom raster tile URL template using `{z}/{x}/{y}`. Default: OpenStreetMap.
* `units` - `metric` (km / m) or `imperial` (mi / ft). Default: the site setting.

Provide either `id` or `gpx`. The block exposes the same options in its sidebar (Source, Display and Map tiles panels).

Filters (for developers):

* `gpxrm_tile_url` - change the default raster tile URL template.
* `gpxrm_tile_attribution` - change the attribution HTML.
* `gpxrm_units` - change the site-wide unit system (`metric` or `imperial`).

== Frequently Asked Questions ==

= Does it require Advanced Custom Fields or any other plugin? =

No. It has no plugin dependencies and works with core WordPress.

= Where do the maps and elevation come from? =

Map rendering uses MapLibre GL JS (BSD-3-Clause) with OpenStreetMap raster tiles. The track, elevation profile and the distance/elevation stats are all computed from your GPX file in the browser. When you add the block, those stats are saved with it so the summary still shows without JavaScript.

= Can I show miles and feet instead of kilometres and metres? =

Yes. Go to Settings > General and set "GPX map units" to Imperial, and every map on the site switches to miles and feet. A single map can use different units from the rest: pick them in the block's Display panel, or add `units="imperial"` to the shortcode. Switching is instant and can be undone at any time, because the plugin always stores distances and elevations in metric and only converts them for display.

= Can I use my own map tiles? =

Yes, set a custom tile URL in the block's Map tiles panel or with the `gpxrm_tile_url` filter.

= Can I link a GPX file hosted on another site? =

Only if that host allows cross-origin (CORS) requests, because the visitor's browser fetches the file directly, and most external sites don't send the required header. The front end then shows "Could not load GPX file." even though the link opens fine in a browser tab. Uploading the file to your Media Library is the reliable option.

= Why don't my existing GPX files appear in the media picker? =

GPX files uploaded before this plugin was activated may be stored with a generic XML type or no type at all. The picker shows GPX and XML types; files stored with an empty or unrelated type won't appear. Re-upload them to fix the stored type. On multisite, the network's "Upload file types" setting must also include `gpx`.

= The front end says "Map failed to load." What's wrong? =

The map library could not be downloaded. The plugin loads its map code as a native JavaScript module, which optimization plugins normally leave alone. But if yours is configured to combine, inline or relocate module scripts, exclude this plugin's view script from it. Visitors on a flaky connection can simply click the message to retry.

== Screenshots ==

1. An interactive route map with waypoints and an elevation profile.
2. Selecting a GPX file in the block editor.

== Changelog ==

= 1.3.0 =
* New: choose between metric (km / m) and imperial (mi / ft) units. Set it for the whole site under Settings > General, override it on a single map from the block's Display panel, or pass `units="imperial"` to the shortcode.
* The unit choice applies to the stats bar and to the elevation profile, including its axes and the readout you get while scrubbing.
* Distances and elevations are still stored in metric, so switching units is instant and changes nothing in your saved content.

= 1.2.1 =
* Tested with WordPress 7.1.
* Block editor controls now use the 40px sizing that becomes the default in WordPress 7.1, so the settings panel keeps its intended layout.
* Replaced an editor data call that was deprecated in WordPress 6.9. No visible change.

= 1.2.0 =
* Full Spanish (es_ES) translation.
* The plugin is now fully translatable: every string in the block editor, the stats bar and the front-end messages can be translated, and a `.pot` template is bundled for translators.

= 1.1.0 =
* Route statistics (distance, elevation gain/loss, max elevation and waypoint count) are now computed in your browser when the block is added and saved with it — a single source of truth that removes a duplicate server-side parser. The stats bar shown without JavaScript now also includes the waypoint count.
* The `[gpx_route_map]` shortcode now fills its stats bar with JavaScript; the block continues to show statistics without JavaScript.

= 1.0.0 =
* Initial release: GPX Route Map block and `[gpx_route_map]` shortcode with MapLibre map, waypoints, stats and an interactive elevation profile.

== Upgrade Notice ==

= 1.3.0 =
Adds a metric/imperial unit setting. Existing maps keep showing metric until you change it.

= 1.2.1 =
Compatibility release for WordPress 7.1.

= 1.2.0 =
Adds a full Spanish translation and makes every remaining string translatable.

= 1.1.0 =
Statistics are now computed in the browser and stored with each block. Existing GPX blocks keep working; re-save one to refresh the statistics shown without JavaScript.

= 1.0.0 =
Initial release.
