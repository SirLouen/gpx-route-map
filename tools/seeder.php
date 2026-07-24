<?php
/**
 * Demo seeder for GPX Route Map.
 *
 * @package GpxRouteMap\Tools
 */

if ( ! class_exists( 'WP_CLI' ) ) {
	return;
}

/**
 * Seeds demo GPX route maps for local development.
 */
final class Gpxrm_Seeder_Command {

	/**
	 * Meta key stamped on every seeded post/page/attachment.
	 */
	private const MARKER = '_gpxrm_seed';

	/**
	 * Imports the sample .gpx fixtures as Media attachments, creates one demo
	 * post per track, and builds a showcase page with several maps + a shortcode.
	 *
	 * ## OPTIONS
	 *
	 * [--fresh]
	 * : Delete previously seeded content first, then re-create it from scratch.
	 *
	 * ## EXAMPLES
	 *
	 *     # Seed (or refresh in place) all demo content.
	 *     $ wp gpxrm seed
	 *
	 *     # Wipe previously seeded content and re-create it.
	 *     $ wp gpxrm seed --fresh
	 *
	 * @when after_wp_load
	 *
	 * @param array<int, string>   $args       Positional args (unused).
	 * @param array<string, mixed> $assoc_args Associative args.
	 * @return void
	 */
	public function seed( $args, $assoc_args ) {
		if ( ! WP_Block_Type_Registry::get_instance()->is_registered( 'gpx-route-map/map' ) ) {
			WP_CLI::warning( 'The "gpx-route-map/map" block is not registered — is the plugin active and built (pnpm run build)?' );
		}

		if ( ! empty( $assoc_args['fresh'] ) ) {
			$this->unseed();
		}

		$attachments = array();

		foreach ( $this->fixtures() as $key => $fixture ) {
			$attachment_id       = $this->import_gpx( $key, $fixture );
			$post_id             = $this->demo_post( $key, $fixture, $attachment_id );
			$attachments[ $key ] = $attachment_id;

			WP_CLI::log( sprintf( '  %-30s attachment #%-4d post #%d', $fixture['title'], $attachment_id, $post_id ) );
		}

		$page_id = $this->showcase_page( $attachments );
		WP_CLI::log( sprintf( '  %-30s page #%d', 'Showcase (multi-map)', $page_id ) );

		WP_CLI::success(
			sprintf(
				'Seeded %d demo maps + a showcase page. Browse %s',
				count( $attachments ),
				home_url( '/' )
			)
		);
	}

	/**
	 * The sample fixtures to seed, keyed by slug.
	 *
	 * @return array<string, array{file: string, title: string, intro: string}>
	 */
	private function fixtures(): array {
		return array(
			'alpine-loop'       => array(
				'file'  => 'alpine-loop.gpx',
				'title' => 'Alpine Loop',
				'intro' => 'A short mountain loop with elevation gain, three waypoints and a full interactive elevation profile.',
			),
			'river-route'       => array(
				'file'  => 'river-route.gpx',
				'title' => 'River Route (route format)',
				'intro' => 'A GPX route (rte/rtept) rather than a recorded track for testing route-format handling.',
			),
			'flat-no-elevation' => array(
				'file'  => 'flat-no-elevation.gpx',
				'title' => 'Canal Towpath (no elevation)',
				'intro' => 'A flat track with no elevation data: distance is shown but the elevation profile stays empty.',
			),
			'two-segment'       => array(
				'file'  => 'two-segment.gpx',
				'title' => 'Two-Stage Hike (multi-segment)',
				'intro' => 'A track split into two separate segments with a gap between them.',
			),
		);
	}

