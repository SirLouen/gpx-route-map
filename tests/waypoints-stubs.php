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
		 * Content.
		 *
		 * @var string
		 */
		public $post_content = '';

		/**
		 * Excerpt.
		 *
		 * @var string
		 */
		public $post_excerpt = '';

		/**
		 * Address name.
		 *
		 * @var string
		 */
		public $post_name = '';

		/**
		 * When it last changed.
		 *
		 * @var string
		 */
		public $post_modified = '';

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
	 * A post a test made, as an object or, given ARRAY_A, as an array.
	 *
	 * @param int    $post_id Post ID.
	 * @param string $output  How to give it.
	 * @return WP_Post|array<string, mixed>|null
	 */
	function get_post( int $post_id, string $output = 'OBJECT' ) {
		$post = $GLOBALS['gpxrm_test_posts'][ $post_id ] ?? null;
		return null !== $post && 'ARRAY_A' === $output ? get_object_vars( $post ) : $post;
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
	 * @param WP_Post|int $post The post, or its ID.
	 * @return string
	 */
	function get_the_title( $post ): string {
		$found = is_int( $post ) ? $GLOBALS['gpxrm_test_posts'][ $post ] : $post;
		return $found->post_title;
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
	 * Text with the slashes update_post_meta() takes off, in text alone or in
	 * an array.
	 *
	 * @param mixed $value Value.
	 * @return mixed
	 */
	function wp_slash( $value ) {
		if ( is_array( $value ) ) {
			return array_map( 'wp_slash', $value );
		}

		return is_string( $value ) ? addslashes( $value ) : $value;
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

if ( ! function_exists( 'absint' ) ) {
	/**
	 * A whole number from 0.
	 *
	 * @param string $value Value.
	 * @return int
	 */
	function absint( string $value ): int {
		return abs( (int) $value );
	}
}

if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}

if ( ! function_exists( 'clean_post_cache' ) ) {
	/**
	 * Keep which posts were cleaned, for a test to see.
	 *
	 * @param int $post_id Post ID.
	 * @return void
	 */
	function clean_post_cache( int $post_id ): void {
		$GLOBALS['gpxrm_test_cleaned'][] = $post_id;
	}
}

if ( ! function_exists( 'wp_cache_delete' ) ) {
	/**
	 * Keep what was dropped from the cache, for a test to see.
	 *
	 * @param int    $key   Key.
	 * @param string $group Group.
	 * @return bool
	 */
	function wp_cache_delete( int $key, string $group ): bool {
		$GLOBALS['gpxrm_test_evicted'][ $group ][] = $key;
		return true;
	}
}

if ( ! function_exists( 'get_current_user_id' ) ) {
	/**
	 * The user a test logged in.
	 *
	 * @return int
	 */
	function get_current_user_id(): int {
		return $GLOBALS['gpxrm_test_user'] ?? 1;
	}
}

if ( ! function_exists( 'get_userdata' ) ) {
	/**
	 * A user a test named.
	 *
	 * @param int $user_id User ID.
	 * @return object|false
	 */
	function get_userdata( int $user_id ) {
		$name = $GLOBALS['gpxrm_test_users'][ $user_id ] ?? null;
		return null === $name ? false : (object) array( 'display_name' => $name );
	}
}

if ( ! function_exists( 'wp_save_post_revision' ) ) {
	/**
	 * Save a revision, through the filter a real one goes through, starting
	 * from what WordPress's content filters leave of a post.
	 *
	 * @param int $post_id Post ID.
	 * @return int
	 */
	function wp_save_post_revision( int $post_id ): int {
		$GLOBALS['gpxrm_test_revisions'][] = apply_filters(
			'wp_insert_post_data',
			array(
				'post_type'    => 'revision',
				'post_parent'  => $post_id,
				'post_content' => 'filtered',
				'post_title'   => 'filtered',
				'post_excerpt' => 'filtered',
			),
			array()
		);

		return 1;
	}
}

if ( ! function_exists( 'wp_update_post' ) ) {
	/**
	 * Save a post, through the filter a real save goes through, starting from
	 * what WordPress's content filters leave of a post, then save a revision
	 * as a real save does. A test can make a hook throw once the row is
	 * written, with $GLOBALS['gpxrm_test_save_throws'].
	 *
	 * @param array<string, mixed> $postarr  The post, slashed.
	 * @param bool                 $wp_error Ignored.
	 * @return int
	 * @throws RuntimeException When a test asks for it.
	 */
	function wp_update_post( array $postarr, bool $wp_error = false ): int {
		unset( $wp_error );
		$GLOBALS['gpxrm_test_saves'][] = $postarr;
		$data                          = apply_filters(
			'wp_insert_post_data',
			array(
				'post_content'  => 'filtered',
				'post_title'    => 'filtered',
				'post_excerpt'  => 'filtered',
				'post_name'     => 'filtered',
				'post_status'   => 'publish',
				'post_modified' => 'now',
			),
			$postarr
		);

		$post = $GLOBALS['gpxrm_test_posts'][ $postarr['ID'] ];
		foreach ( $data as $field => $value ) {
			$post->$field = wp_unslash( $value );
		}

		if ( ! empty( $GLOBALS['gpxrm_test_save_throws'] ) ) {
			throw new RuntimeException( 'A hook threw.' );
		}

		wp_save_post_revision( $postarr['ID'] );

		return $postarr['ID'];
	}
}

if ( ! function_exists( 'set_transient' ) ) {
	/**
	 * Keep a value for a test to read.
	 *
	 * @param string $key   Key.
	 * @param mixed  $value Value.
	 * @param int    $time  Ignored.
	 * @return bool
	 */
	function set_transient( string $key, $value, int $time = 0 ): bool {
		unset( $time );
		$GLOBALS['gpxrm_test_transients'][ $key ] = $value;
		return true;
	}
}

if ( ! function_exists( 'get_transient' ) ) {
	/**
	 * A value kept, or false.
	 *
	 * @param string $key Key.
	 * @return mixed
	 */
	function get_transient( string $key ) {
		return $GLOBALS['gpxrm_test_transients'][ $key ] ?? false;
	}
}

if ( ! function_exists( 'delete_transient' ) ) {
	/**
	 * Drop a value kept.
	 *
	 * @param string $key Key.
	 * @return bool
	 */
	function delete_transient( string $key ): bool {
		unset( $GLOBALS['gpxrm_test_transients'][ $key ] );
		return true;
	}
}

if ( ! function_exists( 'wp_admin_notice' ) ) {
	/**
	 * Keep a notice, for a test to see.
	 *
	 * @param string               $message Message.
	 * @param array<string, mixed> $args    How to show it.
	 * @return void
	 */
	function wp_admin_notice( string $message, array $args ): void {
		$GLOBALS['gpxrm_test_notices'][] = array( $message, $args );
	}
}

if ( ! function_exists( 'get_current_screen' ) ) {
	/**
	 * The screen a test is on.
	 *
	 * @return object|null
	 */
	function get_current_screen() {
		return $GLOBALS['gpxrm_test_screen'] ?? null;
	}
}

if ( ! function_exists( 'wp_revisions_enabled' ) ) {
	/**
	 * Whether a post keeps revisions: yes, unless a test says otherwise.
	 *
	 * @param WP_Post $post The post.
	 * @return bool
	 */
	function wp_revisions_enabled( WP_Post $post ): bool {
		unset( $post );
		return empty( $GLOBALS['gpxrm_test_no_revisions'] );
	}
}

if ( ! function_exists( 'post_type_supports' ) ) {
	/**
	 * Every post type here supports everything.
	 *
	 * @param string $type    Post type.
	 * @param string $feature Feature.
	 * @return bool
	 */
	function post_type_supports( string $type, string $feature ): bool {
		unset( $type, $feature );
		return true;
	}
}

if ( ! function_exists( 'wp_nonce_field' ) ) {
	/**
	 * Print a nonce field.
	 *
	 * @param string $action Ignored.
	 * @param string $name   Field name.
	 * @return void
	 */
	function wp_nonce_field( string $action, string $name ): void {
		unset( $action );
		echo '<input type="hidden" name="' . esc_attr( $name ) . '" value="valid">';
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
