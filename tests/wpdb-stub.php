<?php
/**
 * A stand-in for WordPress's database, for the query that finds maps. Holds the
 * posts a test gives it, newest first, and keeps what it was asked. Required
 * by bootstrap.php.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

// phpcs:disable PEAR.NamingConventions.ValidClassName.StartWithCapital -- WordPress's own name for the class.

if ( ! defined( 'ARRAY_A' ) ) {
	define( 'ARRAY_A', 'ARRAY_A' );
}

if ( ! class_exists( 'wpdb' ) ) {
	/**
	 * Answers from $rows, whatever the SQL says, which a real site checks.
	 */
	class wpdb {

		/**
		 * The table of posts.
		 *
		 * @var string
		 */
		public $posts = 'wp_posts';

		/**
		 * The content of each post, by post ID.
		 *
		 * @var array<int, string>
		 */
		public $rows = array();

		/**
		 * The query that listed the posts.
		 *
		 * @var string
		 */
		public $query = '';

		/**
		 * How many IDs each read of content asked for.
		 *
		 * @var array<int, int>
		 */
		public $reads = array();

		/**
		 * Text with what LIKE would read as a pattern escaped.
		 *
		 * @param string $text Text.
		 * @return string
		 */
		public function esc_like( string $text ): string {
			return addcslashes( $text, '_%\\' );
		}

		/**
		 * A value, quoted.
		 *
		 * @param string $query The format, which is always %s here.
		 * @param string $value Value.
		 * @return string
		 */
		public function prepare( string $query, string $value ): string {
			unset( $query );
			return "'{$value}'";
		}

		/**
		 * The IDs of the posts.
		 *
		 * @param string $query Query.
		 * @return array<int, string>
		 */
		public function get_col( string $query ): array {
			$this->query = $query;
			return array_map( 'strval', array_keys( $this->rows ) );
		}

		/**
		 * The posts a query names, in an order of the database's choosing.
		 *
		 * @param string $query  Query.
		 * @param string $output Ignored.
		 * @return array<int, array{ID: string, post_content: string}>
		 */
		public function get_results( string $query, string $output ): array {
			unset( $output );
			preg_match( '/IN \(([0-9, ]+)\)/', $query, $in );
			$ids           = array_map( 'intval', explode( ',', $in[1] ) );
			$this->reads[] = count( $ids );
			sort( $ids );

			$rows = array();
			foreach ( $ids as $id ) {
				$rows[] = array(
					'ID'           => (string) $id,
					'post_content' => $this->rows[ $id ],
				);
			}

			return $rows;
		}
	}
}
