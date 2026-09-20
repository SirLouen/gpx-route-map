<?php
/**
 * Unit tests for the site-wide map height.
 *
 * Height is the one display setting a map can carry as a plain number, so
 * "inherit" has to be represented by a sentinel rather than an empty string.
 * These tests pin that 0 (and an absent attribute) means the site setting,
 * because getting it wrong silently resizes every published map.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

use Gpxrm\Plugin;
use Gpxrm\Renderer;

/**
 * Resolve an attribute set the way the block and shortcode both do.
 *
 * gpxrm_normalize() is private, so reach it the way the sibling suites do.
 *
 * @param array<string, mixed> $atts Raw attributes.
 * @return array<string, mixed>
 */
function gpxrm_normalize( array $atts ): array {
	$method = new ReflectionMethod( Renderer::class, 'normalize' );
	$method->setAccessible( true );

	return (array) $method->invoke( null, $atts );
}

beforeEach(
	function () {
		$GLOBALS['gpxrm_test_options']         = array();
		$GLOBALS['gpxrm_test_settings_errors'] = array();
	}
);

test(
	'an unconfigured site uses the shipped default',
	function () {
		expect( Renderer::default_height() )->toBe( Renderer::DEFAULT_HEIGHT );
	}
);

test(
	'the stored option is used once set',
	function () {
		$GLOBALS['gpxrm_test_options']['gpxrm_height'] = 640;

		expect( Renderer::default_height() )->toBe( 640 );
	}
);

test(
	'a stored value outside the range is clamped rather than trusted',
	function ( $stored, $expected ) {
		$GLOBALS['gpxrm_test_options']['gpxrm_height'] = $stored;

		expect( Renderer::default_height() )->toBe( $expected );
	}
)->with(
	array(
		'far too short' => array( 10, Renderer::MIN_HEIGHT ),
		'far too tall'  => array( 99999, Renderer::MAX_HEIGHT ),
		'exact minimum' => array( Renderer::MIN_HEIGHT, Renderer::MIN_HEIGHT ),
		'exact maximum' => array( Renderer::MAX_HEIGHT, Renderer::MAX_HEIGHT ),
		'numeric string' => array( '600', 600 ),
		'nonsense'      => array( 'tall', Renderer::DEFAULT_HEIGHT ),
	)
);

test(
	'the settings field only stores heights the renderer accepts',
	function () {
		$plugin = new Plugin();

		expect( $plugin->sanitize_height( 640 ) )->toBe( 640 );
		expect( $plugin->sanitize_height( '640' ) )->toBe( 640 );
		expect( $plugin->sanitize_height( 10 ) )->toBe( Renderer::MIN_HEIGHT );
		expect( $plugin->sanitize_height( 99999 ) )->toBe( Renderer::MAX_HEIGHT );
		expect( $plugin->sanitize_height( 'tall' ) )->toBe( Renderer::DEFAULT_HEIGHT );
		expect( $plugin->sanitize_height( null ) )->toBe( Renderer::DEFAULT_HEIGHT );
	}
);

test(
	'a map without its own height follows the site setting',
	function ( $atts ) {
		$GLOBALS['gpxrm_test_options']['gpxrm_height'] = 700;

		$normalized = gpxrm_normalize( $atts );

		expect( $normalized['height'] )->toBe( 700 );
	}
)->with(
	array(
		// Gutenberg omits an attribute equal to its default, so an inheriting
		// block arrives with no height at all.
		'attribute absent' => array( array( 'gpx' => 'https://example.com/r.gpx' ) ),
		'explicit zero'    => array(
			array(
				'gpx' => 'https://example.com/r.gpx',
				'height' => 0,
			),
		),
		'zero as string'   => array(
			array(
				'gpx' => 'https://example.com/r.gpx',
				'height' => '0',
			),
		),
	)
);

test(
	'a map that sets its own height keeps it whatever the site says',
	function () {
		$GLOBALS['gpxrm_test_options']['gpxrm_height'] = 700;

		$normalized = gpxrm_normalize(
			array(
				'gpx'    => 'https://example.com/r.gpx',
				'height' => 300,
			)
		);

		expect( $normalized['height'] )->toBe( 300 );
	}
);

test(
	'a per-map height is still clamped',
	function () {
		$normalized = gpxrm_normalize(
			array(
				'gpx'    => 'https://example.com/r.gpx',
				'height' => 99999,
			)
		);

		expect( $normalized['height'] )->toBe( Renderer::MAX_HEIGHT );
	}
);

test(
	'the shortcode defers to the site setting instead of hard-coding its own',
	function ( $attribute ) {
		// A real value here would quietly override the site setting on every
		// map that omits the attribute - the bug this pins down for height.
		$defaults = Plugin::shortcode_defaults();

		expect( $defaults )->toHaveKey( $attribute );
		expect( $defaults[ $attribute ] )->toBeIn( array( '', 0 ) );
	}
)->with(
	array(
		// Every attribute backed by a site-wide setting.
		'height'    => 'height',
		'stats'     => 'stats',
		'elevation' => 'elevation',
		'download'  => 'download',
		'fields'    => 'fields',
		'units'     => 'units',
	)
);
