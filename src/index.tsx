/**
 * Block registration for gpx-route-map/map.
 */

import { registerBlockType } from '@wordpress/blocks';
import type { BlockConfiguration } from '@wordpress/blocks';

import metadata from './block.json';
import Edit from './edit';
import type { GpxBlockAttributes } from './edit';

registerBlockType(
	metadata as unknown as BlockConfiguration< GpxBlockAttributes >,
	{
		edit: Edit,
		save: () => null,
	}
);
