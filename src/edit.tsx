/**
 * Editor UI for the GPX Route Map block.
 */

import type { KeyboardEvent } from 'react';

import { __, sprintf } from '@wordpress/i18n';
import { useEffect, useState } from '@wordpress/element';
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
	ToggleControl,
	TextControl,
	Button,
	Placeholder,
	ToolbarGroup,
	ToolbarButton,
	ExternalLink,
	SelectControl,
} from '@wordpress/components';

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
	showStats: boolean;
	showElevation: boolean;
	maxZoom: number;
	tileUrl: string;
	units: string;
	stats?: GpxStats;
};

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
		showStats,
		showElevation,
		maxZoom,
		tileUrl,
		units,
	} = attributes;
	const blockProps = useBlockProps();
	const hasGpx = !! gpxUrl;

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
	const bakeUrl = gpxUrl || mediaUrl || '';

	useEffect( () => {
		if ( ! bakeUrl ) {
			if ( attributes.stats ) {
				setAttributes( { stats: undefined } );
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
				if ( ! cancelled && ! sameStats( attributes.stats, next ) ) {
					setAttributes( { stats: next } );
				}
			} catch {
				if ( ! cancelled && attributes.stats ) {
					setAttributes( { stats: undefined } );
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
					<RangeControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Map height (px)', 'gpx-route-map' ) }
						value={ height }
						onChange={ ( value ) =>
							setAttributes( { height: value } )
						}
						min={ 200 }
						max={ 1200 }
						step={ 20 }
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __( 'Show stats bar', 'gpx-route-map' ) }
						checked={ showStats }
						onChange={ ( value ) =>
							setAttributes( { showStats: value } )
						}
					/>
					<ToggleControl
						__nextHasNoMarginBottom
						label={ __(
							'Show elevation profile',
							'gpx-route-map'
						) }
						checked={ showElevation }
						onChange={ ( value ) =>
							setAttributes( { showElevation: value } )
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
						value={ maxZoom }
						onChange={ ( value ) =>
							setAttributes( { maxZoom: value } )
						}
						min={ 1 }
						max={ 22 }
					/>
				</PanelBody>

				<PanelBody
					title={ __( 'Map tiles', 'gpx-route-map' ) }
					initialOpen={ false }
				>
					<TextControl
						__nextHasNoMarginBottom
						__next40pxDefaultSize
						label={ __( 'Custom tile URL', 'gpx-route-map' ) }
						help={ __(
							'Raster tile template with {z}/{x}/{y}. Leave blank to use OpenStreetMap. Public OSM tiles are rate-limited — use your own provider for busy sites.',
							'gpx-route-map'
						) }
						value={ tileUrl }
						onChange={ ( value ) =>
							setAttributes( { tileUrl: value } )
						}
						placeholder="https://tile.openstreetmap.org/{z}/{x}/{y}.png"
					/>
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
					style={ { minHeight: Math.min( height, 320 ) } }
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
						{ showStats && __( 'Stats', 'gpx-route-map' ) }
						{ showStats && showElevation && ' · ' }
						{ showElevation &&
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
