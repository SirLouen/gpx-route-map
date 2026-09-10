<?php
/**
 * Tests for the map tile provider.
 *
 * The attribution rules matter as much as the URL here: Thunderforest's terms
 * require credit to both Thunderforest and OpenStreetMap and forbid removing
 * it, so the plugin derives it from the tiles actually in use.
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
		$GLOBALS['gpxrm_test_settings_errors'] = array();
	}
);

test(
	'OpenStreetMap is the default and needs no configuration',
	function () {
		expect( Renderer::tile_provider() )->toBe( 'osm' );
		expect( Renderer::default_tile_url() )->toBe( Renderer::OSM_TILE_URL );
		expect( Renderer::default_attribution() )->toBe( Renderer::OSM_ATTRIBUTION );
	}
);

test(
	'choosing Thunderforest without a key keeps the map working on OSM',
	function () {
		$GLOBALS['gpxrm_test_options']['gpxrm_tile_provider'] = 'thunderforest';

		expect( Renderer::default_tile_url() )->toBe( Renderer::OSM_TILE_URL );
		expect( Renderer::default_attribution() )->toBe( Renderer::OSM_ATTRIBUTION );
	}
);

test(
	'a configured Thunderforest key builds the documented tile URL',
	function () {
		$GLOBALS['gpxrm_test_options']['gpxrm_tile_provider']     = 'thunderforest';
		$GLOBALS['gpxrm_test_options']['gpxrm_thunderforest_key'] = GPXRM_TEST_KEY;

		$url = Renderer::default_tile_url();

		// The api. host is the documented one; tile. is legacy.
		expect( $url )->toStartWith( 'https://api.thunderforest.com/outdoors/' );
		// MapLibre substitutes {ratio}; Leaflet's {r} would be sent literally.
		expect( $url )->toContain( '{z}/{x}/{y}{ratio}.png' );
		expect( $url )->not->toContain( '{r}.png' );
		expect( $url )->toEndWith( '?apikey=' . GPXRM_TEST_KEY );
	}
);

test(
	'the style is part of the path and falls back when unknown',
	function () {
		$GLOBALS['gpxrm_test_options']['gpxrm_tile_provider']     = 'thunderforest';
		$GLOBALS['gpxrm_test_options']['gpxrm_thunderforest_key'] = GPXRM_TEST_KEY;

		$GLOBALS['gpxrm_test_options']['gpxrm_thunderforest_style'] = 'cycle';
		expect( Renderer::default_tile_url() )->toContain( '/cycle/' );

		// "opencyclemap" is the name of their web page, not a tile style.
		$GLOBALS['gpxrm_test_options']['gpxrm_thunderforest_style'] = 'opencyclemap';
		expect( Renderer::thunderforest_style() )->toBe( 'outdoors' );
	}
);

test(
	'attribution follows the tiles in use, not the site setting',
	function ( $url, $expected ) {
		expect( Renderer::attribution_for( $url ) )->toBe( $expected );
	}
)->with(
	array(
		'thunderforest' => array( 'https://api.thunderforest.com/outdoors/{z}/{x}/{y}.png?apikey=x', Renderer::THUNDERFOREST_ATTRIBUTION ),
		'legacy host'   => array( 'https://tile.thunderforest.com/cycle/{z}/{x}/{y}.png?apikey=x', Renderer::THUNDERFOREST_ATTRIBUTION ),
		'openstreetmap' => array( Renderer::OSM_TILE_URL, Renderer::OSM_ATTRIBUTION ),
		'other host'    => array( 'https://tiles.example.com/{z}/{x}/{y}.png', Renderer::OSM_ATTRIBUTION ),
		// Must not match a lookalike domain.
		'lookalike'     => array( 'https://thunderforest.com.evil.test/{z}/{x}/{y}.png', Renderer::OSM_ATTRIBUTION ),
	)
);

test(
	'the provider setting only accepts what the plugin supports',
	function () {
		$plugin = new Plugin();

		expect( $plugin->sanitize_tile_provider( 'thunderforest' ) )->toBe( 'thunderforest' );
		expect( $plugin->sanitize_tile_provider( 'osm' ) )->toBe( 'osm' );
		expect( $plugin->sanitize_tile_provider( 'mapbox' ) )->toBe( 'osm' );
		expect( $plugin->sanitize_tile_provider( array() ) )->toBe( 'osm' );
	}
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

		// The previous key survives, so one typo does not break every map.
		expect( $plugin->sanitize_thunderforest_key( $bad ) )->toBe( GPXRM_TEST_KEY );
		expect( $GLOBALS['gpxrm_test_settings_errors'] )->toHaveCount( 1 );
	}
)->with(
	array(
		'a whole URL'      => 'https://api.thunderforest.com/outdoors/{z}/{x}/{y}.png?apikey=abc',
		'punctuation'      => 'abcd-1234-efgh-5678',
		'too short'        => 'abc123',
		'markup'           => '<script>alert(1)</script>',
	)
);

test(
	'the style setting only accepts styles the plugin offers',
	function () {
		$plugin = new Plugin();

		foreach ( array_keys( Renderer::thunderforest_styles() ) as $style ) {
			expect( $plugin->sanitize_thunderforest_style( $style ) )->toBe( $style );
		}

		expect( $plugin->sanitize_thunderforest_style( 'spinal-map' ) )->toBe( 'outdoors' );
		expect( $plugin->sanitize_thunderforest_style( null ) )->toBe( 'outdoors' );
	}
);
