<?php
/**
 * Where the map images come from: the providers on offer, how the site's
 * choice turns into a tile address, the credit each provider requires, and
 * the last zoom level each one has tiles for.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

use Gpxrm\Plugin;
use Gpxrm\Renderer;

const GPXRM_TEST_KEY = '0123456789abcdef0123456789abcdef';

beforeEach(
	function () {
		$GLOBALS['gpxrm_test_options']         = array();
		$GLOBALS['gpxrm_test_filters']         = array();
		$GLOBALS['gpxrm_test_settings_errors'] = array();
	}
);

/**
 * Store the site's provider and, optionally, a Thunderforest key.
 *
 * @param string $provider Stored provider.
 * @param bool   $key      Whether a key is saved.
 * @return void
 */
function gpxrm_use_provider( string $provider, bool $key = false ): void {
	$GLOBALS['gpxrm_test_options']['gpxrm_tile_provider'] = $provider;
	if ( $key ) {
		$GLOBALS['gpxrm_test_options']['gpxrm_thunderforest_key'] = GPXRM_TEST_KEY;
	}
}

test(
	'OpenStreetMap is the default and needs no configuration',
	function () {
		expect( Renderer::tile_provider() )->toBe( 'osm' );
		expect( Renderer::default_tile_url() )->toBe( Renderer::OSM_TILE_URL );
		expect( Renderer::default_attribution() )->toBe( Renderer::OSM_ATTRIBUTION );
	}
);

test(
	'every provider is offered, Thunderforest once per style',
	function () {
		$providers = Renderer::tile_providers();

		expect( array_keys( $providers ) )->toBe(
			array( 'osm', 'opentopomap', 'thunderforest-outdoors', 'thunderforest-cycle', 'thunderforest-landscape', 'thunderforest-atlas' )
		);
		expect( $providers['osm'] )->toBe(
			array(
				'label'        => 'OpenStreetMap',
				'key_required' => false,
			)
		);
		expect( $providers['opentopomap']['key_required'] )->toBeFalse();
		expect( $providers['thunderforest-cycle'] )->toBe(
			array(
				'label'        => 'Thunderforest: OpenCycleMap (cycling)',
				'key_required' => true,
			)
		);
	}
);

test(
	'OpenTopoMap needs no key',
	function () {
		gpxrm_use_provider( 'opentopomap' );

		expect( Renderer::default_tile_url() )->toBe( 'https://tile.opentopomap.org/{z}/{x}/{y}.png' );
		expect( Renderer::default_attribution() )->toBe( Renderer::OPENTOPOMAP_ATTRIBUTION );
	}
);

test(
	'a Thunderforest style without a key keeps the map working on OSM',
	function () {
		gpxrm_use_provider( 'thunderforest-cycle' );

		expect( Renderer::provider_usable( 'thunderforest-cycle' ) )->toBeFalse();
		expect( Renderer::default_tile_url() )->toBe( Renderer::OSM_TILE_URL );
		expect( Renderer::default_attribution() )->toBe( Renderer::OSM_ATTRIBUTION );
	}
);

test(
	'a saved key builds the documented tile address for the chosen style',
	function () {
		gpxrm_use_provider( 'thunderforest-cycle', true );

		$url = Renderer::default_tile_url();

		expect( Renderer::provider_usable( 'thunderforest-cycle' ) )->toBeTrue();
		expect( $url )->toStartWith( 'https://api.thunderforest.com/cycle/' );
		expect( $url )->toContain( '{z}/{x}/{y}{ratio}.png' );
		expect( $url )->toEndWith( '?apikey=' . GPXRM_TEST_KEY );
		expect( Renderer::default_attribution() )->toBe( Renderer::THUNDERFOREST_ATTRIBUTION );
	}
);

// 1.x stored "thunderforest" and the style in a setting of its own.
test(
	'a site set up before 2.0 keeps its Thunderforest style',
	function ( $style, $expected ) {
		gpxrm_use_provider( 'thunderforest' );
		if ( null !== $style ) {
			$GLOBALS['gpxrm_test_options']['gpxrm_thunderforest_style'] = $style;
		}

		expect( Renderer::tile_provider() )->toBe( $expected );
	}
)->with(
	array(
		'a style'        => array( 'landscape', 'thunderforest-landscape' ),
		'no style saved' => array( null, 'thunderforest-outdoors' ),
		'an odd style'   => array( 'opencyclemap', 'thunderforest-outdoors' ),
	)
);

// A 1.x script may set the provider before the style, so the style is only
// looked up when the setting is read.
test(
	'the 1.x Thunderforest value is kept as it is',
	function () {
		expect( ( new Plugin() )->sanitize_tile_provider( 'thunderforest' ) )->toBe( 'thunderforest' );

		gpxrm_use_provider( 'thunderforest' );
		$GLOBALS['gpxrm_test_options']['gpxrm_thunderforest_style'] = 'atlas';
		expect( Renderer::tile_provider() )->toBe( 'thunderforest-atlas' );
	}
);

