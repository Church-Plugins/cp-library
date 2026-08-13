/**
 * CP Sermons dashboard.
 *
 * Only the copy buttons need scripting. Everything else on the panel is server
 * rendered so it paints with the page.
 */

import { __ } from '@wordpress/i18n';
import { speak } from '@wordpress/a11y';

/**
 * Put text on the clipboard.
 *
 * navigator.clipboard needs a secure context, and plenty of church sites run
 * their admin over plain http, so fall back rather than silently failing.
 *
 * @param {string} text The text to copy.
 * @return {Promise<boolean>} Whether it worked.
 */
async function copy( text ) {
	try {
		if ( navigator.clipboard && window.isSecureContext ) {
			await navigator.clipboard.writeText( text );
			return true;
		}

		const field = document.createElement( 'textarea' );
		field.value = text;
		field.setAttribute( 'readonly', '' );
		field.style.position = 'fixed';
		field.style.opacity = '0';
		document.body.appendChild( field );
		field.select();
		document.execCommand( 'copy' );
		document.body.removeChild( field );

		return true;
	} catch ( e ) {
		return false;
	}
}

document.addEventListener( 'click', async ( event ) => {
	const button = event.target.closest( '.cpl-copy' );

	if ( ! button || ! button.dataset.copy ) {
		return;
	}

	event.preventDefault();

	if ( ! ( await copy( button.dataset.copy ) ) ) {
		speak( __( 'Could not copy. Select the text and copy it yourself.', 'cp-library' ), 'assertive' );
		return;
	}

	// Swapping the label of the focused button is not reliably announced, so
	// say it outright as well.
	speak( __( 'Copied — now paste it into your page.', 'cp-library' ) );

	const original = button.textContent;
	button.textContent = __( 'Copied', 'cp-library' );
	button.classList.add( 'is-copied' );

	window.setTimeout( () => {
		button.textContent = original;
		button.classList.remove( 'is-copied' );
	}, 2000 );
} );
