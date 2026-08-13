/**
 * CP Sermons dashboard.
 *
 * Only the copy buttons need scripting. Everything else on the panel is server
 * rendered so it paints with the page.
 */

document.addEventListener( 'click', async ( event ) => {
	const button = event.target.closest( '.cpl-copy' );

	if ( ! button ) {
		return;
	}

	event.preventDefault();

	const text = button.dataset.copy;

	if ( ! text ) {
		return;
	}

	try {
		// navigator.clipboard needs a secure context. Plenty of church sites run
		// their admin over plain http, so fall back rather than silently failing.
		if ( navigator.clipboard && window.isSecureContext ) {
			await navigator.clipboard.writeText( text );
		} else {
			const field = document.createElement( 'textarea' );
			field.value = text;
			field.setAttribute( 'readonly', '' );
			field.style.position = 'fixed';
			field.style.opacity = '0';
			document.body.appendChild( field );
			field.select();
			document.execCommand( 'copy' );
			document.body.removeChild( field );
		}
	} catch ( e ) {
		return;
	}

	const original = button.textContent;
	button.textContent = button.dataset.copied || 'Copied';
	button.classList.add( 'is-copied' );

	window.setTimeout( () => {
		button.textContent = original;
		button.classList.remove( 'is-copied' );
	}, 2000 );
} );
