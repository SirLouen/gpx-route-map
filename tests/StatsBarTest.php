<?php
/**
 * The stats bar as the server prints it, before the map has loaded and for
 * visitors without JavaScript.
 *
 * It can only print what the editor stored on the block. Stats stored before
 * 2.0.0 have no min elevation, which must read as unknown, not as sea level.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

use Gpxrm\Renderer;

/**
 * The stats bar as the server prints it, value by key in display order.
 *
 * @param mixed                $raw_stats The stored `stats` attribute.
 * @param array<int, string>   $fields    Stats to show.
 * @param array<string, mixed> $units     Unit conversion.
 * @return array<string, string>
 */
function gpxrm_stats_bar( $raw_stats, array $fields, array $units = array() ): array {
	$normalize = new ReflectionMethod( Renderer::class, 'normalize_stats' );
	$normalize->setAccessible( true );
	$render = new ReflectionMethod( Renderer::class, 'stats_html' );
	$render->setAccessible( true );

	$html = (string) $render->invoke(
		null,
		$normalize->invoke( null, $raw_stats ),
		$units + array(
			'distFactor' => 1.0,
			'distLabel'  => 'km',
			'eleFactor'  => 1.0,
			'eleLabel'   => 'm',
		),
		$fields
	);
	preg_match_all( '/data-gpxrm-stat="([a-z]+)">([^<]*)</', $html, $matches, PREG_SET_ORDER );

	return array_column( $matches, 2, 1 );
}

const GPXRM_STATS = array(
	'distance'  => 1.5,
	'gain'      => 20,
	'loss'      => 10,
	'max'       => 120,
	'min'       => 99.6,
	'waypoints' => 1,
);

test(
	'the min elevation follows the max',
	function () {
		expect( gpxrm_stats_bar( GPXRM_STATS, array( 'max', 'min' ) ) )->toBe(
			array(
				'max' => '120 m',
				'min' => '100 m',
			)
		);
	}
);

// Stats stored before 2.0.0 have no min. It is unknown, not zero, until the
// map works it out from the file or the editor stores the stats again.
test(
	'a min the stored stats lack shows as unknown',
	function ( $min ) {
		$stats = GPXRM_STATS;
		unset( $stats['min'] );
		if ( null !== $min ) {
			$stats['min'] = $min;
		}

		expect( gpxrm_stats_bar( $stats, array( 'max', 'min' ) ) )->toBe(
			array(
				'max' => '120 m',
				'min' => '—',
			)
		);
	}
)->with(
	array(
		'missing'      => array( null ),
		'not a number' => array( 'n/a' ),
	)
);

test(
	'the min elevation converts to feet',
	function () {
		$bar = gpxrm_stats_bar(
			array( 'min' => 100 ) + GPXRM_STATS,
			array( 'min' ),
			array(
				'eleFactor' => 3.28084,
				'eleLabel'  => 'ft',
			)
		);

		expect( $bar )->toBe( array( 'min' => '328 ft' ) );
	}
);

// The map rounds the same way, so the figure does not change when it loads.
test(
	'a min of half a metre rounds away from zero',
	function () {
		expect( gpxrm_stats_bar( array( 'min' => -410.5 ) + GPXRM_STATS, array( 'min' ) ) )->toBe( array( 'min' => '-411 m' ) );
	}
);

test(
	'a min just below sea level shows as 0, not -0',
	function () {
		expect( gpxrm_stats_bar( array( 'min' => -0.4 ) + GPXRM_STATS, array( 'min' ) ) )->toBe( array( 'min' => '0 m' ) );
	}
);
