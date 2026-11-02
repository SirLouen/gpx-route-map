<?php
/**
 * Which posts can hold a map, and reading them.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

use Gpxrm\Map_Hosts;

beforeEach(
	function () {
		$GLOBALS['wpdb'] = new wpdb();
	}
);

test(
	'maps are looked for in post types people edit, other than waypoints',
	function () {
		expect( Map_Hosts::types() )->toBe( array( 'post', 'page', 'wp_block' ) );
	}
);

test(
	'maps are looked for in every status a person can set',
	function () {
		expect( Map_Hosts::statuses() )->toBe( array( 'publish', 'future', 'draft', 'pending', 'private' ) );
	}
);

test(
	'the query asks only for those, by the block and by the shortcode',
	function () {
		iterator_to_array( Map_Hosts::contents() );

		$query = $GLOBALS['wpdb']->query;

		expect( $query )->toContain( "post_type IN ('post', 'page', 'wp_block')" );
		expect( $query )->toContain( "post_status IN ('publish', 'future', 'draft', 'pending', 'private')" );
		expect( $query )->toContain( "post_content LIKE '%wp:gpx-route-map/map%' OR post_content LIKE '%[gpx\_route\_map%'" );
		expect( $query )->toContain( 'ORDER BY post_modified_gmt DESC, ID DESC' );
	}
);

test(
	'posts come in the order the query found them, however they are read',
	function () {
		$GLOBALS['wpdb']->rows = array(
			9 => 'nine',
			3 => 'three',
			7 => 'seven',
		);

		expect( iterator_to_array( Map_Hosts::contents() ) )->toBe(
			array(
				9 => 'nine',
				3 => 'three',
				7 => 'seven',
			)
		);
	}
);

test(
	'a site with many maps is read a little at a time',
	function () {
		$GLOBALS['wpdb']->rows = array_fill_keys( range( 1, Map_Hosts::CHUNK + 1 ), 'content' );

		expect( iterator_to_array( Map_Hosts::contents() ) )->toHaveCount( Map_Hosts::CHUNK + 1 );
		expect( $GLOBALS['wpdb']->reads )->toBe( array( Map_Hosts::CHUNK, 1 ) );
	}
);

test(
	'a post gone between the two reads is left out',
	function () {
		$GLOBALS['wpdb'] = new class() extends wpdb {
			/**
			 * Two IDs, one of them no longer there.
			 *
			 * @param string $query Ignored.
			 * @return array<int, string>
			 */
			public function get_col( string $query ): array {
				unset( $query );
				return array( '4', '5' );
			}

			/**
			 * Only the post still there.
			 *
			 * @param string $query  Ignored.
			 * @param string $output Ignored.
			 * @return array<int, array{ID: string, post_content: string}>
			 */
			public function get_results( string $query, string $output ): array {
				unset( $query, $output );
				return array(
					array(
						'ID'           => '5',
						'post_content' => 'five',
					),
				);
			}
		};

		expect( iterator_to_array( Map_Hosts::contents() ) )->toBe( array( 5 => 'five' ) );
	}
);
