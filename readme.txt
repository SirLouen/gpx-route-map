=== GPX Route Map ===
Contributors: sirlouen
Tags: gpx, map, openstreetmap, elevation, route
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 2.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Attach a GPX track to any post or page and render it as an interactive OpenStreetMap map with waypoints, distance and an elevation profile.

== Description ==

GPX Route Map turns a GPX file into an interactive map on the front end of your site. Drop the block into any post or page, pick a `.gpx` file, and visitors get:

* An interactive map powered by MapLibre GL, on OpenStreetMap, OpenTopoMap or Thunderforest tiles.
* The track drawn as a route line with start and end markers.
* Support for both GPX tracks (`<trk>`) and GPX routes (`<rte>`), e.g. Garmin Connect course exports.
* Waypoint markers with popups for any `<wpt>` points in the file.
* A stats bar: distance, elevation gain, elevation loss, max and min elevation, in metric or imperial units.
* A hand-drawn elevation profile you can scrub with mouse or touch, the position syncs onto the map.

The map loads lazily (only when it scrolls into view) so it never slows down your page, and multiple maps can live on the same page.

You can author tracks in any GPX tool, for example the free [gpx.studio](https://gpx.studio) editor, then upload the resulting file to your Media Library.

= Source code =

The front-end JavaScript in `build/` is minified (the block editor script is not, so that its strings stay translatable). The human-readable source lives in the plugin's `src/` directory, which is included in this plugin, and is also available with full build instructions at the public repository: [https://github.com/SirLouen/gpx-route-map](https://github.com/SirLouen/gpx-route-map). The plugin is built with Vite (`pnpm install && pnpm run build`).

== External services ==

This plugin renders maps using map images (tiles) served by a third party. Tiles are loaded directly by each visitor's browser when a page containing a map is viewed, so the tile provider receives that visitor's IP address, browser User-Agent, and the coordinates of the map area being viewed. The block editor's map preview loads tiles the same way, from the browser of whoever is editing the post. No data is sent when a page has no map on it, and the plugin never sends your GPX files anywhere.

= OpenStreetMap (default) =

Out of the box the plugin uses OpenStreetMap's public tile server at `tile.openstreetmap.org`. No account or key is needed, and nothing has to be configured.

Service: [OpenStreetMap](https://www.openstreetmap.org/). Terms: [Tile Usage Policy](https://operations.osmfoundation.org/policies/tiles/). Privacy: [OSMF Privacy Policy](https://osmfoundation.org/wiki/Privacy_Policy).

The public OSM tile server is rate-limited and is not intended for high-traffic sites.

= Thunderforest (optional) =

If you choose a Thunderforest style under Settings > GPX Route Map, or for a single map in its block or with the shortcode's `provider` attribute, and enter an API key under Settings, tiles are loaded from `api.thunderforest.com` instead, and your API key is included in each tile request. This only happens once a Thunderforest style is chosen and a key supplied; the plugin never contacts them otherwise.

Because visitors' browsers load the tiles, the key is visible in your pages' source. Thunderforest does not offer a way to restrict a key to your domain, so treat the key as public and keep an eye on your usage.

Service: [Thunderforest](https://www.thunderforest.com/). Terms: [Thunderforest Terms and Conditions](https://www.thunderforest.com/terms/). Privacy: [Thunderforest Privacy Policy](https://www.thunderforest.com/privacy/).

= OpenTopoMap (optional) =

If you choose OpenTopoMap under Settings > GPX Route Map, or for a single map in its block or with the shortcode's `provider` attribute, tiles are loaded from `tile.opentopomap.org` instead. No account or key is needed. This only happens once OpenTopoMap is chosen; the plugin never contacts it otherwise.

OpenTopoMap is run by volunteers, with no guarantee that it stays available, and its maps have less detail close in than OpenStreetMap's.

Service: [OpenTopoMap](https://opentopomap.org/). Terms: [Usage terms](https://opentopomap.org/about#verwendung) (in German). Privacy: OpenTopoMap publishes no privacy policy; its operator is named in the [imprint](https://opentopomap.org/credits).

= Any other provider =

If a developer points the plugin at another tile server with the `gpxrm_tile_url` filter, the same applies to whichever server that is.

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
* `maxzoom` - How close the map opens when it frames a short route, from 1 to 22, though never closer than its tile provider has tiles. Visitors can zoom in as far as those tiles go. Default: the site setting.
* `provider` - The map tiles: `osm` (OpenStreetMap), `opentopomap`, or a Thunderforest style once its API key is saved: `thunderforest-outdoors`, `thunderforest-cycle`, `thunderforest-landscape` or `thunderforest-atlas`. A Thunderforest style without a saved key shows the site's provider. Default: the site setting.
* `units` - `metric` (km / m) or `imperial` (mi / ft). Default: the site setting.
* `fields` - Which stats to list, from `distance`, `gain`, `loss`, `max`, `min` and `waypoints`, e.g. `distance,gain`. Default: the site setting. Use `stats="false"` to remove the bar.

Provide either `id` or `gpx`. The block exposes the same options in its sidebar (Source, Display and Map tiles panels).

Filters (for developers):

* `gpxrm_tile_url` - change the raster tile URL template a map loads. It runs for every map, whether its provider comes from the block, the shortcode or the site setting. The second argument is the provider that map uses, such as `osm`, `opentopomap` or `thunderforest-outdoors`: its own choice, else the site's, else `osm` while a Thunderforest style has no key. A callback that ignores the second argument replaces the tiles of every map, whatever was chosen for it.
* `gpxrm_tile_max_zoom` - change the last zoom level a tile server has tiles for (integer, 0 for not known), such as for a tile server of your own; the second argument is the tile URL template.
* `gpxrm_tile_attribution` - change the attribution HTML.
* `gpxrm_height` - change the site-wide map height in pixels (integer).
* `gpxrm_max_zoom` - change how close maps open when they frame a short route, site-wide (integer).
* `gpxrm_heights` - change the site-wide height for every viewport band (array of `base`, `tablet`, `mobile`; 0 means "follow the next larger band").
* `gpxrm_units` - change the site-wide unit system (`metric` or `imperial`).
* `gpxrm_show_stats` - change whether the stats bar shows by default (boolean).
* `gpxrm_show_elevation` - change whether the elevation profile shows by default (boolean).
* `gpxrm_show_download` - change whether the download button shows by default (boolean).
* `gpxrm_stat_fields` - change which stats the bar lists by default (array of `distance`, `gain`, `loss`, `max`, `min`, `waypoints`).

== Frequently Asked Questions ==

= Does it require Advanced Custom Fields or any other plugin? =

No. It has no plugin dependencies and works with core WordPress.

= Where do the maps and elevation come from? =

Map rendering uses MapLibre GL JS (BSD-3-Clause) with raster tiles from OpenStreetMap, OpenTopoMap or Thunderforest, as chosen under Settings > GPX Route Map or for a single map. The track, elevation profile and the distance/elevation stats are all computed from your GPX file in the browser. When you add the block, those stats are saved with it so the summary still shows without JavaScript.

= Can I show miles and feet instead of kilometres and metres? =

Yes. Go to Settings > GPX Route Map and set "Units" to Imperial, and every map on the site switches to miles and feet. A single map can use different units from the rest: pick them in the block's Display panel, or add `units="imperial"` to the shortcode. Switching is instant and can be undone at any time, because the plugin always stores distances and elevations in metric and only converts them for display.

= Can I change how tall the maps are? =

Yes, for the whole site or for a single map. Go to Settings > GPX Route Map and set "Map height" to size every map that does not set its own. To change just one map, turn off "Use site default height" in the block's Display panel and pick a height, or add `height="600"` to the shortcode. Heights run from 200 to 1200 pixels.

= Can one map use different tiles from the rest? =

Yes. In the block's Map tiles panel, pick a tile provider instead of "Site default". Thunderforest styles appear there once an API key is saved under Settings > GPX Route Map. The shortcode takes `provider="opentopomap"` and the like.

= Visitors can zoom in past where my tiles stop =

The map stops at the last zoom level its tile provider has tiles for. The plugin knows where OpenStreetMap, OpenTopoMap and Thunderforest stop; for a tile server of your own that stops early, say so with the `gpxrm_tile_max_zoom` filter.

"Max zoom" under Settings > GPX Route Map is something else: how close a map opens when it frames a short route. A single map can differ: use the block's Max zoom slider, or add `maxzoom="15"` to the shortcode.

= Can maps be shorter on phones? =

Yes. Alongside the main height there are separate Tablet and Mobile heights, both site-wide and per map, and a shortcode accepts `height_tablet` and `height_mobile`. Tablet applies at 782px and below and Mobile at 480px and below, matching the widths WordPress itself uses. Leave one empty and the map follows the site's size for that screen, or the size above it when the site sets none - so a site-wide Mobile height applies to every map, including one given its own taller height. The elevation profile also gets shorter on phones so the map and the profile stay visible together.

= Can I hide the stats bar or the elevation profile? =

Yes, either one, for the whole site or for a single map. Go to Settings > GPX Route Map and set "Stats bar" or "Elevation profile" to Hidden to change every map at once. To change just one map, use the block's Display panel, or add `stats="false"` or `elevation="false"` to the shortcode. A map set to "Site default" follows the setting, so you can change your mind later in one place.

= Can I show only some of the figures in the stats bar? =

Yes. Under Settings > GPX Route Map, "Stats shown" has a checkbox for each of Distance, Elevation gain, Elevation loss, Max elevation, Min elevation and Waypoints, so you can keep just the ones you care about. Min elevation is off unless you tick it. A single map can differ: open the block's "Stats bar items" panel, turn off "Use site default" and tick what you want, or pass `fields="distance,gain"` to the shortcode. At least one figure always stays ticked; to remove the bar altogether use the separate "Stats bar" setting.

= Can visitors download the GPX file? =

Yes, if you switch the download button on. It is off by default. Go to Settings > GPX Route Map and set "Download button" to Shown, or turn it on for a single map from the block's Display panel or with `download="true"` on the shortcode. The button appears on the map next to the zoom and fullscreen controls. Files in your Media Library download with their own filename; a GPX hosted on another site opens instead of downloading, because browsers only honour the download hint for files served from the same site.

= Can I show contour lines and hill shading? =

Yes. Go to Settings > GPX Route Map and set the tile provider to OpenTopoMap. It needs no account. A single map can differ: pick it in the block's Map tiles panel, or add `provider="opentopomap"` to the shortcode. It is run by volunteers, with no guarantee that it stays available, and has less detail close in.

= Can I use Thunderforest's outdoor and cycling maps? =

Yes. Sign up at [thunderforest.com](https://www.thunderforest.com/pricing/), then go to Settings > GPX Route Map, set the tile provider to the Thunderforest style you want (Outdoors, OpenCycleMap, Landscape or Atlas) and paste your API key. With the key saved, a single map can use a different style: pick it in the block's Map tiles panel, or add `provider="thunderforest-cycle"` and the like to the shortcode. Their free plan covers 150,000 tiles a month, which is roughly 8,000 map views; busier sites need a paid plan.

Two things worth knowing. The key appears in your pages' source, because visitors' browsers fetch the tiles, and Thunderforest cannot lock a key to one domain, so watch your usage. And the map credits both Thunderforest and OpenStreetMap automatically, which their terms require and do not allow you to remove.

= Can I use my own map tiles? =

Not from the editor: the block offers the providers above. A developer can point maps at another tile server with the `gpxrm_tile_url` filter, and say where its tiles stop with `gpxrm_tile_max_zoom`. Tile addresses set on a map before 2.0 are no longer used; those maps follow the site's provider.

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

= 2.0.0 =
* Removed: custom tile URLs. This is a breaking change, and the reason this release is 2.0.0. The block's "Custom tile URL" field and the shortcode's `tile` attribute are gone, and a map that used one now shows the site's tile provider instead. The old address is not kept: it is dropped from a post the next time the post is saved, though older revisions still have it, so going back to 1.8 does not bring it back for posts edited in 2.0. To give a single map different tiles, choose OpenStreetMap, OpenTopoMap or a Thunderforest style in its Map tiles panel, or with the shortcode's new `provider` attribute. Developers who serve tiles from their own server can keep doing so with the `gpxrm_tile_url` filter, which applies to every map, or to every map using a given provider; a single map can no longer have a tile address of its own.
* New: choose the map tiles for each map. The block's Map tiles panel offers the site default, OpenStreetMap, OpenTopoMap and, once an API key is saved under Settings > GPX Route Map, each Thunderforest style. The shortcode takes the same choice, for example `provider="opentopomap"`.
* New: OpenTopoMap, a topographic map with contour lines and hill shading, with no account needed. It is run by volunteers, with no guarantee that it stays available.
* The site's tile provider is now a single list in which each Thunderforest style is its own entry, replacing the separate "Thunderforest style" setting. A site's current choice carries over.
* Maps no longer zoom in past the last level their tile provider has tiles for, where OpenStreetMap went blank and OpenTopoMap showed placeholder images. "Max zoom" is now described as what it is: how close a map opens when it frames a short route.
* New: the block editor shows the map itself, as visitors will see it, instead of a placeholder card, and updates it as you change the settings. Up to 8 maps are live at once; any others show the card until one of those is removed. The preview loads the map tiles like any visitor would, so editing posts with maps now counts toward a Thunderforest plan's tiles.
* New: an optional Min elevation figure for the stats bar, off unless you tick it. Maps added before 2.0.0 show a dash for it until the map has loaded, and updating the post does not change that; to store it with such a map, tick Min elevation in that map's Stats bar items panel.
* Turning off "Use site default" in a map's Stats bar items panel now starts from the figures the site shows, rather than from all of them.
* Fixed: the editor now works out a map's figures from its selected Media Library file, as the front end does, and shows the map, rather than the file picker, for a block that only stores a media file.
* Fixed: the elevation profile now draws on very long tracks, where it stayed blank: from about 125,000 points in Chrome and Edge, or about 500,000 in Firefox and Safari.
* Fixed: a GPX file with points no map can place, such as a latitude beyond 90°, no longer fails to load. Those points are left out; a file with none left shows "No track or route points found in GPX file.", and one whose track points are all unusable shows its route instead. A post saved before 2.0.0 with such a file gets corrected figures when it is opened in the editor, so the editor shows it as changed; update the post to keep them.
* The Spanish translation now matches the reviewed one on translate.wordpress.org, with the strings new in 2.0.0 translated here until they are reviewed there, and also ships as a PHP translation file, which WordPress loads faster.
* Going back to 1.8: once Settings > GPX Route Map has been saved in 2.0, for any change, a site on Thunderforest shows OpenStreetMap until Thunderforest is chosen again there.
* For developers: `gpxrm_tile_url` now runs for every map and gets the provider that map uses as a second argument, so a callback that ignores it replaces every map's tiles. New `gpxrm_tile_max_zoom` filter. `Renderer::tile_provider()` returns provider ids such as `thunderforest-outdoors`, and `Renderer::STAT_FIELDS` includes the opt-in `min` (the shipped default list is `Renderer::DEFAULT_STAT_FIELDS`). Removed: `Renderer::sanitize_tile_url()`, `Plugin::sanitize_thunderforest_style()` and `Plugin::render_thunderforest_style_field()`. The `gpxrm_thunderforest_style` option is only read to carry a 1.x choice over.


= 1.8.0 =
* Now requires WordPress 6.8 or newer. WordPress loads plugin translations by itself from 6.8 on, so the plugin no longer does it. Sites on 6.6 or 6.7 keep the version they already have.
* New: set the maximum zoom once for the whole site under Settings > GPX Route Map, instead of repeating it on every map.
* Fixed: on themes that style buttons, such as Vantage, the waypoint popup's close button grew into a large box that covered the waypoint's name. The map's zoom, fullscreen and download buttons no longer pick up the theme's button styling either.
* The labels on the start and end pins can now be translated. They were always shown in English.
* The map's own controls can now be translated too: the zoom, compass, fullscreen, location and credits buttons, the popup close button, the scale bar units, and the name screen readers give the map. All of it was always in English.
* Map markers now work from the keyboard: Enter or Space opens a marker's popup, Escape closes it, and focus returns to the marker afterwards. Before, the keys did nothing and Space scrolled the page away. Markers also show a focus ring on every theme and have a name screen readers announce.
* The editor now ignores a GPX file URL that is not http or https, instead of turning it into a link.
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

= 2.0.0 =
Breaking change: custom tile URLs are removed, which is why this is 2.0.0. Maps and shortcodes that used one now show the site's tile provider; choose OpenStreetMap, OpenTopoMap or a Thunderforest style for a single map instead. Also adds a live editor preview and an optional Min elevation.


= 1.8.0 =
Needs WordPress 6.8 or newer. Adds a site-wide maximum zoom setting, fixes waypoint popups that some themes made unreadable, and warns in the editor about a tile URL that will not work. Sites on 6.6 or 6.7 are not offered this update and keep the version they have.


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
