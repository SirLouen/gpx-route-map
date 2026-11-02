<?php
/**
 * How the Maps box's form turns into changes, and when they are written.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

use Gpxrm\Map_Host_Writer;
use Gpxrm\Waypoint_Maps_Save;
use Gpxrm\Waypoints;

/**
 * A row's field name.
 *
 * @param string $from Where the row is: "a", or "p" and a position.
 * @param int    $host The post.
 * @return string
 */
function gpxrm_field( string $from, int $host = 100 ): string {
	return $host . ':b:0:' . str_repeat( 'a', 40 ) . ':' . $from;
}

/**
 * The form as the box sends it.
 *
 * @param array<string, string> $rows Rows.
 * @param string                $add  The map to add to.
 * @return array<string, mixed>
 */
function gpxrm_maps_form( array $rows = array(), string $add = '' ): array {
	return array(
		Waypoints::MAPS_NONCE => 'valid',
		'post_ID'             => '12',
		'gpxrm_maps'          => $rows,
		'gpxrm_maps_add'      => $add,
	);
}

/**
 * A writer that keeps what it was asked, and can throw.
 *
 * @param bool $throws Whether it throws.
 * @return Map_Host_Writer
 */
function gpxrm_spy_writer( bool $throws = false ): Map_Host_Writer {
	return new class( $throws ) extends Map_Host_Writer {
		/**
		 * Each call, with the request as it stood.
		 *
		 * @var array<int, array<string, mixed>>
		 */
		public $calls = array();

		/**
		 * Whether it throws.
		 *
		 * @var bool
		 */
		private $throws;

		/**
		 * A writer that keeps what it was asked.
		 *
		 * @param bool $throws Whether it throws.
		 */
		public function __construct( bool $throws ) {
			$this->throws = $throws;
		}

		/**
		 * Keep the call.
		 *
		 * @param int                              $host     The post.
		 * @param string                           $seen     The hash in the form.
		 * @param int                              $waypoint The Waypoint.
		 * @param array<int, array<string, mixed>> $changes  The changes.
		 * @return array{code: string, user?: int}
		 * @throws RuntimeException When asked to.
		 */
		public function write( int $host, string $seen, int $waypoint, array $changes ): array {
			$this->calls[] = array(
				'host'     => $host,
				'seen'     => $seen,
				'waypoint' => $waypoint,
				'changes'  => $changes,
				'post'     => $_POST,
				'request'  => $_REQUEST,
			);

			if ( $this->throws ) {
				throw new RuntimeException( 'A host called wp_die.' );
			}

			return array( 'code' => 'done' );
		}
	};
}

beforeEach(
	function () {
		$GLOBALS['gpxrm_test_caps']       = array( 'edit_post' );
		$GLOBALS['gpxrm_test_transients'] = array();
		$GLOBALS['gpxrm_test_user']       = 1;
		$_POST                            = array();
		$_REQUEST                         = array();
	}
);

afterEach(
	function () {
		$GLOBALS['gpxrm_test_caps'] = array();
		$_POST                      = array();
		$_REQUEST                   = array();
	}
);

test(
	'each row\'s choice becomes its change',
	function ( string $from, string $value, string $op, int $position ) {
		$hosts = Waypoint_Maps_Save::parse( array( gpxrm_field( $from ) => $value ), '' );

		expect( $hosts )->toBe(
			array(
				array(
					'host'    => 100,
					'seen'    => str_repeat( 'a', 40 ),
					'changes' => array(
						array(
							'ordinal'  => 0,
							'op'       => $op,
							'position' => $position,
						),
					),
				),
			)
		);
	}
)->with(
	array(
		'remove a link'      => array( 'p3', 'remove', 'remove-position', 3 ),
		'to own coordinates' => array( 'p3', 'own', 'own', 3 ),
		'remove an added'    => array( 'a', 'remove', 'remove-added', -1 ),
	)
);

test(
	'a row left alone asks for nothing',
	function ( string $from, $value ) {
		expect( Waypoint_Maps_Save::parse( array( gpxrm_field( $from ) => $value ), '' ) )->toBe( array() );
	}
)->with(
	array(
		'kept'                   => array( 'p3', 'keep' ),
		'an added post moved'    => array( 'a', 'own' ),
		'an unknown choice'      => array( 'p3', 'delete' ),
		'not text'               => array( 'p3', array( 'remove' ) ),
	)
);

