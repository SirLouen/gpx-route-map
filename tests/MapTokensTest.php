<?php
/**
 * Finding maps in a post's blocks, against WordPress's real block parser.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

use Gpxrm\Map_Tokens;

const GPXRM_MAP_BLOCK = '<!-- wp:gpx-route-map/map {"gpxId":5} /-->';

/**
 * The maps WordPress's own parser finds, however deep.
 *
 * @param array<int, array<string, mixed>> $blocks Parsed blocks.
 * @return int
 */
function gpxrm_count_parsed_maps( array $blocks ): int {
	$count = 0;
	foreach ( $blocks as $block ) {
		$count += Map_Tokens::BLOCK === $block['blockName'] ? 1 : 0;
		$count += gpxrm_count_parsed_maps( $block['innerBlocks'] );
	}

	return $count;
}

test(
	'it finds what WordPress\'s parser finds',
	function ( string $content, int $want ) {
		expect( Map_Tokens::walk( $content )['maps'] )->toHaveCount( $want );
		expect( gpxrm_count_parsed_maps( parse_blocks( $content ) ) )->toBe( $want );
	}
)->with(
	array(
		'none'                       => array( '<!-- wp:paragraph --><p>Hello</p><!-- /wp:paragraph -->', 0 ),
		'one'                        => array( GPXRM_MAP_BLOCK, 1 ),
		'in a group'                 => array( '<!-- wp:group --><div>' . GPXRM_MAP_BLOCK . '</div><!-- /wp:group -->', 1 ),
		'in columns'                 => array( '<!-- wp:columns --><div><!-- wp:column --><div>' . GPXRM_MAP_BLOCK . GPXRM_MAP_BLOCK . '</div><!-- /wp:column --></div><!-- /wp:columns -->', 2 ),
		'with a closer'              => array( '<!-- wp:gpx-route-map/map {"gpxId":5} --><div></div><!-- /wp:gpx-route-map/map -->', 1 ),
		'with no attributes'         => array( '<!-- wp:gpx-route-map/map /-->', 1 ),
		'a stray closer first'       => array( '<!-- /wp:group -->' . GPXRM_MAP_BLOCK, 0 ),
		'a stray closer after one'   => array( GPXRM_MAP_BLOCK . '<!-- /wp:group -->' . GPXRM_MAP_BLOCK, 1 ),
		'an opener left open'        => array( '<!-- wp:group --><div>' . GPXRM_MAP_BLOCK, 1 ),
		'a closer that does not fit' => array( '<!-- wp:group --><div>' . GPXRM_MAP_BLOCK . '</div><!-- /wp:columns -->' . GPXRM_MAP_BLOCK, 2 ),
		'unescaped in custom HTML'   => array( '<!-- wp:html -->' . GPXRM_MAP_BLOCK . '<!-- /wp:html -->', 1 ),
		'escaped in custom HTML'     => array( '<!-- wp:html -->&lt;!-- wp:gpx-route-map/map /--&gt;<!-- /wp:html -->', 0 ),
		'escaped in a code block'    => array( '<!-- wp:code --><pre><code>&lt;!-- wp:gpx-route-map/map /--&gt;</code></pre><!-- /wp:code -->', 0 ),
		'a block of a similar name'  => array( '<!-- wp:gpx-route-map/map-extra {"gpxId":5} /-->', 0 ),
		'with Windows line endings'  => array( "<!-- wp:group -->\r\n<div>" . GPXRM_MAP_BLOCK . "</div>\r\n<!-- /wp:group -->", 1 ),
		'bad JSON in another block'  => array( '<!-- wp:paragraph {broken} --><p>a</p><!-- /wp:paragraph -->' . GPXRM_MAP_BLOCK, 1 ),
	)
);

test(
	'a map says which form it was stored in',
	function () {
		$walk = Map_Tokens::walk( GPXRM_MAP_BLOCK . '<!-- wp:gpx-route-map/map {"gpxId":6} --><div></div><!-- /wp:gpx-route-map/map -->' );

		expect( array_column( $walk['maps'], 'type' ) )->toBe( array( 'void-block', 'block-opener' ) );
		expect( array_column( array_column( $walk['maps'], 'attrs' ), 'gpxId' ) )->toBe( array( 5, 6 ) );
	}
);

test(
	'a map with attributes that are not JSON reads as having none',
	function () {
		$walk = Map_Tokens::walk( '<!-- wp:gpx-route-map/map {"gpxId":5,} /-->' );

		expect( $walk['maps'] )->toHaveCount( 1 );
		expect( $walk['maps'][0]['attrs'] )->toBeNull();
	}
);

test(
	'a map is found by bytes, whatever text comes before it',
	function () {
		$content = '<!-- wp:paragraph --><p>Café en el río ✓</p><!-- /wp:paragraph -->' . "\n" . GPXRM_MAP_BLOCK;
		$map     = Map_Tokens::walk( $content )['maps'][0];

		expect( substr( $content, $map['start'], $map['length'] ) )->toBe( GPXRM_MAP_BLOCK );
	}
);

test(
	'every delimiter is listed, past where the parser gave up',
	function () {
		$walk = Map_Tokens::walk( '<!-- /wp:group --><!-- wp:paragraph --><p>a</p><!-- /wp:paragraph -->' );

		expect( $walk['maps'] )->toBe( array() );
		expect( $walk['spans'] )->toHaveCount( 3 );
	}
);
