

/**
 * Memoize a request
 */
export default function memoizeRequestRoute( callback ) {
	const cache = new Map();

	return async function( route, ...args ) {
		const key = route

		if ( cache.has( key ) ) {
			return cache.get( key );
		}

		const result = await callback( ...args );

		cache.set( key, result );

		return result;
	};
}