import { useEffect, useMemo, useRef } from '@wordpress/element';

class Ref {
	constructor(value, callback) {
		this._current = value;
		this._callback = callback;
	}

	set current( value ) {
		this._callback(value, this._current);
		this._current = value;
	}

	get current() {
		return this._current;
	}
}

export default function useListenerRef(initial, callback) {
	const onChange = (value, oldValue) => {
		callback(value, oldValue)
	}

	const ref = useMemo(() => {
		return new Ref(initial, onChange);
	}, [initial]);

	useEffect(() => {
		ref._callback = callback;
	}, [callback])

	return ref
}
