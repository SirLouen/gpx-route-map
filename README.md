# GPX Route Map

A WordPress plugin that renders a GPX track as an interactive OpenStreetMap map with waypoints, distance and an elevation profile, as a Gutenberg block or a shortcode.

Built with [MapLibre GL JS](https://maplibre.org/) + raster OpenStreetMap tiles and a hand-rolled `<canvas>` elevation profile.

## Features

-   **Block + shortcode**: `gpx-route-map/map` block and `[gpx_route_map]`.
-   **Interactive map**: track line, start/end markers, waypoint markers with popups, navigation/scale/fullscreen/geolocate controls.
-   **Stats bar**: distance, elevation gain/loss, max elevation, waypoint count. Computed server-side for local files (SSR) and in the browser.
-   **Elevation profile**: scrub with mouse/touch; the position syncs onto the map, and clicking the track highlights the profile.
-   **Lazy loading**: MapLibre loads only when a map scrolls into view; multiple maps per page are supported.
-   **Configurable tiles**: respects the OSM tile usage policy via a custom tile URL / `gpxrm_tile_url` filter.

## Development

This project uses [pnpm](https://pnpm.io/) and [Vite](https://vite.dev/) (see `bin/build.mjs`; `@wordpress/scripts` is retained for linting, formatting and packaging).

```bash
pnpm install
pnpm run start      # watch/dev build
pnpm run build      # production build → build/
pnpm run plugin-zip # package a distributable zip
```

`register_block_type()` reads `build/block.json`, so the block and its assets only register after a build. If WordPress shows no block, run `pnpm run build`.

### Testing

PHP unit tests use [Pest](https://pestphp.com/). `GpxStats` is dependency-free, so the suite runs standalone (no WordPress bootstrap):

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
gpx-route-map.php          Plugin bootstrap
bin/build.mjs              Vite build orchestrator (JS, SCSS, RTL, asset.php)
includes/
  class-plugin.php         Hooks: block, shortcode, GPX upload MIME
  class-renderer.php       Shared HTML for block + shortcode, SSR stats, asset enqueue
  class-gpxstats.php      Pure distance/elevation algorithm (ported, dependency-free)
src/
  block.json               Block metadata
  index.js / edit.js       Editor
  render.php               Server render → Renderer
  view.js                  Front-end entry (lazy init)
  view/
    map-core.js            GPX parse, MapLibre style/markers
    elevation.js           Canvas elevation profile
    map-instance.js        Assembles one map
  style.scss / editor.scss Styles
```

## License

GPL-2.0-or-later. Bundles MapLibre GL JS (BSD-3-Clause).
