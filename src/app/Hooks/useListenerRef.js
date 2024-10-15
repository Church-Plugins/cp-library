import { useCallback, useRef } from '@wordpress/element';

export default function useListenerRef(initial, callback) {
	const ref = useRef(initial)

	const setRef = useCallback((value) => {
		const oldValue = ref.current
		ref.current = value
		callback(value, oldValue)
	}, [])

	return [ref, setRef]
}
