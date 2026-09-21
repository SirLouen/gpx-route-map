<?php
/**
 * The height cascade, driven from the shared fixture.
 *
 * The same cascade is implemented in PHP (Renderer::normalize) and in
 * TypeScript (resolveHeights in src/edit.tsx), because the editor has to show
 * what the front end will render. Two implementations of one table drift, and
 * when they do the editor states one height while the visitor gets another -
 * with neither side wrong on its own. This fixture is the only artefact that
 * can fail when that happens; tests/js/height-cascade.test.ts drives the other
 * half from the very same file.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

use Gpxrm\Renderer;

beforeEach(
	function () {
		$GLOBALS['gpxrm_test_options'] = array();
		$GLOBALS['gpxrm_test_filters'] = array();
	}
);

/**
 * Every case in the shared fixture.
 *
 * @return array<string, array{0: array<string, int>, 1: array<string, int>, 2: array<string, int>}>
 */
function gpxrm_cascade_cases(): array {
	$raw  = file_get_contents( __DIR__ . '/fixtures/height-cascade.json' );
	$data = json_decode( (string) $raw, true );

	$cases = array();
	foreach ( $data['cases'] as $case ) {
		$cases[ $case['name'] ] = array( $case['map'], $case['site'], $case['want'] );
	}

	return $cases;
}

test(
	'the cascade resolves exactly as the shared fixture says',
	function ( array $map, array $site, array $want ) {
		$GLOBALS['gpxrm_test_options']['gpxrm_height'] = $site['base'];
		if ( $site['tablet'] > 0 ) {
			$GLOBALS['gpxrm_test_options']['gpxrm_height_tablet'] = $site['tablet'];
		}
		if ( $site['mobile'] > 0 ) {
			$GLOBALS['gpxrm_test_options']['gpxrm_height_mobile'] = $site['mobile'];
		}

		$method = new ReflectionMethod( Renderer::class, 'normalize' );
		$method->setAccessible( true );

		$got = (array) $method->invoke(
			null,
			array(
				'gpxUrl'       => 'https://example.test/r.gpx',
				'height'       => $map['base'],
				'heightTablet' => $map['tablet'],
				'heightMobile' => $map['mobile'],
			)
		);

		expect( $got['height'] )->toBe( $want['base'] );
		expect( $got['height_tablet'] )->toBe( $want['tablet'] );
		expect( $got['height_mobile'] )->toBe( $want['mobile'] );
	}
)->with( gpxrm_cascade_cases() );

/**
 * Every optional_height() edge case in the shared fixture.
 *
 * @return array<string, array{0: mixed, 1: int}>
 */
function gpxrm_optional_height_cases(): array {
	$raw  = file_get_contents( __DIR__ . '/fixtures/optional-height.json' );
	$data = json_decode( (string) $raw, true );

	// JSON has no NaN or Infinity, so the fixture names them and each language
	// substitutes its own value. Without this the table could never cover the
	// inputs the two implementations were most likely to disagree about.
	$markers = array(
		'__NAN__'     => NAN,
		'__INF__'     => INF,
		'__NEG_INF__' => -INF,
	);

	$cases = array();
	foreach ( $data['cases'] as $case ) {
		$raw = $case['in'];
		// Guarded on is_string: indexing an array with a float is deprecated
		// in PHP 8.1, and several of these inputs are floats.
		$in = ( is_string( $raw ) && isset( $markers[ $raw ] ) ) ? $markers[ $raw ] : $raw;

		// Qualified by type: 640 and "640" would otherwise share a dataset key
		// and the later entry would silently replace the earlier one, so the
		// parity table would run fewer cases here than in vitest.
		$cases[ gettype( $raw ) . ': ' . var_export( $raw, true ) ] = array( $in, $case['want'] );
	}

	return $cases;
}

test(
	'optional_height agrees with its JavaScript mirror on every edge case',
	function ( $in, int $want ) {
		// PHP's is_numeric() and JavaScript's Number() disagree about booleans
		// and hex literals, and casting before testing positivity made anything
		// under 1px read as "nothing set" on one side only.
		expect( Renderer::optional_height( $in ) )->toBe( $want );
	}
)->with( gpxrm_optional_height_cases() );