test(
	'the provider setting only accepts what the plugin offers',
	function () {
		$plugin = new Plugin();

		foreach ( array_keys( Renderer::tile_providers() ) as $id ) {
			expect( $plugin->sanitize_tile_provider( $id ) )->toBe( $id );
		}
		expect( $plugin->sanitize_tile_provider( 'mapbox' ) )->toBe( 'osm' );
		expect( $plugin->sanitize_tile_provider( array() ) )->toBe( 'osm' );
		expect( $plugin->sanitize_tile_provider( null ) )->toBe( 'osm' );

		gpxrm_use_provider( 'nonsense' );
		expect( Renderer::tile_provider() )->toBe( 'osm' );
	}
);

test(
	'the gpxrm_tile_url filter is told whose tiles it filters',
	function () {
		$seen = array();
		$GLOBALS['gpxrm_test_filters']['gpxrm_tile_url'] = array(
			static function ( $url, $provider ) use ( &$seen ) {
				$seen[] = array( $url, $provider );
				return $url;
			},
		);

		gpxrm_use_provider( 'thunderforest-atlas', true );
		Renderer::default_tile_url();
		// Without the key the tiles are OSM's, so the filter is told so.
		unset( $GLOBALS['gpxrm_test_options']['gpxrm_thunderforest_key'] );
		Renderer::default_tile_url();

		expect( $seen[0][1] )->toBe( 'thunderforest-atlas' );
		expect( $seen[1] )->toBe( array( Renderer::OSM_TILE_URL, 'osm' ) );
	}
);

test(
	'the credit follows the tiles in use, not the site setting',
	function ( $url, $expected ) {
		expect( Renderer::attribution_for( $url ) )->toBe( $expected );
	}
)->with(
	array(
		'thunderforest'       => array( 'https://api.thunderforest.com/outdoors/{z}/{x}/{y}.png?apikey=x', Renderer::THUNDERFOREST_ATTRIBUTION ),
		'legacy host'         => array( 'https://tile.thunderforest.com/cycle/{z}/{x}/{y}.png?apikey=x', Renderer::THUNDERFOREST_ATTRIBUTION ),
		'capitals'            => array( 'https://API.Thunderforest.COM/cycle/{z}/{x}/{y}.png?apikey=x', Renderer::THUNDERFOREST_ATTRIBUTION ),
		'opentopomap'         => array( 'https://tile.opentopomap.org/{z}/{x}/{y}.png', Renderer::OPENTOPOMAP_ATTRIBUTION ),
		'opentopomap shard'   => array( 'https://B.Tile.OpenTopoMap.org/{z}/{x}/{y}.png', Renderer::OPENTOPOMAP_ATTRIBUTION ),
		'openstreetmap'       => array( Renderer::OSM_TILE_URL, Renderer::OSM_ATTRIBUTION ),
		'other host'          => array( 'https://tiles.example.com/{z}/{x}/{y}.png', Renderer::OSM_ATTRIBUTION ),
		'lookalike'           => array( 'https://thunderforest.com.evil.test/{z}/{x}/{y}.png', Renderer::OSM_ATTRIBUTION ),
		'opentopomap inside'  => array( 'https://opentopomap.org.evil.test/{z}/{x}/{y}.png', Renderer::OSM_ATTRIBUTION ),
		'spaces around'       => array( '  https://tile.opentopomap.org/{z}/{x}/{y}.png ', Renderer::OPENTOPOMAP_ATTRIBUTION ),
	)
);

test(
	'a gpxrm_tile_attribution filter that returns no text keeps the credit',
	function () {
		$GLOBALS['gpxrm_test_filters']['gpxrm_tile_attribution'] = array(
			static function () {
				return null;
			},
		);

		expect( Renderer::attribution_for( Renderer::OSM_TILE_URL ) )->toBe( Renderer::OSM_ATTRIBUTION );
	}
);

// Asking for tiles beyond these gets errors or placeholder images. A server
// the plugin does not know has no known last level (0), so its maps zoom as
// far as they always did.
test(
	'each provider has tiles up to its own last zoom level',
	function ( $url, $expected ) {
		expect( Renderer::tile_max_zoom_for( $url ) )->toBe( $expected );
	}
)->with(
	array(
		'openstreetmap'         => array( Renderer::OSM_TILE_URL, 19 ),
		'its osm.org alias'     => array( 'https://tile.osm.org/{z}/{x}/{y}.png', 19 ),
		'a trailing dot'        => array( 'https://tile.openstreetmap.org./{z}/{x}/{y}.png', 19 ),
		'opentopomap'           => array( 'https://tile.opentopomap.org/{z}/{x}/{y}.png', 17 ),
		'opentopomap, capitals' => array( 'https://Tile.OpenTopoMap.org/{z}/{x}/{y}.png', 17 ),
		'thunderforest'         => array( 'https://api.thunderforest.com/atlas/{z}/{x}/{y}{ratio}.png?apikey=x', 22 ),
		'other host'            => array( 'https://tiles.example.com/{z}/{x}/{y}.png', 0 ),
		'lookalike'             => array( 'https://evilopenstreetmap.org/{z}/{x}/{y}.png', 0 ),
		'no address'            => array( '', 0 ),
	)
);

