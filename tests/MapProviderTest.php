<?php
/**
 * Which tiles a single map shows: a provider of its own, or the site's.
 *
 * A map can choose any provider the site can use. One it cannot - a
 * Thunderforest style without the key, a value from elsewhere - follows the
 * site instead, and the site's own provider falls back to OpenStreetMap when
 * it cannot be used either. Tile addresses typed into a map, as 1.x allowed,
 * are no longer read.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

use Gpxrm\Plugin;
use Gpxrm\Renderer;

const GPXRM_PROVIDER_TEST_KEY = '0123456789abcdef0123456789abcdef';

beforeEach(
	function () {
		$GLOBALS['gpxrm_test_options'] = array( 'gpxrm_tile_provider' => 'opentopomap' );
		$GLOBALS['gpxrm_test_filters'] = array();
	}
);

/**
 * The tile template a map renders with.
 *
 * @param array<string, mixed> $atts Block attributes.
 * @return string
 */
function gpxrm_map_tiles( array $atts ): string {
	$html = Renderer::render( $atts + array( 'gpxUrl' => 'https://example.test/route.gpx' ) );
	preg_match( '/data-gpxrm-tile-url="([^"]*)"/', $html, $m );

	return html_entity_decode( $m[1] ?? '' );
}

test(
	'a map follows the site unless it chooses a provider it can use',
	function ( $provider, $expected ) {
		expect( gpxrm_map_tiles( array( 'provider' => $provider ) ) )->toBe( $expected );
	}
)->with(
	array(
		'nothing chosen'                => array( '', Renderer::OPENTOPOMAP_TILE_URL ),
		'"default"'                     => array( 'default', Renderer::OPENTOPOMAP_TILE_URL ),
		'OpenStreetMap'                 => array( 'osm', Renderer::OSM_TILE_URL ),
		'OpenTopoMap'                   => array( 'opentopomap', Renderer::OPENTOPOMAP_TILE_URL ),
		'a Thunderforest style, no key' => array( 'thunderforest-cycle', Renderer::OPENTOPOMAP_TILE_URL ),
		'an unknown provider'           => array( 'mapbox', Renderer::OPENTOPOMAP_TILE_URL ),
		'the 1.x value'                 => array( 'thunderforest', Renderer::OPENTOPOMAP_TILE_URL ),
		'not a string'                  => array( 42, Renderer::OPENTOPOMAP_TILE_URL ),
	)
);

test(
	'a map can use a Thunderforest style once the key is saved',
	function () {
		$GLOBALS['gpxrm_test_options']['gpxrm_thunderforest_key'] = GPXRM_PROVIDER_TEST_KEY;

		expect( gpxrm_map_tiles( array( 'provider' => 'thunderforest-cycle' ) ) )
			->toStartWith( 'https://api.thunderforest.com/cycle/' );
	}
);

test(
	'when neither the map nor the site can use their provider, OpenStreetMap',
	function () {
		$GLOBALS['gpxrm_test_options']['gpxrm_tile_provider'] = 'thunderforest-outdoors';

		expect( gpxrm_map_tiles( array( 'provider' => 'thunderforest-cycle' ) ) )->toBe( Renderer::OSM_TILE_URL );
	}
);

test(
	'a tile address stored by 1.x is no longer read',
	function () {
		expect( gpxrm_map_tiles( array( 'tileUrl' => 'https://tiles.example.com/{z}/{x}/{y}.png' ) ) )
			->toBe( Renderer::OPENTOPOMAP_TILE_URL );
	}
);

test(
	'the shortcode chooses a provider, and no longer takes a tile address',
	function () {
		$plugin = new Plugin();
		$tiles  = static function ( string $html ): string {
			preg_match( '/data-gpxrm-tile-url="([^"]*)"/', $html, $m );
			return html_entity_decode( $m[1] ?? '' );
		};

		expect( Plugin::shortcode_defaults() )->not->toHaveKey( 'tile' );
		expect(
			$tiles(
				$plugin->render_shortcode(
					array(
						'gpx'      => 'https://example.test/route.gpx',
						'provider' => 'osm',
					)
				)
			)
		)->toBe( Renderer::OSM_TILE_URL );
		expect(
			$tiles(
				$plugin->render_shortcode(
					array(
						'gpx'  => 'https://example.test/route.gpx',
						'tile' => 'https://tiles.example.com/{z}/{x}/{y}.png',
					)
				)
			)
		)->toBe( Renderer::OPENTOPOMAP_TILE_URL );
	}
);

