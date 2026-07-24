/**
 * Minimal typings for @wordpress/block-editor.
 */
declare module '@wordpress/block-editor' {
	import type { ComponentType, ReactNode } from 'react';

	export function useBlockProps(
		props?: Record< string, unknown >
	): Record< string, unknown >;

	export const InspectorControls: ComponentType< {
		children?: ReactNode;
		group?: string;
	} >;

	export const BlockControls: ComponentType< {
		children?: ReactNode;
		group?: string;
	} >;

	export const MediaUploadCheck: ComponentType< {
		children?: ReactNode;
		fallback?: ReactNode;
	} >;

	export interface MediaUploadSelection {
		id: number;
		url: string;
		[ key: string ]: unknown;
	}

	export const MediaUpload: ComponentType< {
		onSelect: ( media: MediaUploadSelection ) => void;
		allowedTypes?: string[];
		value?: number | number[];
		multiple?: boolean;
		render: ( props: { open: () => void } ) => ReactNode;
	} >;
}