test(
	'a tile server of one\'s own can say where its tiles stop',
	function ( $returned, $expected ) {
		$GLOBALS['gpxrm_test_filters']['gpxrm_tile_max_zoom'] = array(
			static function () use ( $returned ) {
				return $returned;
			},
		);

		expect( Renderer::tile_max_zoom_for( 'https://tiles.example.com/{z}/{x}/{y}.png' ) )->toBe( $expected );
	}
)->with(
	array(
		'a level'           => array( 18, 18 ),
		'too far'           => array( 30, 22 ),
		'none known'        => array( 0, 0 ),
		'below none'        => array( -3, 0 ),
		'not a number'      => array( 'deep', 0 ),
		'not a real number' => array( INF, 0 ),
	)
);

// A map's own tiles, not the site's provider, decide.
test(
	'a map tells the view where its tiles stop, when that is known',
	function ( $provider, $filtered, $expected, $credit ) {
		gpxrm_use_provider( 'opentopomap' );
		if ( null !== $filtered ) {
			$GLOBALS['gpxrm_test_filters']['gpxrm_tile_url'] = array(
				static function () use ( $filtered ) {
					return $filtered;
				},
			);
		}

		$html = Renderer::render(
			array(
				'gpxUrl'   => 'https://example.test/route.gpx',
				'provider' => $provider,
			)
		);
		preg_match( '/data-gpxrm-tile-max-zoom="(\d+)"/', $html, $level );
		preg_match( '/data-gpxrm-attribution="([^"]*)"/', $html, $attribution );

		expect( $level[1] ?? null )->toBe( $expected );
		expect( html_entity_decode( $attribution[1] ?? '' ) )->toBe( html_entity_decode( $credit ) );
	}
)->with(
	array(
		'the site provider'      => array( '', null, '17', Renderer::OPENTOPOMAP_ATTRIBUTION ),
		'a map on OpenStreetMap' => array( 'osm', null, '19', Renderer::OSM_ATTRIBUTION ),
		'a server of one\'s own' => array( '', 'https://tiles.example.com/{z}/{x}/{y}.png', null, Renderer::OSM_ATTRIBUTION ),
	)
);

test(
	'the settings page offers every provider and the current one is chosen',
	function () {
		gpxrm_use_provider( 'opentopomap' );

		ob_start();
		( new Plugin() )->render_tile_provider_field();
		$html = (string) ob_get_clean();

		preg_match_all( '/<option value="([a-z-]+)"/', $html, $offered );
		expect( $offered[1] )->toBe( array_keys( Renderer::tile_providers() ) );
		expect( $html )->toContain( "value=\"opentopomap\" selected='selected'" );
		// Word for word as in 1.x, so its translations still apply.
		expect( $html )->toContain( 'OpenStreetMap works with no setup. Thunderforest offers outdoor and cycling styles, and needs an account.' );
	}
);

// Without its key a Thunderforest style quietly shows OpenStreetMap.
test(
	'the settings page says when the chosen style still needs its key',
	function ( bool $key, bool $warned ) {
		gpxrm_use_provider( 'thunderforest-outdoors', $key );

		ob_start();
		( new Plugin() )->render_tile_provider_field();
		$html = (string) ob_get_clean();

		expect( str_contains( $html, 'No Thunderforest API key is saved' ) )->toBe( $warned );
	}
)->with(
	array(
		'no key'   => array( false, true ),
		'with key' => array( true, false ),
	)
);

test(
	'a valid key is stored and an empty one clears it',
	function () {
		$plugin = new Plugin();

		expect( $plugin->sanitize_thunderforest_key( GPXRM_TEST_KEY ) )->toBe( GPXRM_TEST_KEY );
		expect( $plugin->sanitize_thunderforest_key( '  ' . GPXRM_TEST_KEY . ' ' ) )->toBe( GPXRM_TEST_KEY );
		expect( $plugin->sanitize_thunderforest_key( '' ) )->toBe( '' );
		expect( $GLOBALS['gpxrm_test_settings_errors'] )->toBe( array() );
	}
);

test(
	'a malformed key is rejected with a notice rather than silently stored',
	function ( $bad ) {
		$GLOBALS['gpxrm_test_options']['gpxrm_thunderforest_key'] = GPXRM_TEST_KEY;
		$plugin = new Plugin();

		expect( $plugin->sanitize_thunderforest_key( $bad ) )->toBe( GPXRM_TEST_KEY );
		expect( $GLOBALS['gpxrm_test_settings_errors'] )->toHaveCount( 1 );
	}
)->with(
	array(
		'a whole URL' => 'https://api.thunderforest.com/outdoors/{z}/{x}/{y}.png?apikey=abc',
		'punctuation' => 'abcd-1234-efgh-5678',
		'too short'   => 'abc123',
		'markup'      => '<script>alert(1)</script>',
	)
);
