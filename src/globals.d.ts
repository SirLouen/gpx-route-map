declare module '*.css';

/** Vite's `?worker&url` suffix resolves to the emitted worker's URL. */
declare module '*?worker&url' {
	const url: string;
	export default url;
}
