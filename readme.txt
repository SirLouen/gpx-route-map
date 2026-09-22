=== GPX Route Map ===
Contributors: sirlouen
Tags: gpx, map, openstreetmap, elevation, route
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.7.1
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

= Source code =

The front-end JavaScript in `build/` is minified (the block editor script is not, so that its strings stay translatable). The human-readable source lives in the plugin's `src/` directory, which is included in this plugin, and is also available with full build instructions at the public repository: [https://github.com/SirLouen/gpx-route-map](https://github.com/SirLouen/gpx-route-map). The plugin is built with Vite (`pnpm install && pnpm run build`).

== External services ==

This plugin renders maps using map images (tiles) served by a third party. Tiles are loaded directly by each visitor's browser when a page containing a map is viewed, so the tile provider receives that visitor's IP address, browser User-Agent, and the coordinates of the map area being viewed. No data is sent when a page has no map on it, and the plugin never sends your GPX files anywhere.

= OpenStreetMap (default) =

Out of the box the plugin uses OpenStreetMap's public tile server at `tile.openstreetmap.org`. No account or key is needed, and nothing has to be configured.

Service: [OpenStreetMap](https://www.openstreetmap.org/). Terms: [Tile Usage Policy](https://operations.osmfoundation.org/policies/tiles/). Privacy: [OSMF Privacy Policy](https://osmfoundation.org/wiki/Privacy_Policy).

The public OSM tile server is rate-limited and is not intended for high-traffic sites.

= Thunderforest (optional) =

If you choose Thunderforest under Settings > GPX Route Map and enter an API key, tiles are loaded from `api.thunderforest.com` instead, and your API key is included in each tile request. This only happens once you have selected Thunderforest and supplied a key; the plugin never contacts them otherwise.

Because visitors' browsers load the tiles, the key is visible in your pages' source. Thunderforest does not offer a way to restrict a key to your domain, so treat the key as public and keep an eye on your usage.

Service: [Thunderforest](https://www.thunderforest.com/). Terms: [Thunderforest Terms and Conditions](https://www.thunderforest.com/terms/). Privacy: [Thunderforest Privacy Policy](https://www.thunderforest.com/privacy/).

= Any other provider =

If you set a custom tile URL of your own, the same applies to whichever server that URL points at.

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
* `height` - Map height in pixels, from 200 to 1200. Default: the site setting.
* `height_tablet` - Map height at 782px and below. Default: the site's Tablet height, or `height` when the site sets none.
* `height_mobile` - Map height at 480px and below. Default: the site's Mobile height, or `height_tablet` when the site sets none.
* `stats` - Show the stats bar (distance, elevation, waypoints). `true` or `false`. Default: the site setting.
* `elevation` - Show the interactive elevation profile. `true` or `false`. Default: the site setting.
* `download` - Show the GPX download button on the map. `true` or `false`. Default: the site setting (off).
* `maxzoom` - Maximum zoom level, from 1 to 22. Default: the site setting.
* `tile` - Custom raster tile URL template using `{z}/{x}/{y}`. Must be https on an https site: browsers refuse insecure tile requests and the map draws blank. Default: OpenStreetMap.
* `units` - `metric` (km / m) or `imperial` (mi / ft). Default: the site setting.
* `fields` - Which stats to list, e.g. `distance,gain`. Default: the site setting. Use `stats="false"` to remove the bar.

Provide either `id` or `gpx`. The block exposes the same options in its sidebar (Source, Display and Map tiles panels).

Filters (for developers):

* `gpxrm_tile_url` - change the default raster tile URL template.
* `gpxrm_tile_attribution` - change the attribution HTML.
* `gpxrm_height` - change the site-wide map height in pixels (integer).
* `gpxrm_max_zoom` - change the site-wide maximum zoom level (integer).
* `gpxrm_heights` - change the site-wide height for every viewport band (array of `base`, `tablet`, `mobile`; 0 means "follow the next larger band").
* `gpxrm_units` - change the site-wide unit system (`metric` or `imperial`).
* `gpxrm_show_stats` - change whether the stats bar shows by default (boolean).
* `gpxrm_show_elevation` - change whether the elevation profile shows by default (boolean).
* `gpxrm_show_download` - change whether the download button shows by default (boolean).
* `gpxrm_stat_fields` - change which stats the bar lists by default (array of `distance`, `gain`, `loss`, `max`, `waypoints`).

== Frequently Asked Questions ==

= Does it require Advanced Custom Fields or any other plugin? =

No. It has no plugin dependencies and works with core WordPress.

= Where do the maps and elevation come from? =

Map rendering uses MapLibre GL JS (BSD-3-Clause) with OpenStreetMap raster tiles. The track, elevation profile and the distance/elevation stats are all computed from your GPX file in the browser. When you add the block, those stats are saved with it so the summary still shows without JavaScript.

= Can I show miles and feet instead of kilometres and metres? =

Yes. Go to Settings > GPX Route Map and set "Units" to Imperial, and every map on the site switches to miles and feet. A single map can use different units from the rest: pick them in the block's Display panel, or add `units="imperial"` to the shortcode. Switching is instant and can be undone at any time, because the plugin always stores distances and elevations in metric and only converts them for display.

= Can I change how tall the maps are? =

Yes, for the whole site or for a single map. Go to Settings > GPX Route Map and set "Map height" to size every map that does not set its own. To change just one map, turn off "Use site default height" in the block's Display panel and pick a height, or add `height="600"` to the shortcode. Heights run from 200 to 1200 pixels.

= My custom tile URL shows a blank map =

Browsers refuse to load tiles requested over `http` on a site served over `https`, and the map ends up blank even though the track, markers and stats still draw. Use an `https` tile address. A tile server running on the same machine as the browser, such as `http://localhost:8080`, is exempt and keeps working.

An address without `http://` or `https://` in front of it, including one starting with `//`, is ignored altogether and the map falls back to OpenStreetMap. The block warns about both cases in the Map tiles panel while you are editing.

= Visitors can zoom in past where my tiles stop =

Set a lower "Max zoom" under Settings > GPX Route Map. Most raster providers stop supplying tiles well before zoom 22, and past that point the map keeps zooming into blank space. A single map can differ: use the block's Max zoom slider, or add `maxzoom="18"` to the shortcode.

= Can maps be shorter on phones? =

Yes. Alongside the main height there are separate Tablet and Mobile heights, both site-wide and per map, and a shortcode accepts `height_tablet` and `height_mobile`. Tablet applies at 782px and below and Mobile at 480px and below, matching the widths WordPress itself uses. Leave one empty and the map follows the site's size for that screen, or the size above it when the site sets none - so a site-wide Mobile height applies to every map, including one given its own taller height. The elevation profile also gets shorter on phones so the map and the profile stay visible together.

= Can I hide the stats bar or the elevation profile? =

Yes, either one, for the whole site or for a single map. Go to Settings > GPX Route Map and set "Stats bar" or "Elevation profile" to Hidden to change every map at once. To change just one map, use the block's Display panel, or add `stats="false"` or `elevation="false"` to the shortcode. A map set to "Site default" follows the setting, so you can change your mind later in one place.

= Can I show only some of the figures in the stats bar? =

Yes. Under Settings > GPX Route Map, "Stats shown" has a checkbox for each of Distance, Elevation gain, Elevation loss, Max elevation and Waypoints, so you can keep just the ones you care about. A single map can differ: open the block's "Stats bar items" panel, turn off "Use site default" and tick what you want, or pass `fields="distance,gain"` to the shortcode. At least one figure always stays ticked; to remove the bar altogether use the separate "Stats bar" setting.

= Can visitors download the GPX file? =

Yes, if you switch the download button on. It is off by default. Go to Settings > GPX Route Map and set "Download button" to Shown, or turn it on for a single map from the block's Display panel or with `download="true"` on the shortcode. The button appears on the map next to the zoom and fullscreen controls. Files in your Media Library download with their own filename; a GPX hosted on another site opens instead of downloading, because browsers only honour the download hint for files served from the same site.

= Can I use Thunderforest's outdoor and cycling maps? =

Yes. Sign up at [thunderforest.com](https://www.thunderforest.com/pricing/), then go to Settings > GPX Route Map, set the tile provider to Thunderforest and paste your API key. Pick from the Outdoors, OpenCycleMap, Landscape and Atlas styles. Their free plan covers 150,000 tiles a month, which is roughly 8,000 map views; busier sites need a paid plan.

Two things worth knowing. The key appears in your pages' source, because visitors' browsers fetch the tiles, and Thunderforest cannot lock a key to one domain, so watch your usage. And the map credits both Thunderforest and OpenStreetMap automatically, which their terms require and do not allow you to remove.

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

= 1.7.1 =
* Now requires WordPress 6.8 or newer. WordPress loads plugin translations by itself from 6.8 on, so the plugin no longer does it. Sites on 6.6 or 6.7 keep the version they already have.
* New: set the maximum zoom once for the whole site under Settings > GPX Route Map, instead of repeating it on every map.
* The block now warns when a custom tile URL will not work: an insecure address that browsers refuse to load, or one missing http:// or https:// that the plugin ignores. Both left the map blank with nothing to explain why.


= 1.7.0 =
* New: set the map height once for the whole site under Settings > GPX Route Map, instead of repeating it on every map. Individual maps can still set their own.
* New: separate heights for tablets and phones, site-wide and per map, so a map can be shorter on a small screen. Leave one empty and the map follows the site's size for that screen. The elevation profile shrinks on phones too, so the map and the profile stay visible together.
* The block's height control now moves in steps of 10 pixels rather than 20, so more heights can actually be chosen.
* Maps that never had a height set follow the site setting, so existing maps are unaffected until you change it.
* Updated the map library to MapLibre GL JS 6. Maps now need a browser with WebGL2, which covers Chrome, Edge, Firefox and Safari 15 or newer. Where it is missing, the map shows its "could not load" notice instead of failing silently.


= 1.6.1 =
* Fixed: the "Map failed to load" message on the front end was always in English. It is now translated like every other message the plugin shows.
* Fixed: a hint in the block editor still pointed at Settings > General after the settings moved to their own screen in 1.6.0.
* The Custom tile URL field now documents `{ratio}`, the placeholder for providers that serve retina tiles, and notes that the address should use https.


= 1.6.0 =
* The plugin's settings now have their own screen at Settings > GPX Route Map, instead of sitting at the bottom of Settings > General. Your existing choices carry over untouched.
* New: Thunderforest map tiles. Enter an API key and pick from the Outdoors, OpenCycleMap, Landscape and Atlas styles for maps built for hiking and cycling.
* Maps now credit whichever tile service they actually use. A map pointed at Thunderforest by hand, with a custom tile URL, now carries the credit Thunderforest's terms require instead of only crediting OpenStreetMap.


= 1.5.0 =
* New: choose which figures the stats bar lists. Settings > General has a checkbox for Distance, Elevation gain, Elevation loss, Max elevation and Waypoints, and a single map can differ from the rest through the block's "Stats bar items" panel or the `fields` shortcode attribute.
* The two settings stay distinct: "Stats bar" decides whether the bar appears, "Stats shown" decides what it lists. Choosing the contents is hidden while the bar is switched off.

= 1.4.0 =
* New: choose whether the stats bar and the elevation profile are shown. Set it for the whole site under Settings > General, or override it on a single map from the block's Display panel.
* The `stats` and `elevation` shortcode attributes now follow the site setting when you leave them out, and still accept `true` or `false` to force either way.
* New: an optional download button on the map, in the same control stack as zoom and fullscreen, that lets visitors save the GPX file. It is off until you switch it on, under Settings > General or on a single map.
* Existing maps are unaffected: anything you had already switched off stays off, and no download button appears unless you ask for one.

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

= 1.7.1 =
The block warns when a custom tile URL is not secure. Nothing else changes.


= 1.7.0 =
Map height can now be set once for the whole site, with separate sizes for tablets and phones. Existing maps keep the size they already had. Uses MapLibre GL JS 6, which needs a browser with WebGL2.


= 1.6.1 =
Translation and wording fixes: the front-end "Map failed to load" message is now translatable, and an editor hint pointed at the old settings location.


= 1.6.0 =
Settings move to their own screen at Settings > GPX Route Map, and Thunderforest map tiles can now be used with an API key.


= 1.5.0 =
Adds a setting for choosing which figures the stats bar lists.

= 1.4.0 =
Adds a site-wide setting for showing or hiding the stats bar and the elevation profile.

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
