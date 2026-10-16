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
	 * It also turns Waypoint posts on, seeds one of each kind (photo marker,
	 * icon marker with and without its own colors, usual marker, and ones a
	 * map must not show) and builds a page whose maps link them from the
	 * block, the GPX file and the shortcode, saying what each map should show.
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

		// First, so that --fresh finds the seeded Waypoint posts too.
		$this->enable_waypoints();

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

		$waypoints = $this->waypoints();
		$page_id   = $this->waypoints_page( $attachments, $waypoints );
		WP_CLI::log( sprintf( '  %-30s page #%d %s', 'Waypoint posts', $page_id, get_permalink( $page_id ) ) );

		WP_CLI::success(
			sprintf(
				'Seeded %d demo maps, %d Waypoint posts, a showcase page and a Waypoint posts page. Browse %s',
				count( $attachments ),
				count( $waypoints ),
				home_url( '/' )
			)
		);
	}

	/**
	 * Turn Waypoint posts on, and register their post type for this run if
	 * it was off when WordPress loaded.
	 *
	 * @return void
	 */
	private function enable_waypoints(): void {
		if ( ! \Gpxrm\Waypoints::enabled() ) {
			update_option( \Gpxrm\Waypoints::OPTION, true );
			WP_CLI::log( 'Turned Waypoint posts on (Settings > GPX Route Map > General).' );
		}

		if ( ! post_type_exists( \Gpxrm\Waypoints::POST_TYPE ) ) {
			( new \Gpxrm\Waypoints() )->register_post_type();
		}
	}

	/**
	 * The Waypoint posts to seed, keyed by slug: one of each kind a map can
	 * show, and two it must not.
	 *
	 * Photos are [title, alt text, sky top color, sky bottom color].
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function waypoint_fixtures(): array {
		return array(
			'refuge'    => array(
				'title'       => 'Trailhead Refuge',
				'description' => "Staffed from June to September, with <strong>40 bunks</strong>.\nBook ahead on <a href=\"https://example.com/\">the refuge's website</a>: it fills up on weekends.",
				'marker'      => 'image',
				'photo'       => array( 'Trailhead Refuge at dawn', 'A mountain ridge under a pink dawn sky', array( 255, 179, 140 ), array( 120, 90, 160 ) ),
			),
			'viewpoint' => array(
				'title'       => 'Crest Viewpoint',
				'description' => 'The best view of the valley. On a clear day you can see the lake.',
				'marker'      => 'icon',
				'icon'        => 'visibility',
				'color'       => '#1d2327',
				'background'  => '#f0b849',
				'photo'       => array( 'View from the crest', 'Blue peaks under a clear midday sky', array( 86, 160, 230 ), array( 200, 228, 250 ) ),
			),
			'spring'    => array(
				'title'       => 'Spring',
				'description' => "Drinkable all year.\nThe last water before the crest.",
				'marker'      => '',
			),
			'meadow'    => array(
				'title'       => 'Picnic Meadow',
				'description' => 'Flat, shaded and out of the wind. An icon marker in the map\'s own colors.',
				'marker'      => 'icon',
				'icon'        => 'palmtree',
			),
			'no-photo'  => array(
				'title'       => 'Photo marker without a photo',
				'description' => 'Its marker is set to Photo, but it has no photo, so the map keeps the usual marker.',
				'marker'      => 'image',
			),
			'draft'     => array(
				'title'       => 'Draft waypoint',
				'status'      => 'draft',
				'description' => 'Not published: a map must never show this.',
				'marker'      => 'icon',
				'icon'        => 'flag',
			),
			'protected' => array(
				'title'       => 'Password-protected waypoint',
				'password'    => 'gpxrm',
				'description' => 'Behind a password: a map must never show this.',
				'marker'      => 'icon',
				'icon'        => 'lock',
			),
		);
	}

	/**
	 * Create or update the Waypoint posts, with their settings saved as their
	 * edit screen saves them, and their photos.
	 *
	 * @return array<string, int> Post IDs keyed by slug.
	 */
	private function waypoints(): array {
		$ids = array();

		foreach ( $this->waypoint_fixtures() as $key => $fixture ) {
			$post_id = $this->upsert(
				'waypoint:' . $key,
				array(
					'post_type'     => \Gpxrm\Waypoints::POST_TYPE,
					'post_title'    => $fixture['title'],
					'post_status'   => $fixture['status'] ?? 'publish',
					'post_password' => $fixture['password'] ?? '',
				)
			);

			$fields = \Gpxrm\Waypoints::sanitize_fields( $fixture );
			$meta   = array(
				\Gpxrm\Waypoints::META_DESCRIPTION => $fields['description'],
				\Gpxrm\Waypoints::META_MARKER      => $fields['marker'],
				\Gpxrm\Waypoints::META_ICON        => $fields['icon'],
				\Gpxrm\Waypoints::META_COLOR       => $fields['color'],
				\Gpxrm\Waypoints::META_BACKGROUND  => $fields['background'],
			);
			foreach ( $meta as $meta_key => $value ) {
				if ( '' === $value ) {
					delete_post_meta( $post_id, $meta_key );
				} else {
					update_post_meta( $post_id, $meta_key, wp_slash( $value ) );
				}
			}

			$photo = isset( $fixture['photo'] ) ? $this->seed_photo( $key, ...$fixture['photo'] ) : 0;
			if ( $photo > 0 ) {
				set_post_thumbnail( $post_id, $photo );
			} else {
				delete_post_thumbnail( $post_id );
			}

			$ids[ $key ] = $post_id;
			WP_CLI::log( sprintf( '  %-30s waypoint #%-4d %s', $fixture['title'], $post_id, '' === $fields['marker'] ? 'usual marker' : $fields['marker'] . ' marker' ) );
		}

		return $ids;
	}

	/**
	 * A generated landscape photo as a Media attachment: a sky in two colors
	 * over two mountain ranges, large enough for every image size.
	 *
	 * @param string          $key    Waypoint slug.
	 * @param string          $title  Attachment title.
	 * @param string          $alt    Alt text.
	 * @param array<int, int> $top    Sky color at the top, as RGB.
	 * @param array<int, int> $bottom Sky color at the horizon, as RGB.
	 * @return int Attachment ID, or 0 when PHP cannot draw one.
	 */
	private function seed_photo( string $key, string $title, string $alt, array $top, array $bottom ): int {
		$existing = $this->find_seeded( 'attachment:photo-' . $key, 'attachment' );
		if ( $existing ) {
			return $existing;
		}

		if ( ! function_exists( 'imagecreatetruecolor' ) || ! function_exists( 'imagejpeg' ) ) {
			WP_CLI::warning( 'PHP has no GD with JPEG support, so the Waypoint posts get no photos.' );
			return 0;
		}

		$width  = 1200;
		$height = 800;
		$image  = imagecreatetruecolor( $width, $height );

		for ( $y = 0; $y < $height; $y++ ) {
			$mix   = $y / $height;
			$color = imagecolorallocate(
				$image,
				(int) round( $top[0] + ( $bottom[0] - $top[0] ) * $mix ),
				(int) round( $top[1] + ( $bottom[1] - $top[1] ) * $mix ),
				(int) round( $top[2] + ( $bottom[2] - $top[2] ) * $mix )
			);
			imageline( $image, 0, $y, $width, $y, $color );
		}
		imagefilledpolygon( $image, array( 0, 800, 0, 470, 230, 260, 430, 430, 690, 180, 960, 460, 1200, 300, 1200, 800 ), imagecolorallocate( $image, 92, 110, 128 ) );
		imagefilledpolygon( $image, array( 0, 800, 0, 620, 300, 470, 560, 640, 840, 500, 1200, 650, 1200, 800 ), imagecolorallocate( $image, 46, 125, 50 ) );

		ob_start();
		imagejpeg( $image, null, 85 );
		$jpeg = (string) ob_get_clean();

		$upload = wp_upload_bits( 'gpxrm-waypoint-' . $key . '.jpg', null, $jpeg );
		if ( ! empty( $upload['error'] ) ) {
			WP_CLI::error( 'Upload failed for the ' . $key . ' photo: ' . $upload['error'] );
		}

		$attachment_id = (int) wp_insert_attachment(
			array(
				'post_mime_type' => 'image/jpeg',
				'post_title'     => $title,
				'post_status'    => 'inherit',
			),
			$upload['file']
		);

		require_once ABSPATH . 'wp-admin/includes/image.php';
		wp_update_attachment_metadata( $attachment_id, wp_generate_attachment_metadata( $attachment_id, $upload['file'] ) );
		update_post_meta( $attachment_id, '_wp_attachment_image_alt', $alt );
		update_post_meta( $attachment_id, self::MARKER, 'attachment:photo-' . $key );

		return $attachment_id;
	}

	/**
	 * A copy of the Alpine Loop that links its waypoints to Waypoint posts
	 * itself, with the lines each Waypoint post's edit screen offers to copy,
	 * as a Media attachment. Written again on every run, as the post IDs it
	 * names change with --fresh.
	 *
	 * @param array<int, int> $links Post IDs by waypoint position, from 0.
	 * @return int Attachment ID.
	 */
	private function linked_gpx( array $links ): int {
		$source   = file_get_contents( __DIR__ . '/sample-data/alpine-loop.gpx' ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
		$position = -1;
		$contents = (string) preg_replace_callback(
			'#</wpt>#',
			static function ( array $match ) use ( $links, &$position ): string {
				++$position;
				return isset( $links[ $position ] ) ? \Gpxrm\Waypoints::gpx_snippet( $links[ $position ] ) . "\n  " . $match[0] : $match[0];
			},
			(string) $source
		);

		$existing = $this->find_seeded( 'attachment:alpine-loop-linked', 'attachment' );
		$file     = $existing ? get_attached_file( $existing ) : false;
		if ( is_string( $file ) && '' !== $file ) {
			file_put_contents( $file, $contents ); // phpcs:ignore WordPress.WP.AlternativeFunctions.file_system_operations_file_put_contents
			return $existing;
		}

		$upload = wp_upload_bits( 'alpine-loop-linked.gpx', null, $contents );
		if ( ! empty( $upload['error'] ) ) {
			WP_CLI::error( 'Upload failed for alpine-loop-linked.gpx: ' . $upload['error'] );
		}

		$attachment_id = (int) wp_insert_attachment(
			array(
				'post_mime_type' => 'application/gpx+xml',
				'post_title'     => 'Alpine Loop (links its waypoints)',
				'post_status'    => 'inherit',
			),
			$upload['file']
		);
		update_post_meta( $attachment_id, self::MARKER, 'attachment:alpine-loop-linked' );

		return $attachment_id;
	}

	/**
	 * Create or update the page that links the Waypoint posts from a block,
	 * a GPX file and a shortcode, saying what each map should show.
	 *
	 * @param array<string, int> $attachments GPX attachment IDs keyed by fixture slug.
	 * @param array<string, int> $waypoints   Waypoint post IDs keyed by slug.
	 * @return int Page ID.
	 */
	private function waypoints_page( array $attachments, array $waypoints ): int {
		$linked = $this->linked_gpx(
			array(
				0 => $waypoints['meadow'],
				1 => $waypoints['no-photo'],
				2 => $waypoints['draft'],
			)
		);

		$sections = array(
			array(
				'Linked in the block',
				'The Alpine Loop, its three waypoints linked in the block\'s Waypoints panel.',
				array(
					'1: Trailhead Refuge, drawn as its photo alone, under the track\'s Start and End pins, as the loop starts and ends there. Its popup shows the photo, then the title and the post\'s two-line description, with bold text and a link.',
					'2: Crest Viewpoint, a dark eye icon on yellow. Its popup shows its photo, though its marker is the icon.',
					'3: Spring, with the usual water marker, and the post\'s title and description in place of the file\'s.',
				),
				$this->map_block( $attachments['alpine-loop'], array( 0 => $waypoints['refuge'], 1 => $waypoints['viewpoint'], 2 => $waypoints['spring'] ) ),
			),
			array(
				'Linked in the GPX file',
				'A copy of the Alpine Loop in the Media Library whose waypoints name Waypoint posts in their extensions. The block links nothing.',
				array(
					'1: Picnic Meadow, a palm tree icon in the map\'s own colors: white on the track\'s green.',
					'2: Photo marker without a photo, with the file\'s usual viewpoint marker (an eye on green), as its post has no photo.',
					'3: Spring, as in the file: it links a draft, which maps never show.',
				),
				$this->map_block( $linked ),
			),
			array(
				'The block wins over the file',
				'The same file, its first waypoint also linked in the block.',
				array(
					'1: Trailhead Refuge from the block, not Picnic Meadow from the file.',
					'2 and 3: as in the map above.',
				),
				$this->map_block( $linked, array( 0 => $waypoints['refuge'] ) ),
			),
			array(
				'Linked by the shortcode',
				sprintf( 'The Two-Stage Hike, with waypoints="1:%d, 2:%d".', $waypoints['viewpoint'], $waypoints['protected'] ),
				array(
					'1: Crest Viewpoint, the eye icon.',
					'2: Stage 2 finish, as in the file: it links a password-protected post, which maps never show.',
				),
				sprintf( "<!-- wp:shortcode -->\n[gpx_route_map id=\"%d\" waypoints=\"1:%d, 2:%d\"]\n<!-- /wp:shortcode -->", $attachments['two-segment'], $waypoints['viewpoint'], $waypoints['protected'] ),
			),
		);

		$content = "<!-- wp:paragraph -->\n<p>Waypoint posts linked to maps in every way a map can link them. Under each map, what its waypoints should show. In the editor, each block's Waypoints panel lists its links.</p>\n<!-- /wp:paragraph -->\n\n";
		foreach ( $sections as $section ) {
			$content .= sprintf( "<!-- wp:heading -->\n<h2 class=\"wp-block-heading\">%s</h2>\n<!-- /wp:heading -->\n\n", esc_html( $section[0] ) );
			$content .= sprintf( "<!-- wp:paragraph -->\n<p>%s</p>\n<!-- /wp:paragraph -->\n\n", esc_html( $section[1] ) );
			$content .= $section[3] . "\n\n";
			$content .= "<!-- wp:list -->\n<ul class=\"wp-block-list\">";
			foreach ( $section[2] as $expected ) {
				$content .= sprintf( "<!-- wp:list-item -->\n<li>%s</li>\n<!-- /wp:list-item -->", esc_html( $expected ) );
			}
			$content .= "</ul>\n<!-- /wp:list -->\n\n";
		}

		return $this->upsert(
			'page:waypoints',
			array(
				'post_type'    => 'page',
				'post_title'   => 'GPX Route Map — Waypoint posts',
				'post_content' => $content,
				'post_status'  => 'publish',
			)
		);
	}

	/**
	 * A map block for a GPX attachment, with the waypoint links the editor
	 * would store: an object keyed by waypoint position.
	 *
	 * @param int             $attachment_id GPX attachment ID.
	 * @param array<int, int> $links         Post IDs by waypoint position, from 0.
	 * @return string
	 */
	private function map_block( int $attachment_id, array $links = array() ): string {
		$atts = array( 'gpxId' => $attachment_id );
		if ( $links ) {
			$atts['waypointPosts'] = (object) $links;
		}

		return sprintf( '<!-- wp:gpx-route-map/map %s /-->', wp_json_encode( $atts ) );
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
				'post_type'      => array( 'post', 'page', 'attachment', \Gpxrm\Waypoints::POST_TYPE ),
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
