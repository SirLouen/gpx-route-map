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

if ( ! function_exists( 'delete_post_meta' ) ) {
	/**
	 * Drop a value stored for a post.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $key     Meta key.
	 * @return bool
	 */
	function delete_post_meta( int $post_id, string $key ): bool {
		unset( $GLOBALS['gpxrm_test_meta'][ $post_id ][ $key ] );
		return true;
	}
}

if ( ! class_exists( 'WP_Post' ) ) {
	/**
	 * The fields of a post that Waypoint posts read.
	 */
	class WP_Post {

		/**
		 * Post ID.
		 *
		 * @var int
		 */
		public $ID;

		/**
		 * Title.
		 *
		 * @var string
		 */
		public $post_title;

		/**
		 * Password, or ''.
		 *
		 * @var string
		 */
		public $post_password;

		/**
		 * Type.
		 *
		 * @var string
		 */
		public $post_type = 'post';

		/**
		 * Status.
		 *
		 * @var string
		 */
		public $post_status = 'publish';

		/**
		 * A post.
		 *
		 * @param int    $id       Post ID.
		 * @param string $title    Title.
		 * @param string $password Password, or ''.
		 */
		public function __construct( int $id, string $title = '', string $password = '' ) {
			$this->ID            = $id;
			$this->post_title    = $title;
			$this->post_password = $password;
		}
	}
}

if ( ! function_exists( 'get_post' ) ) {
	/**
	 * A post a test made.
	 *
	 * @param int $post_id Post ID.
	 * @return WP_Post|null
	 */
	function get_post( int $post_id ): ?WP_Post {
		return $GLOBALS['gpxrm_test_posts'][ $post_id ] ?? null;
	}
}

if ( ! function_exists( 'update_post_thumbnail_cache' ) ) {
	/**
	 * Nothing to cache here.
	 *
	 * @param WP_Query $query The query.
	 * @return void
	 */
	function update_post_thumbnail_cache( WP_Query $query ): void {
		unset( $query );
	}
}

if ( ! function_exists( 'get_post_thumbnail_id' ) ) {
	/**
	 * No post has a photo here.
	 *
	 * @param WP_Post $post The post.
	 * @return int
	 */
	function get_post_thumbnail_id( WP_Post $post ): int {
		unset( $post );
		return 0;
	}
}

if ( ! function_exists( 'get_the_title' ) ) {
	/**
	 * A post's title.
	 *
	 * @param WP_Post $post The post.
	 * @return string
	 */
	function get_the_title( WP_Post $post ): string {
		return $post->post_title;
	}
}

if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	/**
	 * Text without its tags.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function wp_strip_all_tags( string $text ): string {
		return strip_tags( $text );
	}
}

if ( ! function_exists( 'wpautop' ) ) {
	/**
	 * Stand in for WordPress's paragraphs: the text as it is.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function wpautop( string $text ): string {
		return $text;
	}
}

if ( ! function_exists( 'wp_verify_nonce' ) ) {
	/**
	 * Only the nonce the tests send is valid.
	 *
	 * @param string $nonce  Nonce.
	 * @param string $action Ignored.
	 * @return bool
	 */
	function wp_verify_nonce( string $nonce, string $action ): bool {
		unset( $action );
		return 'valid' === $nonce;
	}
}

if ( ! function_exists( 'wp_is_post_revision' ) ) {
	/**
	 * No post is a revision here.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	function wp_is_post_revision( int $post_id ): bool {
		unset( $post_id );
		return false;
	}
}

if ( ! function_exists( 'wp_unslash' ) ) {
	/**
	 * Text without the slashes WordPress adds to a request.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	function wp_unslash( $value ) {
		return is_string( $value ) ? stripslashes( $value ) : $value;
	}
}

if ( ! function_exists( 'wp_slash' ) ) {
	/**
	 * Text with the slashes update_post_meta() takes off.
	 *
	 * @param string $value Text.
	 * @return string
	 */
	function wp_slash( string $value ): string {
		return addslashes( $value );
	}
}

if ( ! function_exists( 'sanitize_text_field' ) ) {
	/**
	 * Stand in for WordPress's: the text, trimmed.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	function sanitize_text_field( string $text ): string {
		return trim( $text );
	}
}

if ( ! function_exists( 'sanitize_key' ) ) {
	/**
	 * Stand in for WordPress's: lower case letters, digits, - and _.
	 *
	 * @param string $key Key.
	 * @return string
	 */
	function sanitize_key( string $key ): string {
		return (string) preg_replace( '/[^a-z0-9_\-]/', '', strtolower( $key ) );
	}
}

if ( ! function_exists( 'add_query_arg' ) ) {
	/**
	 * An address with one more argument.
	 *
	 * @param string $key   Argument.
	 * @param string $value Its value.
	 * @param string $url   Address.
	 * @return string
	 */
	function add_query_arg( string $key, string $value, string $url ): string {
		return $url . ( false === strpos( $url, '?' ) ? '?' : '&' ) . $key . '=' . $value;
	}
}

if ( ! function_exists( 'wp_get_upload_dir' ) ) {
	/**
	 * Where uploads live.
	 *
	 * @return array{baseurl: string}
	 */
	function wp_get_upload_dir(): array {
		return array( 'baseurl' => 'https://example.test/wp-content/uploads' );
	}
}

