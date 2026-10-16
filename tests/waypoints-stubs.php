<?php
/**
 * Stubs for the WordPress functions Waypoint posts call, so their code can be
 * unit tested without loading WordPress. Required by bootstrap.php.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

if ( ! function_exists( 'wp_check_invalid_utf8' ) ) {
	/**
	 * Stand in for WordPress's check: '' for text that is not valid UTF-8.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function wp_check_invalid_utf8( string $text ): string {
		return 1 === preg_match( '//u', $text ) ? $text : '';
	}
}

if ( ! function_exists( 'attachment_url_to_postid' ) ) {
	/**
	 * No Media Library here: no address is one of its files.
	 *
	 * @param string $url File address.
	 * @return int
	 */
	function attachment_url_to_postid( string $url ): int {
		unset( $url );
		return 0;
	}
}

if ( ! function_exists( 'get_attached_file' ) ) {
	/**
	 * The file a test gave an attachment, or false for none.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string|false
	 */
	function get_attached_file( int $attachment_id ) {
		return $GLOBALS['gpxrm_test_files'][ $attachment_id ] ?? false;
	}
}

if ( ! function_exists( 'get_post_meta' ) ) {
	/**
	 * A value a test stored for a post, or '' for none, as WordPress gives a
	 * single value.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param bool   $single  Ignored: always a single value.
	 * @return mixed
	 */
	function get_post_meta( int $post_id, string $key, bool $single = false ) {
		unset( $single );
		return $GLOBALS['gpxrm_test_meta'][ $post_id ][ $key ] ?? '';
	}
}

if ( ! function_exists( 'update_post_meta' ) ) {
	/**
	 * Store a value for a post, where get_post_meta() finds it.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @param mixed  $value   Value.
	 * @return bool
	 */
	function update_post_meta( int $post_id, string $key, $value ): bool {
		$GLOBALS['gpxrm_test_meta'][ $post_id ][ $key ] = $value;
		return true;
	}
}
