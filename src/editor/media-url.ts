/**
 * The address of the block's GPX file in the Media Library.
 */

import { useSelect } from '@wordpress/data';

/**
 * The address of a Media Library file, once the editor has loaded it.
 *
 * Read in the view context: the edit context answers only those who may
 * edit the file, so an Author could not read one someone else uploaded.
 *
 * @param gpxId The file's attachment ID, if the block has one.
 * @return Its address, or undefined while loading or without a file.
 */
export function useMediaUrl( gpxId: number | undefined ): string | undefined {
	return useSelect(
		( select ): string | undefined => {
			if ( ! gpxId ) {
				return undefined;
			}
			const core = select( 'core' ) as {
				getEntityRecord: (
					kind: string,
					name: string,
					id: number,
					query: Record< string, string >
				) => { source_url?: string } | undefined;
			};
			return core.getEntityRecord( 'postType', 'attachment', gpxId, {
				context: 'view',
			} )?.source_url;
		},
		[ gpxId ]
	);
}
