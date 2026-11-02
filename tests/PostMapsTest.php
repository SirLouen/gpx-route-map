<?php
/**
 * Reading the maps out of a post's content, in blocks and in shortcodes, the
 * way the map itself reads its file and links.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

use Gpxrm\Plugin;
use Gpxrm\Post_Maps;
use Gpxrm\Renderer;

beforeEach(
	function () {
		$GLOBALS['gpxrm_test_attachments'] = array(
			5 => 'https://example.test/uploads/route.gpx',
			6 => 'https://example.test/uploads/other.gpx',
		);
		$GLOBALS['gpxrm_test_files']       = array();
		$GLOBALS['gpxrm_test_meta']        = array();
	}
);

/**
 * The maps in a post, reduced to the fields a test names.
 *
 * @param string             $content Post content.
 * @param array<int, string> $fields  Fields to keep.
 * @return array<int, array<string, mixed>>
 */
function gpxrm_maps_in( string $content, array $fields ): array {
	return array_map(
		static function ( array $map ) use ( $fields ): array {
			return array_intersect_key( $map, array_flip( $fields ) );
		},
		( new Post_Maps() )->read( $content ) ?? array()
	);
}

test(
	'it finds each map once, in blocks and in shortcodes',
	function ( string $content, array $kinds ) {
		expect( array_column( gpxrm_maps_in( $content, array( 'kind' ) ), 'kind' ) )->toBe( $kinds );
	}
)->with(
	array(
		'a block'                => array( '<!-- wp:gpx-route-map/map {"gpxId":5} /-->', array( 'block' ) ),
		'a nested block'         => array( '<!-- wp:group --><div><!-- wp:gpx-route-map/map {"gpxId":5} /--></div><!-- /wp:group -->', array( 'block' ) ),
		'in a paragraph'         => array( '<!-- wp:paragraph --><p>[gpx_route_map id="5" waypoints="1:7, @8"]</p><!-- /wp:paragraph -->', array( 'shortcode' ) ),
		'in a shortcode block'   => array( "<!-- wp:shortcode -->\n[gpx_route_map id=5]\n<!-- /wp:shortcode -->", array( 'shortcode' ) ),
		'escaped'                => array( '<!-- wp:paragraph --><p>[[gpx_route_map id=5]]</p><!-- /wp:paragraph -->', array() ),
		'in another block\'s JSON' => array( '<!-- wp:paragraph {"x":"[gpx_route_map id=5]"} --><p>a</p><!-- /wp:paragraph -->', array() ),
		'in a map\'s own JSON'   => array( '<!-- wp:gpx-route-map/map {"gpxId":5,"metadata":{"name":"[gpx_route_map id=6]"}} /-->', array( 'block' ) ),
		'written as an entity'   => array( '<!-- wp:code --><pre class="wp-block-code"><code>&#91;gpx_route_map id=5]</code></pre><!-- /wp:code -->', array() ),
		'after a stray closer'   => array( '<!-- /wp:group --><!-- wp:gpx-route-map/map {"gpxId":5} /-->[gpx_route_map id=5]', array( 'shortcode' ) ),
		'mixed'                  => array( '<!-- wp:gpx-route-map/map {"gpxId":5} /-->' . "\n[gpx_route_map id=5]\n" . '<!-- wp:gpx-route-map/map {"gpxId":5} /-->', array( 'block', 'shortcode', 'block' ) ),
		'in plain text'          => array( "text\n\n[gpx_route_map id=\"5\"]\n\nmore [gpx_route_map_extra id=5]", array( 'shortcode' ) ),
		'no map at all'          => array( '<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->', array() ),
	)
);

test(
	'maps are numbered within their kind, in the order of the post',
	function () {
		$content = '[gpx_route_map id=5]<!-- wp:gpx-route-map/map {"gpxId":5} /-->[gpx_route_map id=6]<!-- wp:gpx-route-map/map {"gpxId":6} /-->';

		expect( gpxrm_maps_in( $content, array( 'kind', 'ordinal' ) ) )->toBe(
			array(
				array(
					'kind'    => 'shortcode',
					'ordinal' => 0,
				),
				array(
					'kind'    => 'block',
					'ordinal' => 0,
				),
				array(
					'kind'    => 'shortcode',
					'ordinal' => 1,
				),
				array(
					'kind'    => 'block',
					'ordinal' => 1,
				),
			)
		);
	}
);

test(
	'a block map reads its links in either stored shape',
	function ( string $links ) {
		$maps = gpxrm_maps_in( '<!-- wp:gpx-route-map/map {"gpxId":5,"waypointPosts":' . $links . ',"addedWaypoints":[49,40]} /-->', array( 'own', 'added', 'file_id', 'file_url' ) );

		expect( $maps[0] )->toBe(
			array(
				'file_id'  => 5,
				'file_url' => 'https://example.test/uploads/route.gpx',
				'own'      => array( 0 => 12 ),
				'added'    => array( 49, 40 ),
			)
		);
	}
)->with(
	array(
		'an object' => array( '{"0":12}' ),
		'a list'    => array( '[12]' ),
	)
);

