<?php
/**
 * Unit tests for Gpxrm\Plugin.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

use Gpxrm\Plugin;

/**
 * Write contents to a temp file and return its path.
 *
 * @param string $contents File contents.
 * @return string
 */
function gpxrm_temp_upload( string $contents ): string {
	$path = (string) tempnam( sys_get_temp_dir(), 'gpxrm' );
	file_put_contents( $path, $contents );

	return $path;
}

test(
	'forces the gpx type for genuine GPX content',
	function ( $contents ) {
		$file = gpxrm_temp_upload( $contents );
		$data = ( new Plugin() )->fix_gpx_filetype_check(
			array(
				'ext'             => false,
				'type'            => false,
				'proper_filename' => false,
			),
			$file,
			'route.gpx',
			null
		);
		unlink( $file );

		expect( $data['ext'] )->toBe( 'gpx' );
		expect( $data['type'] )->toBe( 'application/gpx+xml' );
	}
)->with(
	array(
		'plain gpx root'  => '<gpx xmlns="http://www.topografix.com/GPX/1/1"><trk></trk></gpx>',
		'bom and prolog'  => "\xEF\xBB\xBF" . '<?xml version="1.0" encoding="UTF-8"?>' . "\n" . '<gpx version="1.1"><wpt lat="0" lon="0"/></gpx>',
		'leading comment' => '<?xml version="1.0"?><!-- exported --><gpx creator="x"></gpx>',
	)
);

test(
	'refuses to force the type for non-GPX content named .gpx',
	function ( $contents ) {
		$file     = gpxrm_temp_upload( $contents );
		$original = array(
			'ext'             => false,
			'type'            => false,
			'proper_filename' => false,
		);
		$data     = ( new Plugin() )->fix_gpx_filetype_check( $original, $file, 'payload.gpx', null );
		unlink( $file );

		expect( $data )->toBe( $original );
	}
)->with(
	array(
		'html'        => '<!doctype html><html><body><script>alert(1)</script></body></html>',
		'svg'         => '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>',
		'plain text'  => 'just some text',
		'empty file'  => '',
		'php script'  => '<?php echo "hi";',
	)
);

test(
	'leaves non-gpx filenames untouched even with GPX content',
	function () {
		$file     = gpxrm_temp_upload( '<gpx version="1.1"></gpx>' );
		$original = array(
			'ext'             => 'xml',
			'type'            => 'text/xml',
			'proper_filename' => false,
		);
		$data     = ( new Plugin() )->fix_gpx_filetype_check( $original, $file, 'route.xml', null );
		unlink( $file );

		expect( $data )->toBe( $original );
	}
);

test(
	'sniffer rejects unreadable or missing files',
	function () {
		expect( Plugin::looks_like_gpx( '' ) )->toBeFalse();
		expect( Plugin::looks_like_gpx( sys_get_temp_dir() . '/gpxrm-does-not-exist.gpx' ) )->toBeFalse();
	}
);