// The editor offers what a map can use and names what "Site default" is,
// without ever seeing the key or a tile address.
test(
	'the editor is told which providers a map can use, and the site\'s',
	function ( bool $key, string $site, array $usable ) {
		if ( $key ) {
			$GLOBALS['gpxrm_test_options']['gpxrm_thunderforest_key'] = GPXRM_PROVIDER_TEST_KEY;
		}
		$GLOBALS['gpxrm_test_options']['gpxrm_tile_provider'] = 'thunderforest-landscape';

		$defaults  = Plugin::editor_defaults();
		$providers = $defaults['tileProviders'];

		expect( array_column( $providers, 'id' ) )->toBe( array_keys( Renderer::tile_providers() ) );
		expect( array_keys( $providers[0] ) )->toBe( array( 'id', 'label', 'usable' ) );
		expect( array_column( array_filter( $providers, fn( $p ) => $p['usable'] ), 'id' ) )->toBe( $usable );
		expect( $defaults['tileProvider'] )->toBe( $site );
		expect( wp_json_encode( $defaults ) )->not->toContain( GPXRM_PROVIDER_TEST_KEY );
	}
)->with(
	array(
		'no key'   => array( false, 'osm', array( 'osm', 'opentopomap' ) ),
		'with key' => array( true, 'thunderforest-landscape', array_keys( Renderer::tile_providers() ) ),
	)
);

// A 1.x callback that ignores it replaces every map's tiles, whatever was
// chosen for it.
test(
	'the gpxrm_tile_url filter is told the provider the map itself uses',
	function () {
		$GLOBALS['gpxrm_test_options']['gpxrm_tile_provider'] = 'osm';
		$seen = array();
		$GLOBALS['gpxrm_test_filters']['gpxrm_tile_url'] = array(
			static function ( $url, $provider ) use ( &$seen ) {
				$seen[] = $provider;
				return $url;
			},
		);

		gpxrm_map_tiles( array( 'provider' => 'opentopomap' ) );

		expect( $seen )->toBe( array( 'opentopomap' ) );
	}
);

// As the shortcode's other values are, so the name as Settings shows it works.
test(
	'the shortcode reads its provider in any case and with spaces around',
	function ( $given, $expected ) {
		$GLOBALS['gpxrm_test_options']['gpxrm_tile_provider'] = 'osm';
		$html = ( new Plugin() )->render_shortcode(
			array(
				'gpx'      => 'https://example.test/route.gpx',
				'provider' => $given,
			)
		);
		preg_match( '/data-gpxrm-tile-url="([^"]*)"/', $html, $m );

		expect( html_entity_decode( $m[1] ?? '' ) )->toBe( $expected );
	}
)->with(
	array(
		'capitals' => array( 'OpenTopoMap', Renderer::OPENTOPOMAP_TILE_URL ),
		'spaces'   => array( ' opentopomap ', Renderer::OPENTOPOMAP_TILE_URL ),
	)
);

// React prints the labels as text, so an entity a translation uses would show
// as typed: "&nbsp;" instead of a space.
test(
	'the editor gets the provider names as text, entities decoded',
	function () {
		$GLOBALS['gpxrm_test_filters']['gettext'] = array(
			static function ( $translation, $text ) {
				return 'Thunderforest: %s' === $text ? 'Thunderforest&nbsp;: %s' : $translation;
			},
		);

		$labels = array_column( Plugin::editor_defaults()['tileProviders'], 'label', 'id' );

		expect( $labels['thunderforest-outdoors'] )->toBe( "Thunderforest\u{a0}: Outdoors (hiking)" );
	}
);

test(
	'the editor marks as usable exactly what maps can use',
	function ( bool $key ) {
		if ( $key ) {
			$GLOBALS['gpxrm_test_options']['gpxrm_thunderforest_key'] = GPXRM_PROVIDER_TEST_KEY;
		}

		foreach ( Plugin::editor_defaults()['tileProviders'] as $provider ) {
			expect( $provider['usable'] )->toBe( Renderer::provider_usable( $provider['id'] ) );
		}
	}
)->with(
	array(
		'no key'   => array( false ),
		'with key' => array( true ),
	)
);
