# GPX Route Map

A WordPress plugin that renders a GPX track as an interactive OpenStreetMap map with waypoints, distance and an elevation profile, as a Gutenberg block or a shortcode.

Built with [MapLibre GL JS](https://maplibre.org/) + raster tiles (OpenStreetMap, OpenTopoMap or Thunderforest) and a hand-rolled `<canvas>` elevation profile.

## Features

-   **Block + shortcode**: `gpx-route-map/map` block and `[gpx_route_map]`.
-   **Interactive map**: track line, start/end markers, waypoint markers with popups, navigation/scale/fullscreen/geolocate controls.
-   **Waypoint posts** (experimental, opt-in, Settings > GPX Route Map > General): a `gpxrm_waypoint` post type that gives a waypoint its own description, popup photo and marker (photo thumbnail or a Dashicon in chosen colors). A block links waypoints to posts by their position in the file (`waypointPosts`), the shortcode with `waypoints="1:12"`, or a Media Library GPX file itself with `<gpxrm:waypoint>` in a waypoint's `<extensions>`; the block's link wins.
-   **Stats bar**: distance, elevation gain/loss, max and min elevation (min is opt-in), waypoint count. Computed in the browser from the GPX file; a block also stores them, so its bar shows the figures without JavaScript, while a shortcode's fills in once the map loads.
-   **Elevation profile**: scrub with mouse/touch; the position syncs onto the map, and clicking the track highlights the profile.
-   **Lazy loading**: MapLibre loads only when a map scrolls into view; multiple maps per page are supported.
-   **Configurable tiles**: OpenStreetMap, OpenTopoMap or a Thunderforest style, for the whole site or a single map, each credited and zoomed only as far as it has tiles; busy sites can point maps at their own tile server with the `gpxrm_tile_url` filter, as the OSM tile usage policy asks.

## Development

This project uses [pnpm](https://pnpm.io/) and [Vite](https://vite.dev/).

```bash
pnpm install
pnpm run start      # watch/dev build
pnpm run build      # production build → build/
pnpm run typecheck  # tsc --noEmit
pnpm run plugin-zip # package a distributable zip
```

`register_block_type()` reads `build/block.json`, so the block and its assets only register after a build. If WordPress shows no block, run `pnpm run build`.

### Testing

JavaScript unit tests use [Vitest](https://vitest.dev/).

```bash
pnpm run test:js        # run once
pnpm run test:js:watch  # watch mode
```

PHP unit tests use [Pest](https://pestphp.com/). The suite runs standalone, without WordPress: `tests/bootstrap.php` stubs the WordPress functions the tested code calls.

```bash
composer install
composer test      # runs Pest
```

A local WordPress for manual/integration testing is provided via [`@wordpress/env`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/):

```bash
pnpm run env:start   # http://localhost:8888
pnpm run env:stop
```

### Layout

```text
gpx-route-map.php                 Plugin bootstrap
bin/build.mjs                     Vite build orchestrator (JS, SCSS, RTL, asset.php)
includes/
  class-plugin.php                Hooks: block, shortcode, GPX upload MIME
  class-renderer.php              Shared HTML for block + shortcode, SSR stats, asset enqueue
  class-waypoints.php             Waypoint post type: its switch, registration and shared constants
  class-waypoint-edit-screen.php  A Waypoint post's settings box, saving and list columns
  class-waypoint-values.php       Checks a Waypoint post's marker, icon and colours
  class-waypoint-description.php  The markup a description keeps, and how maps show it
  class-waypoint-links.php        Waypoint links from a block or a shortcode
  class-gpx-file-links.php        A Media Library GPX file's own links, cached
  class-gpx-link-reader.php       Reads those links from the file, as a stream
  class-waypoint-map-markers.php  What one map is told about its linked waypoints
  class-waypoint-posts.php        The published Waypoint posts a map links
  class-waypoint-post.php         One Waypoint post, as a map shows it
  class-waypoint-marker.php       A post's settings, as the map's marker and popup
  class-dashicons.php             The icons a waypoint can use
src/
  block.json                      Block metadata
  index.tsx / edit.tsx            Editor
  render.php                      Server render → Renderer
  view.ts                         Front-end entry (lazy init)
  view/
    types.ts                      Shared domain types
    map-core.ts                   GPX parse, MapLibre markers
    elevation.ts                  Canvas elevation profile
    stats.ts                      Route stats
    map-instance.ts               Assembles one map
    waypoint-posts.ts             Reads the map's Waypoint post data
    description.ts                A Waypoint post's description, rebuilt for its popup
    safe-url.ts                   The link addresses a map may open
  editor/
    waypoint-links.tsx            The block's Waypoints panel
    media-url.ts                  The block's Media Library file address
  style.scss / editor.scss        Styles
```

## License

GPL-2.0-or-later. Bundles MapLibre GL JS (BSD-3-Clause).