test(
	'a field that is not one the box prints is ignored',
	function ( string $key ) {
		expect( Waypoint_Maps_Save::parse( array( $key => 'remove' ), '' ) )->toBe( array() );
	}
)->with(
	array(
		'no post'           => array( '0:b:0:' . str_repeat( 'a', 40 ) . ':a' ),
		'a shortcode'       => array( '100:s:0:' . str_repeat( 'a', 40 ) . ':a' ),
		'a short hash'      => array( '100:b:0:abc:a' ),
		'an upper-case one' => array( '100:b:0:' . str_repeat( 'A', 40 ) . ':a' ),
		'a bad place'       => array( '100:b:0:' . str_repeat( 'a', 40 ) . ':x3' ),
		'a line after it'   => array( '100:b:0:' . str_repeat( 'a', 40 ) . ":a\n" ),
		'nonsense'          => array( 'gpxrm' ),
	)
);

test(
	'adding to a map becomes a change of its post',
	function () {
		expect( Waypoint_Maps_Save::parse( array(), '100:b:2:' . str_repeat( 'b', 40 ) ) )->toBe(
			array(
				array(
					'host'    => 100,
					'seen'    => str_repeat( 'b', 40 ),
					'changes' => array(
						array(
							'ordinal'  => 2,
							'op'       => 'add',
							'position' => -1,
						),
					),
				),
			)
		);
	}
);

test(
	'a map to add to that is not one the box prints is ignored',
	function () {
		expect( Waypoint_Maps_Save::parse( array(), '100:b:2' ) )->toBe( array() );
		expect( Waypoint_Maps_Save::parse( array(), '' ) )->toBe( array() );
	}
);

test(
	'changes to one post are grouped, whatever the form\'s order',
	function () {
		$hosts = Waypoint_Maps_Save::parse(
			array(
				gpxrm_field( 'p0' )       => 'remove',
				gpxrm_field( 'p1', 200 )  => 'remove',
				gpxrm_field( 'a' )        => 'remove',
			),
			''
		);

		expect( array_column( $hosts, 'host' ) )->toBe( array( 100, 200 ) );
		expect( $hosts[0]['changes'] )->toHaveCount( 2 );
	}
);

test(
	'one Update makes no more changes than the box can ask for',
	function () {
		$rows = array();
		for ( $position = 0; $position < Waypoint_Maps_Save::MAX_CHANGES + 20; $position++ ) {
			$rows[ gpxrm_field( 'p' . $position ) ] = 'remove';
		}

		$hosts = Waypoint_Maps_Save::parse( $rows, '' );

		expect( $hosts[0]['changes'] )->toHaveCount( Waypoint_Maps_Save::MAX_CHANGES );
	}
);

test(
	'a changed row is written after the Waypoint\'s save, not during it',
	function () {
		$writer = gpxrm_spy_writer();
		$save   = new Waypoint_Maps_Save( $writer );
		$_POST  = gpxrm_maps_form( array( gpxrm_field( 'p0' ) => 'remove' ) );

		$save->queue( 12 );
		expect( $writer->calls )->toBe( array() );

		$save->write( 12 );
		expect( $writer->calls )->toHaveCount( 1 );
		expect( $writer->calls[0]['host'] )->toBe( 100 );
		expect( $writer->calls[0]['waypoint'] )->toBe( 12 );
	}
);

test(
	'the Waypoint comes from the save, never from the form',
	function () {
		$writer = gpxrm_spy_writer();
		$save   = new Waypoint_Maps_Save( $writer );
		$_POST  = gpxrm_maps_form( array( gpxrm_field( 'p0' ) => 'remove' ) ) + array( 'gpxrm_waypoint' => '999' );

		$save->queue( 12 );
		$save->write( 12 );

		expect( $writer->calls[0]['waypoint'] )->toBe( 12 );
	}
);

test(
	'nothing is written without the box\'s own nonce',
	function ( array $form ) {
		$writer = gpxrm_spy_writer();
		$save   = new Waypoint_Maps_Save( $writer );
		$_POST  = $form;

		$save->queue( 12 );
		$save->write( 12 );

		expect( $writer->calls )->toBe( array() );
	}
)->with(
	array(
		'no form'         => array( array() ),
		'a wrong nonce'   => array(
			array(
				Waypoints::MAPS_NONCE => 'stale',
				'post_ID'             => '12',
				'gpxrm_maps_add'      => '100:b:2:' . str_repeat( 'b', 40 ),
			),
		),
		'the box\'s other nonce' => array(
			array(
				Waypoints::NONCE => 'valid',
				'post_ID'        => '12',
				'gpxrm_maps_add' => '100:b:2:' . str_repeat( 'b', 40 ),
			),
		),
	)
);

