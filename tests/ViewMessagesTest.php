<?php
/**
 * The front-end message payload, checked against the shared key list.
 *
 * The view runs as a script module and cannot load its own translations, so
 * every string it shows travels in a JSON attribute built here. A key this side
 * forgets does not fail anywhere: the view quietly falls back to its English
 * copy. tests/js/view-messages.test.ts holds the TypeScript fallback to the same
 * fixture, so the two sides cannot drift apart unnoticed.
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
function gpxrm_view_messages(): array {
	$method = new ReflectionMethod( Renderer::class, 'view_messages_json' );
	$method->setAccessible( true );

	return (array) json_decode( (string) $method->invoke( null ), true );
}

test(
	'the payload carries exactly the keys the view renders',
	function () {
		$raw  = file_get_contents( __DIR__ . '/fixtures/view-message-keys.json' );
		$want = json_decode( (string) $raw, true )['keys'];

		$have = array_keys( gpxrm_view_messages() );
		sort( $want );
		sort( $have );

		expect( $have )->toBe( $want );
	}
);

test(
	'every message is a non-empty string',
	function () {
		foreach ( gpxrm_view_messages() as $key => $message ) {
			expect( $message )->toBeString( "message {$key}" )->not->toBe( '' );
		}
	}
);
