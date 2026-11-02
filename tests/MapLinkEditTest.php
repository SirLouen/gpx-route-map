<?php
/**
 * Changing one map in a post's content: on WordPress's real parser and on the
 * attribute encoder of 6.8, whose bug is part of what must be caught. Every
 * byte outside the map's own delimiter has to stay as it was.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

use Gpxrm\Map_Link_Edit;

/**
 * A parser that finds nothing, as a site's own parser might.
 */
class Gpxrm_Test_Empty_Parser extends WP_Block_Parser {

	/**
	 * No blocks at all.
	 *
	 * @param string $document Ignored.
	 * @return array<int, array<string, mixed>>
	 */
	public function parse( $document ) {
		unset( $document );
		return array();
	}
}

/**
 * A map with these attributes.
 *
 * @param string $json Attributes.
 * @return string
 */
function gpxrm_map_with( string $json ): string {
	return '<!-- wp:gpx-route-map/map ' . $json . ' /-->';
}

beforeEach(
	function () {
		$GLOBALS['gpxrm_test_filters'] = array();
	}
);

test(
	'each change does what it says',
	function ( string $before, string $op, int $position, string $after ) {
		expect( Map_Link_Edit::apply( gpxrm_map_with( $before ), 0, 12, $op, $position ) )->toBe( gpxrm_map_with( $after ) );
	}
)->with(
	array(
		'remove a link'             => array( '{"gpxId":5,"waypointPosts":{"0":12,"3":40}}', 'remove-position', 0, '{"gpxId":5,"waypointPosts":{"3":40}}' ),
		'remove the last link'      => array( '{"gpxId":5,"waypointPosts":{"3":12}}', 'remove-position', 3, '{"gpxId":5}' ),
		'a link to its coordinates' => array( '{"gpxId":5,"waypointPosts":{"0":12,"3":40}}', 'own', 0, '{"gpxId":5,"waypointPosts":{"3":40},"addedWaypoints":[12]}' ),
		'that, when added already'  => array( '{"gpxId":5,"waypointPosts":{"0":12},"addedWaypoints":[12]}', 'own', 0, '{"gpxId":5,"addedWaypoints":[12]}' ),
		'remove an added post'      => array( '{"gpxId":5,"addedWaypoints":[12,40]}', 'remove-added', -1, '{"gpxId":5,"addedWaypoints":[40]}' ),
		'remove the last added'     => array( '{"gpxId":5,"addedWaypoints":[12]}', 'remove-added', -1, '{"gpxId":5}' ),
		'add a post'                => array( '{"gpxId":5,"addedWaypoints":[40]}', 'add', -1, '{"gpxId":5,"addedWaypoints":[40,12]}' ),
		'add the first post'        => array( '{"gpxId":5}', 'add', -1, '{"gpxId":5,"addedWaypoints":[12]}' ),
		'links stored as a list'    => array( '{"gpxId":5,"waypointPosts":[12,40]}', 'remove-position', 0, '{"gpxId":5,"waypointPosts":{"1":40}}' ),
		'replace a junk list'       => array( '{"gpxId":5,"addedWaypoints":"x"}', 'add', -1, '{"gpxId":5,"addedWaypoints":[12]}' ),
	)
);

test(
	'a change to one key leaves the other as stored',
	function () {
		expect( Map_Link_Edit::apply( gpxrm_map_with( '{"gpxId":5,"waypointPosts":[40,41]}' ), 0, 12, 'add' ) )
			->toBe( gpxrm_map_with( '{"gpxId":5,"waypointPosts":[40,41],"addedWaypoints":[12]}' ) );
	}
);

test(
	'a map left with no attributes keeps no braces',
	function () {
		expect( Map_Link_Edit::apply( gpxrm_map_with( '{"addedWaypoints":[12]}' ), 0, 12, 'remove-added' ) )
			->toBe( '<!-- wp:gpx-route-map/map /-->' );
	}
);

test(
	'a map with no attributes can take a post',
	function () {
		expect( Map_Link_Edit::apply( '<!-- wp:gpx-route-map/map /-->', 0, 12, 'add' ) )
			->toBe( gpxrm_map_with( '{"addedWaypoints":[12]}' ) );
	}
);

test(
	'a map stored with a closer keeps it',
	function () {
		$content = '<!-- wp:gpx-route-map/map {"gpxId":5} --><div></div><!-- /wp:gpx-route-map/map -->';

		expect( Map_Link_Edit::apply( $content, 0, 12, 'add' ) )
			->toBe( '<!-- wp:gpx-route-map/map {"gpxId":5,"addedWaypoints":[12]} --><div></div><!-- /wp:gpx-route-map/map -->' );
	}
);

test(
	'the other attributes stay, an empty object as an empty object',
	function () {
		expect( Map_Link_Edit::apply( gpxrm_map_with( '{"gpxId":5,"style":{},"list":[],"stats":{"distance":3.9471122144943145,"waypoints":3}}' ), 0, 12, 'add' ) )
			->toBe( gpxrm_map_with( '{"gpxId":5,"style":{},"list":[],"stats":{"distance":3.9471122144943145,"waypoints":3},"addedWaypoints":[12]}' ) );
	}
);

test(
	'only the map asked for changes, among maps of one file',
	function () {
		$first  = gpxrm_map_with( '{"gpxId":5,"height":300}' );
		$second = gpxrm_map_with( '{"gpxId":5,"height":500}' );

		expect( Map_Link_Edit::apply( $first . "\n" . $second, 1, 12, 'add' ) )
			->toBe( $first . "\n" . gpxrm_map_with( '{"gpxId":5,"height":500,"addedWaypoints":[12]}' ) );
	}
);