test(
	'nothing is written for a post the form was not sent for',
	function () {
		$writer           = gpxrm_spy_writer();
		$save             = new Waypoint_Maps_Save( $writer );
		$_POST            = gpxrm_maps_form( array( gpxrm_field( 'p0' ) => 'remove' ) );
		$_POST['post_ID'] = '13';

		$save->queue( 12 );
		$save->write( 12 );

		expect( $writer->calls )->toBe( array() );
	}
);

test(
	'nothing is written for a user who cannot edit the Waypoint',
	function () {
		$GLOBALS['gpxrm_test_caps'] = array();

		$writer = gpxrm_spy_writer();
		$save   = new Waypoint_Maps_Save( $writer );
		$_POST  = gpxrm_maps_form( array( gpxrm_field( 'p0' ) => 'remove' ) );

		$save->queue( 12 );
		$save->write( 12 );

		expect( $writer->calls )->toBe( array() );
	}
);

test(
	'a plugin saving the Waypoint again causes no second write',
	function () {
		$writer = gpxrm_spy_writer();
		$save   = new Waypoint_Maps_Save( $writer );
		$_POST  = gpxrm_maps_form( array( gpxrm_field( 'p0' ) => 'remove' ) );

		$save->queue( 12 );
		$save->write( 12 );
		$save->write( 12 );

		expect( $writer->calls )->toHaveCount( 1 );
	}
);

test(
	'another post\'s save does not set the queue off',
	function () {
		$writer = gpxrm_spy_writer();
		$save   = new Waypoint_Maps_Save( $writer );
		$_POST  = gpxrm_maps_form( array( gpxrm_field( 'p0' ) => 'remove' ) );

		$save->queue( 12 );
		$save->write( 100 );

		expect( $writer->calls )->toBe( array() );
	}
);

// Another plugin's save_post on the host would take this form for its own.
test(
	'the request is empty while posts are written, and back afterwards',
	function () {
		$writer   = gpxrm_spy_writer();
		$save     = new Waypoint_Maps_Save( $writer );
		$form     = gpxrm_maps_form( array( gpxrm_field( 'p0' ) => 'remove' ) );
		$_POST    = $form;
		$_REQUEST = $form;

		$save->queue( 12 );
		$save->write( 12 );

		expect( $writer->calls[0]['post'] )->toBe( array() );
		expect( $writer->calls[0]['request'] )->toBe( array() );
		expect( $_POST )->toBe( $form );
		expect( $_REQUEST )->toBe( $form );
	}
);

test(
	'one post that fails stops neither the others nor the request\'s return',
	function () {
		$writer = gpxrm_spy_writer( true );
		$save   = new Waypoint_Maps_Save( $writer );
		$form   = gpxrm_maps_form(
			array(
				gpxrm_field( 'p0' )      => 'remove',
				gpxrm_field( 'p0', 200 ) => 'remove',
			)
		);
		$_POST  = $form;

		$save->queue( 12 );
		$save->write( 12 );

		expect( $writer->calls )->toHaveCount( 2 );
		expect( $_POST )->toBe( $form );
		expect( array_column( array_values( $GLOBALS['gpxrm_test_transients'] )[0], 'code' ) )->toBe( array( 'failed', 'failed' ) );
	}
);

test(
	'each post\'s result is kept as soon as it is known',
	function () {
		$writer = gpxrm_spy_writer();
		$save   = new Waypoint_Maps_Save( $writer );
		$_POST  = gpxrm_maps_form(
			array(
				gpxrm_field( 'p0' ) => 'remove',
				gpxrm_field( 'a' )  => 'remove',
			)
		);

		$save->queue( 12 );
		$save->write( 12 );

		expect( array_values( $GLOBALS['gpxrm_test_transients'] )[0] )->toBe(
			array(
				array(
					'code'    => 'done',
					'host'    => 100,
					'changes' => 2,
				),
			)
		);
	}
);
