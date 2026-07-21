<?php
/**
 * Unit tests for Gpxrm\Renderer.
 *
 * @package GpxRouteMap
 */

declare( strict_types=1 );

use Gpxrm\Renderer;

test(
	'keeps tile URL templates with placeholders intact',
	function ( $template ) {
		expect( Renderer::sanitize_tile_url( $template ) )->toBe( $template );
	}
)->with(
	array(
		'basic xyz'            => 'https://tiles.example.com/{z}/{x}/{y}.png',
		'retina suffix'        => 'https://tiles.example.com/{z}/{x}/{y}@2x.png',
		'subdomain shard'      => 'https://{s}.tile.example.com/{z}/{x}/{y}.png',
		'query string params'  => 'https://tile.example.com/wmts?layer=osm&z={z}&x={x}&y={y}&key=abc123',
		'tms inverted y'       => 'https://tiles.example.com/{z}/{x}/{-y}.png',
		'local dev server'     => 'http://localhost:8081/tiles/{z}/{x}/{y}.png',
		'plain http'           => 'http://tiles.example.com/{z}/{x}/{y}.png',
		'default osm template' => 'https://tile.openstreetmap.org/{z}/{x}/{y}.png',
	)
);

test(
	'trims surrounding whitespace from the template',
	function () {
		expect( Renderer::sanitize_tile_url( "  https://tiles.example.com/{z}/{x}/{y}.png\n" ) )
			->toBe( 'https://tiles.example.com/{z}/{x}/{y}.png' );
	}
);

test(
	'rejects invalid tile URL templates',
	function ( $raw ) {
		expect( Renderer::sanitize_tile_url( $raw ) )->toBe( '' );
	}
)->with(
	array(
		'empty string'      => '',
		'whitespace only'   => '   ',
		'not a url'         => 'tiles please',
		'javascript scheme' => 'javascript:alert(1)',
		'data scheme'       => 'data:text/html,x',
		'ftp scheme'        => 'ftp://tiles.example.com/{z}/{x}/{y}.png',
		'protocol relative' => '//tiles.example.com/{z}/{x}/{y}.png',
		'missing host'      => 'https:///{z}/{x}/{y}.png',
		'non string'        => 42,
		'null'              => null,
	)
);