test(
	'nothing outside the map changes by a single byte',
	function ( string $before, string $after ) {
		$map = gpxrm_map_with( '{"gpxId":5}' );

		expect( Map_Link_Edit::apply( $before . $map . $after, 0, 12, 'add' ) )
			->toBe( $before . gpxrm_map_with( '{"gpxId":5,"addedWaypoints":[12]}' ) . $after );
	}
)->with(
	array(
		'nested'                  => array( '<!-- wp:group --><div><!-- wp:columns --><div>', '</div><!-- /wp:columns --></div><!-- /wp:group -->' ),
		'classic text'            => array( "<p>Plain <b>markup</b></p>\n\n", "\n\n<p>More</p>" ),
		'a freeform block'        => array( '<!-- wp:freeform --><p>Old</p><!-- /wp:freeform -->', '' ),
		'escaped fake comments'   => array( '<!-- wp:html -->&lt;!-- wp:gpx-route-map/map /--&gt;<!-- /wp:html -->', '<!-- wp:code --><pre><code>&lt;!-- wp:gpx-route-map/map /--&gt;</code></pre><!-- /wp:code -->' ),
		'unicode escapes'         => array( '<!-- wp:paragraph {"a":"caf\u00e9 x\u002d\u002dy \u003c"} --><p>a</p><!-- /wp:paragraph -->', '' ),
		'numbers PHP would round' => array( '<!-- wp:x {"a":1.0,"b":1e3,"c":12345678901234567890} /-->', '' ),
		'an empty object'         => array( '<!-- wp:x {"settings":{},"list":{"0":"a","1":"b"}} /-->', '' ),
		'bad JSON elsewhere'      => array( '<!-- wp:paragraph {broken} --><p>a</p><!-- /wp:paragraph --><!-- wp:x {"a":1,} /-->', '' ),
		'Windows line endings'    => array( "<!-- wp:group -->\r\n<div>\r\n", "\r\n</div>\r\n<!-- /wp:group -->" ),
		'a query loop'            => array( '<!-- wp:query {"queryId":1,"query":{"perPage":3}} --><div><!-- wp:post-template --><!-- wp:post-title /--><!-- /wp:post-template -->', '</div><!-- /wp:query -->' ),
		'a stray closer after'    => array( '', '<!-- /wp:group --><p>past where the parser stops</p>' ),
		'an unclosed block'       => array( '<!-- wp:group --><div>', '' ),
		'text in many scripts'    => array( '<!-- wp:paragraph --><p>Café en el río ✓ 日本語</p><!-- /wp:paragraph -->', '' ),
		'a backslash'             => array( '<!-- wp:paragraph --><p>C:\\dir\\file</p><!-- /wp:paragraph -->', '' ),
	)
);

test(
	'a change is refused unless the map holds what it expects',
	function ( string $json, string $op, int $position ) {
		expect( Map_Link_Edit::apply( gpxrm_map_with( $json ), 0, 12, $op, $position ) )->toBeNull();
	}
)->with(
	array(
		'removing another post'        => array( '{"gpxId":5,"waypointPosts":{"0":40}}', 'remove-position', 0 ),
		'removing an empty place'      => array( '{"gpxId":5}', 'remove-position', 0 ),
		'moving another post'          => array( '{"gpxId":5,"waypointPosts":{"0":40}}', 'own', 0 ),
		'removing a post not added'    => array( '{"gpxId":5,"addedWaypoints":[40]}', 'remove-added', -1 ),
		'adding a post added already'  => array( '{"gpxId":5,"addedWaypoints":[12]}', 'add', -1 ),
		'adding a post linked already' => array( '{"gpxId":5,"waypointPosts":{"2":12}}', 'add', -1 ),
		'an unknown change'            => array( '{"gpxId":5,"waypointPosts":{"0":12}}', 'swap', 0 ),
	)
);

test(
	'a map that is not there is refused',
	function () {
		expect( Map_Link_Edit::apply( gpxrm_map_with( '{"gpxId":5}' ), 1, 12, 'add' ) )->toBeNull();
	}
);

test(
	'a map whose attributes are not JSON is refused',
	function () {
		expect( Map_Link_Edit::apply( gpxrm_map_with( '{"gpxId":5,}' ), 0, 12, 'add' ) )->toBeNull();
	}
);

// PHP reads this key into an array, but not into an object.
test(
	'a map with a key an object cannot hold is refused',
	function () {
		expect( Map_Link_Edit::apply( gpxrm_map_with( '{"\u0000a":1}' ), 0, 12, 'add' ) )->toBeNull();
	}
);

test(
	'a site whose parser sees the post differently is refused',
	function () {
		add_filter(
			'block_parser_class',
			static function (): string {
				return Gpxrm_Test_Empty_Parser::class;
			}
		);

		expect( Map_Link_Edit::apply( gpxrm_map_with( '{"gpxId":5}' ), 0, 12, 'add' ) )->toBeNull();
	}
);

// WordPress 6.8 turns a value's closing \" into \u0022, which breaks the JSON.
test(
	'what the encoder of WordPress 6.8 breaks is refused',
	function () {
		expect( Map_Link_Edit::apply( gpxrm_map_with( '{"gpxId":5,"note":"C:\\\\"}' ), 0, 12, 'add' ) )->toBeNull();
	}
);
