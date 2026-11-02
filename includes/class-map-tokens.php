<?php
/**
 * Finding map blocks in a post's content.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

namespace Gpxrm;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Walks the block delimiters of a post with WordPress's own tokenizer, in
 * which a map is found by its bytes. Parsing the post and writing it back
 * would change other blocks, such as {} into [], so nothing that needs a post
 * left as it is can go through that.
 *
 * WP_Block_Parser::next_token(), $document and $offset are public from 6.8,
 * and core's own custom-CSS filter walks a post the same way. WP_Block_Processor
 * comes only with 6.9.
 *
 * @phpstan-type Token array{type: string, attrs: array<mixed>|null, start: int, length: int}
 */
final class Map_Tokens {

	const BLOCK = 'gpx-route-map/map';

	/**
	 * The map blocks of a post, and where each block delimiter is.
	 *
	 * Maps stop counting at a closer with nothing open: WordPress's parser
	 * gives up there and reads the rest as one block of plain content, so
	 * maps past it do not show. Delimiters still count, as they are not text.
	 * A map inside a Custom HTML block is a map when its comment is not
	 * escaped: it shows there too.
	 *
	 * @param string $content Post content.
	 * @return array{maps: array<int, array{type: string, attrs: array<mixed>|null, start: int, length: int}>, spans: array<int, array{0: int, 1: int}>}
	 * @phpstan-return array{maps: array<int, Token>, spans: array<int, array{0: int, 1: int}>}
	 */
	public static function walk( string $content ): array {
		$parser           = new \WP_Block_Parser();
		$parser->document = $content;
		$parser->offset   = 0;

		$maps  = array();
		$spans = array();
		$depth = 0;
		while ( true ) {
			list( $type, $name, $attrs, $start, $length ) = $parser->next_token();
			if ( ! is_int( $start ) || ! is_int( $length ) ) {
				break;
			}

			$parser->offset = $start + $length;
			$spans[]        = array( $start, $length );
			if ( $depth < 0 ) {
				continue;
			}

			$depth += 'block-opener' === $type ? 1 : 0;
			$depth -= 'block-closer' === $type ? 1 : 0;
			if ( self::BLOCK === $name && 'block-closer' !== $type ) {
				$maps[] = array(
					'type'   => 'void-block' === $type ? 'void-block' : 'block-opener',
					'attrs'  => is_array( $attrs ) ? $attrs : null,
					'start'  => $start,
					'length' => $length,
				);
			}
		}

		return array(
			'maps'  => $maps,
			'spans' => $spans,
		);
	}
}
