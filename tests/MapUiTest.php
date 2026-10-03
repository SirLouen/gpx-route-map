<?php
/**
 * MapLibre's interface strings, checked against the shared key list.
 *
 * MapLibre only knows English and takes translations by its own string IDs. A
 * key misspelt here does not fail anywhere: MapLibre ignores it and shows its
 * English. tests/js/map-ui.test.ts checks the same fixture against MapLibre's
 * own list, so both ends are pinned.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

use Gpxrm\Renderer;

/**
 * The decoded payload exactly as the renderer prints it.
 *
 * @return array<string, mixed>
 */
function gpxrm_map_ui(): array {
	$method = new ReflectionMethod( Renderer::class, 'map_ui_json' );
	$method->setAccessible( true );

	return (array) json_decode( (string) $method->invoke( null ), true );
}

test(
	'the payload translates exactly the strings the maps show',
	function () {
		$raw  = file_get_contents( __DIR__ . '/fixtures/map-ui-keys.json' );
		$want = json_decode( (string) $raw, true )['sent'];

		$have = array_keys( gpxrm_map_ui() );
		sort( $want );
		sort( $have );

		expect( $have )->toBe( $want );
	}
);

test(
	'every string is a non-empty string',
	function () {
		foreach ( gpxrm_map_ui() as $key => $text ) {
			expect( $text )->toBeString( "string {$key}" )->not->toBe( '' );
		}
	}
);
