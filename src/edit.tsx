/**
 * Editor UI for the GPX Route Map block.
 */

import type { KeyboardEvent } from 'react';

import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useRef, useState } from '@wordpress/element';
import { useSelect } from '@wordpress/data';
import type { BlockEditProps } from '@wordpress/blocks';
import {
	useBlockProps,
	InspectorControls,
	MediaUpload,
	MediaUploadCheck,
	BlockControls,
} from '@wordpress/block-editor';
import {
	PanelBody,
	RangeControl,
	TextControl,
	Button,
	Placeholder,
	ToolbarGroup,
	ToolbarButton,
	ExternalLink,
	SelectControl,
	ToggleControl,
	CheckboxControl,
	Notice,
} from '@wordpress/components';

import {
	resolveHeights,
	siteHeights,
	MIN_HEIGHT,
	MAX_HEIGHT,
	DEFAULT_HEIGHT,
} from './heights';
import { siteDefaultMaxZoom, MIN_ZOOM, MAX_ZOOM } from './zoom';
import { tileUrlProblem } from './tile-url';
import { parseGPX } from './view/map-core';
import { routeStats } from './view/stats';

/** Summary stats computed in the browser and stored on the block. */
export type GpxStats = {
	distance: number;
	gain: number;
	loss: number;
	max: number;
	waypoints: number;
};

/**
 * Attributes as declared in block.json. A type alias (not an interface) so it
 * satisfies the Record<string, unknown> constraint of BlockEditProps.
 */
export type GpxBlockAttributes = {
	gpxId?: number;
	gpxUrl: string;
	height: number;
	heightTablet: number;
	heightMobile: number;
	showStats: string;
	showElevation: string;
	showDownload: string;
	statFields: string;
	maxZoom: number;
	tileUrl: string;
	units: string;
	stats?: GpxStats;
};

/**
 * The stats the bar can list, in display order. Mirrors Renderer::STAT_FIELDS.
 */
const STAT_FIELDS: Array< { key: string; label: string } > = [
	{ key: 'distance', label: __( 'Distance', 'gpx-route-map' ) },
	{ key: 'gain', label: __( 'Elevation gain', 'gpx-route-map' ) },
	{ key: 'loss', label: __( 'Elevation loss', 'gpx-route-map' ) },
	{ key: 'max', label: __( 'Max elevation', 'gpx-route-map' ) },
	{ key: 'waypoints', label: __( 'Waypoints', 'gpx-route-map' ) },
];

/**
 * Parse the stored stat list.
 *
 * `''` means "follow the site setting", which the caller checks before asking
 * for the list, so an empty result here only means nothing recognisable.
 *
 * @param value Stored attribute value.
 */
function parseStatFields( value: string ): string[] {
	return value
		.split( ',' )
		.map( ( k ) => k.trim() )
		.filter( ( k ) => STAT_FIELDS.some( ( f ) => f.key === k ) );
}

/**
 * Serialise a stat selection back to the attribute.
 *
 * @param keys Selected stat keys.
 */
function serialiseStatFields( keys: string[] ): string {
	const ordered = STAT_FIELDS.filter( ( f ) => keys.includes( f.key ) ).map(
		( f ) => f.key
	);
	// Never empty: whether the bar appears is the Stats bar setting's job, so
	// the last remaining figure cannot be unticked.
	return ordered.length
		? ordered.join( ',' )
		: STAT_FIELDS.map( ( f ) => f.key ).join( ',' );
}

/** Choices for the panels that can defer to the site setting. */
const VISIBILITY_OPTIONS = [
	{ label: __( 'Site default', 'gpx-route-map' ), value: '' },
	{ label: __( 'Shown', 'gpx-route-map' ), value: 'show' },
	{ label: __( 'Hidden', 'gpx-route-map' ), value: 'hide' },
];

/**
 * Normalize a panel visibility attribute for the select.
 *
 * These were booleans before the site setting existed, so blocks saved by an
 * older version still hand us true/false here.
 *
 * @param value Stored attribute value.
 */
function visibilityChoice( value: string | boolean ): string {
	if ( true === value ) {
		return 'show';
	}
	if ( false === value ) {
		return 'hide';
	}
	return 'show' === value || 'hide' === value ? value : '';
}

/** The subset of the media object the picker hands to onSelect. */
interface SelectedMedia {
	id: number;
	url: string;
}

