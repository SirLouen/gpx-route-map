<?php
/**
 * Waypoint posts: the switch that turns them on, and the settings a post
 * keeps: its description, marker, icon and colours.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

use Gpxrm\Dashicons;
use Gpxrm\Plugin;
use Gpxrm\Waypoint_Columns;
use Gpxrm\Waypoint_Description;
use Gpxrm\Waypoint_Settings;
use Gpxrm\Waypoint_Values;
use Gpxrm\Waypoints;

beforeEach(
	function () {
		$GLOBALS['gpxrm_test_options'] = array();
		$GLOBALS['gpxrm_test_filters'] = array();
		$GLOBALS['gpxrm_test_files']   = array();
		$GLOBALS['gpxrm_test_meta']    = array();
	}
);

test(
	'Waypoint posts are off until the site turns them on',
	function () {
		expect( Waypoints::enabled() )->toBeFalse();
		expect( Plugin::editor_defaults()['waypoints'] )->toBeFalse();

		$GLOBALS['gpxrm_test_options'][ Waypoints::OPTION ] = '1';
		expect( Waypoints::enabled() )->toBeTrue();
		expect( Plugin::editor_defaults()['waypoints'] )->toBeTrue();
	}
);

test(
	'only a stored true turns them on',
	function ( $stored, bool $on ) {
		$GLOBALS['gpxrm_test_options'][ Waypoints::OPTION ] = $stored;
		expect( Waypoints::enabled() )->toBe( $on );
	}
)->with(
	array(
		'true'          => array( true, true ),
		'1'             => array( 1, true ),
		"'1'"           => array( '1', true ),
		'false'         => array( false, false ),
		"''"            => array( '', false ),
		"'0'"           => array( '0', false ),
		"'yes'"         => array( 'yes', false ),
		'a list'        => array( array( 1 ), false ),
		'not saved yet' => array( null, false ),
	)
);

test(
	'the setting keeps a ticked box as on and anything else as off',
	function ( $submitted, bool $on ) {
		expect( ( new Plugin() )->sanitize_waypoints( $submitted ) )->toBe( $on );
	}
)->with(
	array(
		'on'     => array( 'on', true ),
		'1'      => array( '1', true ),
		'true'   => array( true, true ),
		'absent' => array( null, false ),
		"''"     => array( '', false ),
		'off'    => array( 'off', false ),
		'a list' => array( array( 'on' ), false ),
	)
);

test(
	'a waypoint keeps only settings the map can use',
	function () {
		expect(
			Waypoint_Values::sanitize_fields(
				array(
					'description' => "  Cold water\nall year  ",
					'marker'      => 'icon',
					'icon'        => 'flag',
					'color'       => '#FF0000',
					'background'  => ' #00f ',
				)
			)
		)->toBe(
			array(
				'description' => "Cold water\nall year",
				'marker'      => 'icon',
				'icon'        => 'flag',
				'color'       => '#ff0000',
				'background'  => '#0000ff',
			)
		);
	}
);

// So a map draws its own colours, which may one day follow the site or theme.
test(
	'a waypoint keeps a colour left at its default as none',
	function () {
		$fields = Waypoint_Values::sanitize_fields(
			array(
				'marker'     => 'icon',
				'icon'       => 'flag',
				'color'      => '#FFF',
				'background' => strtoupper( Waypoints::DEFAULT_BACKGROUND ),
			)
		);
		expect( $fields['color'] )->toBe( '' );
		expect( $fields['background'] )->toBe( '' );
	}
);

test(
	'a waypoint drops a marker, icon or colour it does not know',
	function () {
		expect(
			Waypoint_Values::sanitize_fields(
				array(
					'description' => array( 'not text' ),
					'marker'      => 'balloon',
					'icon'        => 'not-an-icon',
					'color'       => 'red',
					'background'  => '#12345',
				)
			)
		)->toBe(
			array(
				'description' => '',
				'marker'      => '',
				'icon'        => '',
				'color'       => '',
				'background'  => '',
			)
		);
	}
);

// The map shows it as text, so nothing in it is a tag.
test(
	'a description keeps the markup its editor writes, and only that',
	function () {
		expect(
			Waypoint_Description::sanitize(
				"Gradient &lt;10% on <a href=\"https://example.test/\">the climb</a>, <strong>steep</strong>.\r\n<ul><li>Water</li></ul><img src=\"x\"><span>Shade</span>"
			)
		)->toBe( "Gradient &lt;10% on <a href=\"https://example.test/\">the climb</a>, <strong>steep</strong>.\n<ul><li>Water</li></ul>Shade" );
	}
);

test(
	'a description may hold links, bold and italic text, lists and paragraphs',
	function () {
		expect( array_keys( Waypoint_Description::tags() ) )->toBe( array( 'a', 'b', 'br', 'em', 'i', 'li', 'ol', 'p', 'strong', 'ul' ) );
		expect( Waypoint_Description::tags()['a'] )->toBe(
			array(
				'href'   => true,
				'target' => true,
				'rel'    => true,
			)
		);
	}
);

test(
	'a description drops control characters and invalid text',
	function () {
		expect( Waypoint_Description::sanitize( "  One\x00\x07 line\rtwo\ttabbed  " ) )->toBe( "One line\ntwo\ttabbed" );
		expect( Waypoint_Description::sanitize( "bad \xC3\x28 bytes" ) )->toBe( '' );
	}
);

test(
	'an icon marker always has an icon',
	function () {
		$fields = Waypoint_Values::sanitize_fields(
			array(
				'marker' => 'icon',
				'icon'   => 'not-an-icon',
			)
		);
		expect( $fields['icon'] )->toBe( Waypoints::DEFAULT_ICON );
	}
);

test(
	'the icon list holds only names a class can take',
	function () {
		expect( Dashicons::NAMES )->not->toBeEmpty();
		expect( count( array_unique( Dashicons::NAMES ) ) )->toBe( count( Dashicons::NAMES ) );
		foreach ( Dashicons::NAMES as $name ) {
			expect( preg_match( '/^[a-z0-9-]+$/', $name ) )->toBe( 1, $name );
		}
		expect( Dashicons::exists( Waypoints::DEFAULT_ICON ) )->toBeTrue();
		expect( Dashicons::exists( 'location' ) )->toBeTrue();
		expect( Dashicons::exists( 'dashicons-location' ) )->toBeFalse();
		expect( Dashicons::exists( 3 ) )->toBeFalse();
	}
);

test(
	'colours are #rgb or #rrggbb, in lower case',
	function ( $value, string $want ) {
		expect( Waypoint_Values::hex_color( $value, 'fallback' ) )->toBe( $want );
	}
)->with(
	array(
		'short'          => array( '#ABC', '#aabbcc' ),
		'long'           => array( '#A1B2C3', '#a1b2c3' ),
		'padded'         => array( " #fff\n", '#ffffff' ),
		'no hash'        => array( 'ffffff', 'fallback' ),
		'four digits'    => array( '#ffff', 'fallback' ),
		'a name'         => array( 'red', 'fallback' ),
		'css after it'   => array( '#fff; background: url(x)', 'fallback' ),
		'not text'       => array( 255, 'fallback' ),
		'nothing at all' => array( '', 'fallback' ),
	)
);

test(
	'a waypoint\'s screens show it unsaved with the map\'s own icon and colours',
	function () {
		$settings = new Waypoint_Settings( 7 );

		expect( $settings->description() )->toBe( '' );
		expect( $settings->marker() )->toBe( '' );
		expect( $settings->icon() )->toBe( Waypoints::DEFAULT_ICON );
		expect( $settings->color() )->toBe( Waypoints::DEFAULT_COLOR );
		expect( $settings->background() )->toBe( Waypoints::DEFAULT_BACKGROUND );
	}
);

test(
	'a waypoint\'s screens show what it saved, kept to what they can show',
	function () {
		$GLOBALS['gpxrm_test_meta'][7] = array(
			Waypoints::META_DESCRIPTION => '<p>Cold water</p>',
			Waypoints::META_MARKER      => 'icon',
			Waypoints::META_ICON        => 'flag',
			Waypoints::META_COLOR       => '#F00',
			Waypoints::META_BACKGROUND  => 'red',
		);

		$settings = new Waypoint_Settings( 7 );

		expect( $settings->description() )->toBe( '<p>Cold water</p>' );
		expect( $settings->marker() )->toBe( 'icon' );
		expect( $settings->icon() )->toBe( 'flag' );
		expect( $settings->color() )->toBe( '#ff0000' );
		expect( $settings->background() )->toBe( Waypoints::DEFAULT_BACKGROUND );

		$GLOBALS['gpxrm_test_meta'][7][ Waypoints::META_MARKER ] = 'video';
		$GLOBALS['gpxrm_test_meta'][7][ Waypoints::META_ICON ]   = 'not-an-icon';

		expect( $settings->marker() )->toBe( '' );
		expect( $settings->icon() )->toBe( Waypoints::DEFAULT_ICON );
	}
);

test(
	'an icon marker\'s preview draws its icon in its colours',
	function () {
		$GLOBALS['gpxrm_test_meta'][7] = array(
			Waypoints::META_ICON  => 'flag',
			Waypoints::META_COLOR => '#123456',
		);

		expect( ( new Waypoint_Settings( 7 ) )->icon_html() )->toBe(
			'<span class="gpxrm-marker-preview" style="color:#123456;background-color:#2e7d32"><span class="dashicons dashicons-flag" aria-hidden="true"></span></span>'
		);
	}
);

test(
	'the list of waypoints shows each one\'s marker and ID after its title',
	function () {
		$columns = ( new Waypoint_Columns() )->columns(
			array(
				'cb'    => '<input type="checkbox">',
				'title' => 'Title',
				'date'  => 'Date',
			)
		);

		expect( array_keys( $columns ) )->toBe( array( 'cb', 'title', 'gpxrm_marker', 'gpxrm_id', 'date' ) );

		ob_start();
		( new Waypoint_Columns() )->column( 'gpxrm_id', 42 );
		expect( ob_get_clean() )->toBe( '42' );

		ob_start();
		( new Waypoint_Columns() )->column( 'date', 42 );
		expect( ob_get_clean() )->toBe( '' );
	}
);