test(
	'a shortcode map reads its file and links as the shortcode does',
	function () {
		$maps = gpxrm_maps_in( '[gpx_route_map id="5" waypoints="2:12, @49"]', array( 'own', 'added', 'file_id' ) );

		expect( $maps[0] )->toBe(
			array(
				'file_id' => 5,
				'own'     => array( 1 => 12 ),
				'added'   => array( 49 ),
			)
		);
	}
);

test(
	'a shortcode map follows a filter on its attributes, as the map does',
	function () {
		add_filter(
			'shortcode_atts_gpx_route_map',
			static function ( array $atts ): array {
				$atts['id']        = '6';
				$atts['waypoints'] = '1:77';
				return $atts;
			}
		);

		$maps = gpxrm_maps_in( '[gpx_route_map id="5" waypoints="2:12"]', array( 'file_id', 'own' ) );

		unset( $GLOBALS['gpxrm_test_filters']['shortcode_atts_gpx_route_map'] );

		expect( $maps[0] )->toBe(
			array(
				'file_id' => 6,
				'own'     => array( 0 => 77 ),
			)
		);
	}
);

test(
	'a map shows the file\'s links where it makes none of its own',
	function () {
		$GLOBALS['gpxrm_test_files'][5] = gpxrm_waypoint_gpx(
			'<wpt lat="43.0" lon="-6.0"><extensions><gpxrm:waypoint>30</gpxrm:waypoint></extensions></wpt>'
			. '<wpt lat="43.1" lon="-6.0"><extensions><gpxrm:waypoint>31</gpxrm:waypoint></extensions></wpt>'
		);

		$maps = gpxrm_maps_in( '<!-- wp:gpx-route-map/map {"gpxId":5,"waypointPosts":{"1":12}} /-->', array( 'links' ) );

		expect( $maps[0]['links'] )->toBe(
			array(
				0 => 30,
				1 => 12,
			)
		);

		unlink( $GLOBALS['gpxrm_test_files'][5] );
	}
);

test(
	'a block map with broken attributes counts, and links nothing',
	function () {
		$maps = gpxrm_maps_in( '<!-- wp:gpx-route-map/map {"gpxId":5,} /--><!-- wp:gpx-route-map/map {"gpxId":6} /-->', array( 'ordinal', 'file_url' ) );

		expect( $maps )->toBe(
			array(
				array(
					'ordinal'  => 0,
					'file_url' => '',
				),
				array(
					'ordinal'  => 1,
					'file_url' => 'https://example.test/uploads/other.gpx',
				),
			)
		);
	}
);

test(
	'a post it cannot read is not a post without maps',
	function () {
		$reader = new class() extends Post_Maps {
			/**
			 * As when the content is too long for the pattern.
			 *
			 * @param string $text Ignored.
			 * @return array<int, array<array{0: string, 1: int}>>|null
			 */
			protected function shortcode_matches( string $text ): ?array {
				unset( $text );
				return null;
			}
		};

		expect( $reader->read( '[gpx_route_map id=5]' ) )->toBeNull();
	}
);

test(
	'a file is found as the map finds it',
	function ( array $atts, string $url, int $id ) {
		expect( Renderer::file_of( $atts ) )->toBe(
			array(
				'url' => $url,
				'id'  => $id,
			)
		);
	}
)->with(
	array(
		'a Media Library file'    => array( array( 'gpxId' => 5 ), 'https://example.test/uploads/route.gpx', 5 ),
		'its ID as text'          => array( array( 'gpxId' => '5' ), 'https://example.test/uploads/route.gpx', 5 ),
		'an address'              => array( array( 'gpxUrl' => 'https://example.test/a.gpx' ), 'https://example.test/a.gpx', 0 ),
		'a file that is gone'     => array( array( 'gpxId' => 99 ), '', 0 ),
		'a gone file, an address' => array(
			array(
				'gpxId'  => 99,
				'gpxUrl' => 'https://example.test/a.gpx',
			),
			'https://example.test/a.gpx',
			0,
		),
		'nothing'                 => array( array(), '', 0 ),
	)
);

test(
	'a shortcode names its file as it always has',
	function ( array $atts, array $want ) {
		expect( Plugin::shortcode_file( $atts + Plugin::shortcode_defaults() ) )->toBe( $want );
	}
)->with(
	array(
		'an ID'                   => array( array( 'id' => '5' ), array( 'gpxId' => 5 ) ),
		'a numeric gpx'           => array( array( 'gpx' => '5' ), array( 'gpxId' => 5 ) ),
		'an address'              => array( array( 'gpx' => 'https://example.test/a.gpx' ), array( 'gpxUrl' => 'https://example.test/a.gpx' ) ),
		'an ID beats an address'  => array(
			array(
				'id'  => '999999',
				'gpx' => 'https://example.test/a.gpx',
			),
			array( 'gpxId' => 999999 ),
		),
		'an ID that is not one'   => array(
			array(
				'id'  => 'abc',
				'gpx' => 'https://example.test/a.gpx',
			),
			array( 'gpxUrl' => 'https://example.test/a.gpx' ),
		),
	)
);