if ( ! function_exists( 'wp_get_attachment_url' ) ) {
	/**
	 * The address a test gave an attachment, or false for none.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @return string|false
	 */
	function wp_get_attachment_url( int $attachment_id ) {
		return $GLOBALS['gpxrm_test_attachments'][ $attachment_id ] ?? false;
	}
}

if ( ! function_exists( 'get_post_types' ) ) {
	/**
	 * The post types of a default site, with the Waypoint type.
	 *
	 * @param array<string, mixed> $args Ignored.
	 * @return array<string, string>
	 */
	function get_post_types( array $args ): array {
		unset( $args );
		return array(
			'post'           => 'post',
			'page'           => 'page',
			'attachment'     => 'attachment',
			'wp_block'       => 'wp_block',
			'wp_navigation'  => 'wp_navigation',
			'gpxrm_waypoint' => 'gpxrm_waypoint',
		);
	}
}

if ( ! function_exists( 'get_post_stati' ) ) {
	/**
	 * The statuses of a default site that are not internal.
	 *
	 * @param array<string, mixed> $args Ignored.
	 * @return array<string, string>
	 */
	function get_post_stati( array $args ): array {
		unset( $args );
		return array(
			'publish' => 'publish',
			'future'  => 'future',
			'draft'   => 'draft',
			'pending' => 'pending',
			'private' => 'private',
		);
	}
}

if ( ! function_exists( '_prime_post_caches' ) ) {
	/**
	 * Keep which posts were primed, for a test to see.
	 *
	 * @param array<int, int> $ids Post IDs.
	 * @return void
	 */
	function _prime_post_caches( array $ids ): void {
		$GLOBALS['gpxrm_test_primed'] = $ids;
	}
}

if ( ! function_exists( '_n' ) ) {
	/**
	 * The singular or the plural, in English.
	 *
	 * @param string $single Singular.
	 * @param string $plural Plural.
	 * @param int    $number Number.
	 * @return string
	 */
	function _n( string $single, string $plural, int $number ): string {
		return 1 === $number ? $single : $plural;
	}
}

if ( ! function_exists( '_draft_or_post_title' ) ) {
	/**
	 * A post's title, for a post a test made.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	function _draft_or_post_title( int $post_id ): string {
		return esc_html( $GLOBALS['gpxrm_test_posts'][ $post_id ]->post_title );
	}
}

if ( ! function_exists( 'get_post_type' ) ) {
	/**
	 * A post's type, for a post a test made: a post unless it says.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	function get_post_type( int $post_id ): string {
		return $GLOBALS['gpxrm_test_posts'][ $post_id ]->post_type ?? 'post';
	}
}

if ( ! function_exists( 'get_post_status' ) ) {
	/**
	 * A post's status, for a post a test made: published unless it says.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	function get_post_status( int $post_id ): string {
		return $GLOBALS['gpxrm_test_posts'][ $post_id ]->post_status ?? 'publish';
	}
}

if ( ! function_exists( 'get_post_type_object' ) ) {
	/**
	 * A post type, labelled as its name.
	 *
	 * @param string $type Post type.
	 * @return object
	 */
	function get_post_type_object( string $type ) {
		return (object) array( 'labels' => (object) array( 'singular_name' => ucfirst( $type ) ) );
	}
}

if ( ! function_exists( 'get_post_status_object' ) ) {
	/**
	 * A post status, labelled as its name.
	 *
	 * @param string $status Status.
	 * @return object
	 */
	function get_post_status_object( string $status ) {
		return (object) array( 'label' => ucfirst( $status ) );
	}
}

if ( ! function_exists( 'get_edit_post_link' ) ) {
	/**
	 * Where a post is edited.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	function get_edit_post_link( int $post_id ): string {
		return "https://example.test/edit/{$post_id}";
	}
}

if ( ! function_exists( 'get_permalink' ) ) {
	/**
	 * Where a post is read.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	function get_permalink( int $post_id ): string {
		return "https://example.test/read/{$post_id}";
	}
}

if ( ! function_exists( 'is_post_publicly_viewable' ) ) {
	/**
	 * Whether a post is published, for a post a test made.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	function is_post_publicly_viewable( int $post_id ): bool {
		return 'publish' === get_post_status( $post_id );
	}
}

if ( ! function_exists( 'wp_basename' ) ) {
	/**
	 * The last part of a path.
	 *
	 * @param string $path Path.
	 * @return string
	 */
	function wp_basename( string $path ): string {
		return basename( $path );
	}
}

if ( ! function_exists( 'esc_html_e' ) ) {
	/**
	 * Print text, escaped.
	 *
	 * @param string $text   Text.
	 * @param string $domain Ignored.
	 * @return void
	 */
	function esc_html_e( string $text, string $domain = 'default' ): void {
		unset( $domain );
		echo esc_html( $text );
	}
}

if ( ! function_exists( 'wp_kses' ) ) {
	/**
	 * Stand in for WordPress's kses: only the elements allowed are kept.
	 * Their attributes and the protocols of their addresses are left to the
	 * real one, which these tests do not load.
	 *
	 * @param string                              $content      Markup.
	 * @param array<string, array<string, mixed>> $allowed_html Elements allowed, with their attributes.
	 * @return string
	 */
	function wp_kses( string $content, array $allowed_html ): string {
		return strip_tags( $content, array_keys( $allowed_html ) );
	}
}