	/**
	 * Import a fixture as a Media attachment.
	 *
	 * @param string                                            $key     Fixture slug.
	 * @param array{file: string, title: string, intro: string} $fixture Fixture definition.
	 * @return int Attachment ID.
	 */
	private function import_gpx( string $key, array $fixture ): int {
		$existing = $this->find_seeded( 'attachment:' . $key, 'attachment' );
		if ( $existing ) {
			return $existing;
		}

		$path     = __DIR__ . '/sample-data/' . $fixture['file'];
		$contents = file_get_contents( $path ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		if ( false === $contents ) {
			WP_CLI::error( 'Could not read fixture: ' . $path );
		}

		$upload = wp_upload_bits( $fixture['file'], null, $contents );
		if ( ! empty( $upload['error'] ) ) {
			WP_CLI::error( 'Upload failed for ' . $fixture['file'] . ': ' . $upload['error'] );
		}

		$attachment_id = wp_insert_attachment(
			array(
				'post_mime_type' => 'application/gpx+xml',
				'post_title'     => $fixture['title'],
				'post_status'    => 'inherit',
			),
			$upload['file']
		);

		update_post_meta( $attachment_id, self::MARKER, 'attachment:' . $key );

		return (int) $attachment_id;
	}

	/**
	 * Create or update the demo post for a fixture.
	 *
	 * @param string                                            $key           Fixture slug.
	 * @param array{file: string, title: string, intro: string} $fixture       Fixture definition.
	 * @param int                                               $attachment_id GPX attachment ID.
	 * @return int Post ID.
	 */
	private function demo_post( string $key, array $fixture, int $attachment_id ): int {
		$content  = sprintf( "<!-- wp:paragraph -->\n<p>%s</p>\n<!-- /wp:paragraph -->\n\n", $fixture['intro'] );
		$content .= sprintf( '<!-- wp:gpx-route-map/map %s /-->', wp_json_encode( array( 'gpxId' => $attachment_id ) ) );

		return $this->upsert(
			'post:' . $key,
			array(
				'post_type'    => 'post',
				'post_title'   => $fixture['title'],
				'post_content' => $content,
				'post_status'  => 'publish',
			)
		);
	}

	/**
	 * Create or update the multi-map showcase page.
	 *
	 * @param array<string, int> $attachments Attachment IDs keyed by fixture slug.
	 * @return int Page ID.
	 */
	private function showcase_page( array $attachments ): int {
		$ids = array_values( $attachments );

		$content  = "<!-- wp:paragraph -->\n<p>Several GPX Route Map blocks plus a shortcode on one page, to exercise multiple independent maps.</p>\n<!-- /wp:paragraph -->\n\n";
		$content .= "<!-- wp:heading -->\n<h2>Blocks</h2>\n<!-- /wp:heading -->\n\n";

		foreach ( array_slice( $ids, 0, 3 ) as $index => $id ) {
			$atts = array( 'gpxId' => $id );
			if ( 1 === $index ) {
				$atts['showElevation'] = false;
				$atts['height']        = 360;
			}
			$content .= sprintf( "<!-- wp:gpx-route-map/map %s /-->\n\n", wp_json_encode( $atts ) );
		}

		if ( $ids ) {
			$content .= "<!-- wp:heading -->\n<h2>Shortcode</h2>\n<!-- /wp:heading -->\n\n";
			$content .= sprintf( "<!-- wp:shortcode -->\n[gpx_route_map id=\"%d\"]\n<!-- /wp:shortcode -->", $ids[0] );
		}

		return $this->upsert(
			'page:showcase',
			array(
				'post_type'    => 'page',
				'post_title'   => 'GPX Route Map — Showcase',
				'post_content' => $content,
				'post_status'  => 'publish',
			)
		);
	}

	/**
	 * Insert a post/page, or update the existing seeded one with the same marker.
	 *
	 * @param string               $marker  Marker value that identifies this item.
	 * @param array<string, mixed> $postarr Post data for wp_insert_post/wp_update_post.
	 * @return int Post ID.
	 */
	private function upsert( string $marker, array $postarr ): int {
		$existing = $this->find_seeded( $marker, $postarr['post_type'] );

		if ( $existing ) {
			$postarr['ID'] = $existing;
			$post_id       = wp_update_post( $postarr, true );
		} else {
			$post_id = wp_insert_post( $postarr, true );
		}

		if ( is_wp_error( $post_id ) ) {
			WP_CLI::error( 'Failed to save "' . $marker . '": ' . $post_id->get_error_message() );
		}

		update_post_meta( $post_id, self::MARKER, $marker );

		return (int) $post_id;
	}

	/**
	 * Find a single seeded object by its marker value.
	 *
	 * @param string $marker    Marker value.
	 * @param string $post_type Post type to look in.
	 * @return int Post ID, or 0 if none.
	 */
	private function find_seeded( string $marker, string $post_type ): int {
		$ids = get_posts(
			array(
				'post_type'        => $post_type,
				'post_status'      => 'any',
				'posts_per_page'   => 1,
				'fields'           => 'ids',
				'meta_key'         => self::MARKER, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'       => $marker,      // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'suppress_filters' => false,
			)
		);

		return $ids ? (int) $ids[0] : 0;
	}

	/**
	 * Delete every post, page and attachment this seeder created.
	 *
	 * @return void
	 */
	private function unseed(): void {
		$ids = get_posts(
			array(
				'post_type'      => array( 'post', 'page', 'attachment' ),
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'fields'         => 'ids',
				'meta_key'       => self::MARKER, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
			)
		);

		foreach ( $ids as $id ) {
			if ( 'attachment' === get_post_type( $id ) ) {
				wp_delete_attachment( (int) $id, true );
			} else {
				wp_delete_post( (int) $id, true );
			}
		}

		WP_CLI::log( sprintf( 'Removed %d previously seeded item(s).', count( $ids ) ) );
	}
}

WP_CLI::add_command( 'gpxrm', 'Gpxrm_Seeder_Command' );