/**
 * GPX uploaded while the plugin is active gets application/gpx+xml, but files
 * uploaded before activation or imported, are stored as generic XML
 */
const GPX_TYPES = [ 'application/gpx+xml', 'application/xml', 'text/xml' ];

/**
 * Whether two computed stat sets are identical, so the attribute is only
 * written when the numbers actually change (avoids marking a clean post dirty
 * when a saved block is reopened).
 *
 * @param a Existing stats.
 * @param b Freshly computed stats.
 */
function sameStats( a: GpxStats | undefined, b: GpxStats ): boolean {
	return (
		!! a &&
		a.distance === b.distance &&
		a.gain === b.gain &&
		a.loss === b.loss &&
		a.max === b.max &&
		a.waypoints === b.waypoints
	);
}

/**
 * Block edit component.
 *
 * @param props               Block props.
 * @param props.attributes    Block attributes.
 * @param props.setAttributes Attribute setter.
 */
export default function Edit( {
	attributes,
	setAttributes,
}: BlockEditProps< GpxBlockAttributes > ) {
	const {
		gpxId,
		gpxUrl,
		height,
		heightTablet,
		heightMobile,
		showStats,
		showElevation,
		showDownload,
		statFields,
		maxZoom,
		tileUrl,
		units,
	} = attributes;
	const blockProps = useBlockProps();
	const hasGpx = !! gpxUrl;

	// The summary below only labels what this block is set to. A panel left on
	// "Site default" is listed, since showing is the shipped default.
	const statsShown = 'hide' !== visibilityChoice( showStats );
	const elevationShown = 'hide' !== visibilityChoice( showElevation );

	// Draft state for the URL field: typing must not discard a selected
	// media file, so the attribute only updates on blur or Enter.
	const [ urlDraft, setUrlDraft ] = useState< string | null >( null );
	const committedUrl = gpxId ? '' : gpxUrl;

	const mediaUrl = useSelect(
		( select ): string | undefined => {
			if ( ! gpxId ) {
				return undefined;
			}
			const core = select( 'core' ) as {
				getEntityRecord: (
					kind: string,
					name: string,
					id: number
				) => { source_url?: string } | undefined;
			};
			return core.getEntityRecord( 'postType', 'attachment', gpxId )
				?.source_url;
		},
		[ gpxId ]
	);
	const site = siteHeights();
	const siteMaxZoom = siteDefaultMaxZoom();
	const usesSiteHeight = ! height && ! heightTablet && ! heightMobile;
	const resolved = resolveHeights(
		{ base: height, tablet: heightTablet, mobile: heightMobile },
		site
	);
	const siteHeight = site.base;
	const effectiveHeight = resolved.base;

	const bakeUrl = gpxUrl || mediaUrl || '';

	// The effect below writes stats, so it must not depend on them: listing
	// them re-runs it on its own write, which fetches every GPX file twice on
	// every editor load (measured - the sameStats guard stops it there, so it
	// is waste rather than a loop). Reading through refs keeps the dependency
	// list honestly limited to the URL, and also stops the async comparison
	// from testing against a value that went stale while the fetch was in
	// flight.
	const statsRef = useRef( attributes.stats );
	statsRef.current = attributes.stats;

	const setAttributesRef = useRef( setAttributes );
	setAttributesRef.current = setAttributes;

	useEffect( () => {
		if ( ! bakeUrl ) {
			if ( statsRef.current ) {
				setAttributesRef.current( { stats: undefined } );
			}
			return;
		}
		let cancelled = false;
		( async () => {
			try {
				const response = await fetch( bakeUrl );
				if ( ! response.ok ) {
					throw new Error( 'HTTP ' + response.status );
				}
				const parsed = parseGPX( await response.text() );
				if ( parsed.invalid || parsed.coords.length < 2 ) {
					throw new Error( 'unparseable' );
				}
				const s = routeStats(
					parsed.coords,
					new Set( parsed.segmentStarts )
				);
				const next: GpxStats = {
					distance: s.distance,
					gain: s.gain,
					loss: s.loss,
					max: s.maxEle,
					waypoints: parsed.waypoints.length,
				};
				if ( ! cancelled && ! sameStats( statsRef.current, next ) ) {
					setAttributesRef.current( { stats: next } );
				}
			} catch {
				if ( ! cancelled && statsRef.current ) {
					setAttributesRef.current( { stats: undefined } );
				}
			}
		} )();
		return () => {
			cancelled = true;
		};
	}, [ bakeUrl ] );

	const commitUrl = () => {
		if ( null === urlDraft ) {
			return;
		}
		const value = urlDraft.trim();
		setUrlDraft( null );
		if ( '' === value ) {
			// An emptied field never discards a selected media file.
			if ( ! gpxId && gpxUrl ) {
				setAttributes( { gpxUrl: '' } );
			}
			return;
		}
		if ( value !== committedUrl ) {
			setAttributes( { gpxUrl: value, gpxId: undefined } );
		}
	};

	const onSelect = ( media: SelectedMedia ) => {
		setUrlDraft( null );
		setAttributes( { gpxId: media.id, gpxUrl: media.url } );
	};

	const fileName = gpxUrl ? gpxUrl.split( '/' ).pop() : '';

	const urlHelp = __(
		'Paste a direct link to a .gpx file. The file must be hosted on this site, or on a host that allows cross-origin (CORS) requests — most external sites do not.',
		'gpx-route-map'
	);
	const mediaNotice =
		gpxId && fileName
			? sprintf(
					/* translators: %s: selected GPX file name. */
					__(
						'Currently using the selected media file "%s". Entering a URL here replaces it (applied on Enter or when leaving the field).',
						'gpx-route-map'
					),
					fileName
			  )
			: '';

	return (
		<div { ...blockProps }>
			<InspectorControls>
				<PanelBody title={ __( 'Source', 'gpx-route-map' ) }>
					<MediaUploadCheck>
						<MediaUpload
							onSelect={ onSelect }
							allowedTypes={ GPX_TYPES }
							value={ gpxId }
							render={ ( { open } ) => (
								<Button variant="secondary" onClick={ open }>
									{ hasGpx
										? __(
												'Replace GPX file',
												'gpx-route-map'
										  )
										: __(
												'Select GPX file',
												'gpx-route-map'
										  ) }
								</Button>
							) }
						/>
					</MediaUploadCheck>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'or GPX file URL', 'gpx-route-map' ) }
						help={
							mediaNotice ? mediaNotice + ' ' + urlHelp : urlHelp
						}
						value={ urlDraft ?? committedUrl }
						onChange={ setUrlDraft }
						onBlur={ commitUrl }
						onKeyDown={ (
							event: KeyboardEvent< HTMLInputElement >
						) => {
							if ( 'Enter' === event.key ) {
								commitUrl();
							}
						} }
						placeholder="https://example.com/route.gpx"
					/>
				</PanelBody>

				<PanelBody title={ __( 'Display', 'gpx-route-map' ) }>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Use site default height',
							'gpx-route-map'
						) }
						help={ sprintf(
							/* translators: %d: height in pixels. */
							__(
								'Follow the height set under Settings → GPX Route Map (%d px).',
								'gpx-route-map'
							),
							siteHeight
						) }
						checked={ usesSiteHeight }
						onChange={ ( useDefault ) =>
							setAttributes(
								useDefault
									? {
											height: 0,
											heightTablet: 0,
											heightMobile: 0,
									  }
									: { height: siteHeight }
							)
						}
					/>
					{ ! usesSiteHeight && (
						<>
							<RangeControl
								__nextHasNoMarginBottom
								__next40pxDefaultSize
								label={ __(
									'All screens (px)',
									'gpx-route-map'
								) }
								value={ resolved.base }
								onChange={ ( value ) =>
									setAttributes( {
										height: value ?? DEFAULT_HEIGHT,
									} )
								}
								min={ MIN_HEIGHT }
								max={ MAX_HEIGHT }
								step={ 10 }
							/>
							{ (
								[
									[
										'heightTablet',
										__( 'Tablet (px)', 'gpx-route-map' ),
										__(
											'Applies at 782px and below. Reset to follow the site setting, or the height above when the site has none.',
											'gpx-route-map'
										),
										heightTablet,
										resolved.tablet,
									],
									[
										'heightMobile',
										__( 'Mobile (px)', 'gpx-route-map' ),
										__(
											'Applies at 480px and below. Reset to follow the site setting, or the tablet height when the site has none.',
											'gpx-route-map'
										),
										heightMobile,
										resolved.mobile,
									],
								] as Array<
									[ string, string, string, number, number ]
								>
							 ).map( ( [ key, label, hint, own, shown ] ) => (
								<RangeControl
									key={ key }
									__nextHasNoMarginBottom
									__next40pxDefaultSize
									label={ label }
									help={ hint }
									// The inherited value, so the slider
									// shows what the band actually renders
									// at rather than a misleading 0.
									value={ shown }
									onChange={ ( value ) =>
										setAttributes( {
											[ key ]: value ?? 0,
										} )
									}
									min={ MIN_HEIGHT }
									max={ MAX_HEIGHT }
									step={ 10 }
									// Reset hands the change handler undefined,
									// which the `?? 0` above turns back into
									// "inherit". Passing resetFallbackValue
									// here would be identical to omitting it.
									allowReset
									// Dimmed while it is only inheriting.
									className={
										own
											? undefined
											: 'gpxrm-inherited-range'
									}
								/>
							) ) }
						</>
					) }
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Stats bar', 'gpx-route-map' ) }
						value={ visibilityChoice( showStats ) }
						options={ VISIBILITY_OPTIONS }
						onChange={ ( value ) =>
							setAttributes( { showStats: value } )
						}
					/>
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Elevation profile', 'gpx-route-map' ) }
						value={ visibilityChoice( showElevation ) }
						options={ VISIBILITY_OPTIONS }
						onChange={ ( value ) =>
							setAttributes( { showElevation: value } )
						}
					/>
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Download button', 'gpx-route-map' ) }
						help={ __(
							'Adds a button on the map for visitors to download the GPX file.',
							'gpx-route-map'
						) }
						value={ visibilityChoice( showDownload ) }
						options={ VISIBILITY_OPTIONS }
						onChange={ ( value ) =>
							setAttributes( { showDownload: value } )
						}
					/>
					<SelectControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Units', 'gpx-route-map' ) }
						help={ __(
							'Distance and elevation units for this map.',
							'gpx-route-map'
						) }
						value={ units }
						options={ [
							{
								label: __( 'Site default', 'gpx-route-map' ),
								value: '',
							},
							{
								label: __( 'Metric (km / m)', 'gpx-route-map' ),
								value: 'metric',
							},
							{
								label: __(
									'Imperial (mi / ft)',
									'gpx-route-map'
								),
								value: 'imperial',
							},
						] }
						onChange={ ( value ) =>
							setAttributes( { units: value } )
						}
					/>
					<RangeControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Max zoom', 'gpx-route-map' ) }
						help={ sprintf(
							/* translators: %d: zoom level. */
							__(
								'Reset to follow the site setting (%d).',
								'gpx-route-map'
							),
							siteMaxZoom
						) }
						// The inherited value, so the slider shows what the map
						// actually uses rather than a meaningless 0.
						value={ maxZoom || siteMaxZoom }
						onChange={ ( value ) =>
							// Reset hands back undefined, which is how the map
							// returns to following the site.
							setAttributes( { maxZoom: value ?? 0 } )
						}
						min={ MIN_ZOOM }
						max={ MAX_ZOOM }
						allowReset
						className={
							maxZoom ? undefined : 'gpxrm-inherited-range'
						}
					/>
				</PanelBody>

				{ /* Choosing what goes in a bar that is switched off has no
				     meaning, so the panel goes away with it. */ }
				{ 'hide' !== visibilityChoice( showStats ) && (
					<PanelBody
						title={ __( 'Stats bar items', 'gpx-route-map' ) }
						initialOpen={ false }
					>
						<ToggleControl
							__nextHasNoMarginBottom
							label={ __( 'Use site default', 'gpx-route-map' ) }
							help={ __(
								'Follow the stats chosen under Settings → GPX Route Map.',
								'gpx-route-map'
							) }
							checked={ '' === statFields }
							onChange={ ( useDefault ) =>
								setAttributes( {
									statFields: useDefault
										? ''
										: serialiseStatFields(
												STAT_FIELDS.map(
													( f ) => f.key
												)
										  ),
								} )
							}
						/>
						{ '' !== statFields &&
							STAT_FIELDS.map( ( field ) => {
								const selected = parseStatFields( statFields );
								const isChecked = selected.includes(
									field.key
								);
								return (
									<CheckboxControl
										__nextHasNoMarginBottom
										key={ field.key }
										label={ field.label }
										checked={ isChecked }
										// The last one stays ticked: emptying the
										// list is not how the bar is hidden.
										disabled={
											isChecked && 1 === selected.length
										}
										onChange={ ( checked ) =>
											setAttributes( {
												statFields: serialiseStatFields(
													checked
														? [
																...selected,
																field.key,
														  ]
														: selected.filter(
																( k ) =>
																	k !==
																	field.key
														  )
												),
											} )
										}
									/>
								);
							} ) }
					</PanelBody>
				) }

				<PanelBody
					title={ __( 'Map tiles', 'gpx-route-map' ) }
					initialOpen={ false }
				>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Custom tile URL', 'gpx-route-map' ) }
						help={ __(
							'Raster tile template with {z}/{x}/{y}, over https. Add {ratio} where the provider expects a retina suffix, for example {z}/{x}/{y}{ratio}.png. Leave blank to use OpenStreetMap. Public OSM tiles are rate-limited — use your own provider for busy sites.',
							'gpx-route-map'
						) }
						value={ tileUrl }
						onChange={ ( value ) =>
							setAttributes( { tileUrl: value } )
						}
						placeholder="https://tile.openstreetmap.org/{z}/{x}/{y}.png"
					/>
					{ ( () => {
						const problem = tileUrlProblem(
							tileUrl,
							window.location.protocol
						);

						if ( ! problem ) {
							return null;
						}

						return (
							<Notice status="warning" isDismissible={ false }>
								{ 'insecure' === problem
									? __(
											'This address is not secure, so visitors\u2019 browsers will refuse to load the tiles and the map will appear blank. Use an https address, unless the tile server runs on the same machine as the browser.',
											'gpx-route-map'
									  )
									: __(
											'This address is missing http:// or https://, so it will be ignored and the map will fall back to OpenStreetMap.',
											'gpx-route-map'
									  ) }
							</Notice>
						);
					} )() }
				</PanelBody>
			</InspectorControls>

			{ hasGpx && (
				<BlockControls>
					<ToolbarGroup>
						<MediaUploadCheck>
							<MediaUpload
								onSelect={ onSelect }
								allowedTypes={ GPX_TYPES }
								value={ gpxId }
								render={ ( { open } ) => (
									<ToolbarButton onClick={ open }>
										{ __( 'Replace GPX', 'gpx-route-map' ) }
									</ToolbarButton>
								) }
							/>
						</MediaUploadCheck>
					</ToolbarGroup>
				</BlockControls>
			) }

			{ ! hasGpx ? (
				<Placeholder
					icon="location-alt"
					label={ __( 'GPX Route Map', 'gpx-route-map' ) }
					instructions={ __(
						'Select a GPX track to render it as an interactive map with an elevation profile.',
						'gpx-route-map'
					) }
				>
					<MediaUploadCheck>
						<MediaUpload
							onSelect={ onSelect }
							allowedTypes={ GPX_TYPES }
							value={ gpxId }
							render={ ( { open } ) => (
								<Button variant="primary" onClick={ open }>
									{ __( 'Select GPX file', 'gpx-route-map' ) }
								</Button>
							) }
						/>
					</MediaUploadCheck>
				</Placeholder>
			) : (
				<div
					className="gpxrm-editor-card"
					style={ { minHeight: Math.min( effectiveHeight, 320 ) } }
				>
					<span className="gpxrm-editor-icon">🗺️</span>
					<strong className="gpxrm-editor-title">
						{ __( 'GPX Route Map', 'gpx-route-map' ) }
					</strong>
					<span className="gpxrm-editor-file">{ fileName }</span>
					<span className="gpxrm-editor-note">
						{ __(
							'The interactive map renders on the front end.',
							'gpx-route-map'
						) }
					</span>
					<span className="gpxrm-editor-meta">
						{ statsShown && __( 'Stats', 'gpx-route-map' ) }
						{ statsShown && elevationShown && ' · ' }
						{ elevationShown &&
							__( 'Elevation profile', 'gpx-route-map' ) }
					</span>
					<ExternalLink href={ gpxUrl } className="gpxrm-editor-link">
						{ __( 'Open GPX file', 'gpx-route-map' ) }
					</ExternalLink>
				</div>
			) }
		</div>
	);
}
