jQuery( function( $ ){

	const wp_inline_edit_function = false;
	if( "undefined" !== typeof inlineEditPost ) {
		wp_inline_edit_function = inlineEditPost.edit ?? false;
	}

	if( false !== wp_inline_edit_function ) {
		// we overwrite the it with our own
		inlineEditPost.edit = function( post_id ) {

			// let's merge arguments of the original function
			wp_inline_edit_function.apply( this, arguments );

			// get the post ID from the argument
			if ( typeof( post_id ) == 'object' ) { // if it is object, get the ID number
				post_id = parseInt( this.getId( post_id ) );
			}

			// add rows to variables
			const edit_row = $( '#edit-' + post_id )
			const post_row = $( '#inline_' + post_id )

			const sermonTimestamp = $( '.message-timestamp', post_row ).text(); //  remove $ sign

			// populate the inputs with column data
			$( ':input[name="message_timestamp"]', edit_row ).val( sermonTimestamp );
		}
	}

	/**
	 * Toggle the top-level Bible book list
	 * @param {DOMElement} target
	 */
	let toggleList = ( target ) => {
		if( $( target ).hasClass( 'cpl-list-closed' ) ) {
			$( target ).removeClass( 'cpl-list-closed' );
		} else {
			$( target ).addClass( 'cpl-list-closed' );
		}
	}

	/**
	 * Click handler for the Scritpure input list
	 */
	$( '#cpl-scripture-input' ).on(
		'click',
		(event) => {
			event.preventDefault();

			// Another selector is already open
			if( ! $( '#cpl-scripture-list-chapter' ).hasClass( 'cpl-list-closed' ) ) {
				return;
			} else if( ! $( '#cpl-scripture-list-verse' ).hasClass( 'cpl-list-closed' ) ) {
				return;
			}

			// Toggle the list
			let listTarget = $( '#cpl-scripture-list' );
			toggleList( listTarget );
		}
	);

	/**
	 * Click handler for selecting a Bible chapter
	 *
	 * @param {DOMEvent} event
	 */
	let handleChapterSelection = ( event ) => {
		event.preventDefault();

		$( 'cpl-scripture-selection-number' ).removeClass( 'cpl-selected' );

		let target = $( event.target );
		if( ! $( target ).hasClass( 'cpl-scripture-selection-number' ) ) {
			target = $( target ).parents( '.cpl-scripture-selection-number' )[0];
		}
		$( target ).addClass( 'cpl-selected' );

		let currentSelection = $( '#cpl-scripture-current-selection' ).attr( 'data-value' );
		currentSelection = currentSelection + ' ' +  $( target ).attr( 'data-value' );
		let intSelection = parseInt( $( target ).attr( 'data-value' ), 10 );

		$( '#cpl-scripture-list-chapter .cpl-scripture-progress-display').html(
			'<strong>Current Selection</strong>:&nbsp;&nbsp;' + currentSelection
		);
		$( '#cpl-scripture-list-chapter .cpl-scripture-finish-progress').html(
			'<a class="cpl-scripture-cancel-modal" href="#">Cancel</a>' +
			'<div class="preview button">SELECT &apos;' + currentSelection + '&apos;</div>'
		);

		$( '#cpl-scripture-current-selection' ).attr( 'data-value', currentSelection );
		$( '#cpl-scripture-selection-level' ).attr( 'data-value', 'verse' );

		$( '.cpl-scripture-selection-number' ).each(
			( index, element ) => {
				let loopNumber = parseInt( $( element ).attr( 'data-value' ), 10 );
				if( loopNumber < intSelection ) {
					$( element ).addClass( 'disabled' );
				}
			}
		);

		setTimeout(
			() => {
				rebindClickHandlers();
			}, 100
		);

		// TODO: Load the next view and hide this one
	}

	/**
	 * Click handler for removing a previously selecteditem from the Taxonomy input
	 *
	 * @param {DOMEvent} event
	 */
	let removeItem = (event) => {
		event.preventDefault();
		event.stopPropagation();

		let target = $( event.target );

		if( ! $( target ).hasClass( 'cpl-scripture-tag' ) ) {
			target = $( target ).parents( '.cpl-scripture-tag' )[0];
		}
		let targetName = $( target ).html().trim();

		$( target ).remove();
		let listTarget = $( '#cpl-book-list li.cpl-scripture-book[data-name="' + targetName + '"]' );
		if( undefined !== listTarget ) {
			$( listTarget ).removeClass( 'cpl-selected' );
		}
	}

	/**
	 * Click handler for to close all Scripture selection modals
	 *
	 * @param {DOMEvent} event
	 */
	let cancelModal = (event) => {
		event.preventDefault();
		$( '#cpl-scripture-list' ).addClass( 'cpl-list-closed' );
		$( '#cpl-scripture-list-chapter' ).addClass( 'cpl-list-closed' );
		$( '#cpl-scripture-list-verse' ).addClass( 'cpl-list-closed' );
	}

	/**
	 * After uesr has finished selecting a scripture, add it to the list to save
	 *
	 * @param {DOMEvent} event
	 */
	let finalizeScriptureSelection = (event) => {
		event.preventDefault();
		// 3. Save the final selection to the metabox UI
		let target = $( '#cpl-scripture-input' );
		let source =  $( '#cpl-scripture-current-selection' );

		let newElement =
			'<span class="cpl-scripture-tag" data-id="0">' +
				$( source ).attr( 'data-value' ) +
				'<input type="hidden" name="cpl-scripture-tag-selections[]" data-id="0" data-name="' + $( source ).attr( 'data-value' ) + '">' +
			'</span>';
		$( target ).append( newElement );
		cancelModal( event );

		let listTarget = $( '#cpl-book-list li.cpl-scripture-book[data-name="' + $( source ).attr( 'data-value' ) + '"]' );
		if( undefined !== listTarget ) {
			$( listTarget ).addClass( 'cpl-selected' );
		}

		setTimeout(
			() => {
				rebindClickHandlers();
			}, 100
		);
	}

	/**
	 * Since items come and go from the DOM,weneed to re-bindhandlers regularly
	 */
	let rebindClickHandlers = () => {
		$( '.cpl-scripture-cancel-modal' ).off( 'click' );
		$( '.cpl-scripture-cancel-modal' ).on(
			'click',
			(event) => {
				cancelModal( event );
			}
		);

		$( 'li.cpl-scripture-selection-number' ).off( 'click' );
		$( 'li.cpl-scripture-selection-number' ).on(
			'click',
			(event) => {
				handleChapterSelection( event );
			}
		);

		$( '.cpl-scripture-finish-progress .preview.button' ).off( 'click' );
		$( '.cpl-scripture-finish-progress .preview.button' ).on(
			'click',
			(event) => {
				finalizeScriptureSelection( event );
			}
		);

		$( '.cpl-scripture-tag' ).off( 'click' );
		$( '.cpl-scripture-tag' ).on(
			'click',
			(event) => {
				removeItem( event );
			}
		);
	}

	/**
	 * Click handler for selecting a Bible book
	 *
	 * @param {String} inputValue
	 */
	let handlePassgeSelection = ( inputValue ) => {

		// let currentSelection = $( '#cpl-scripture-current-selection' ).attr( 'data-value' );
		let selectionLevel = $( '#cpl-scripture-selection-level' ).attr( 'data-value' );
		// let rootOutputDiv = '#cpl-scripture-list-chapter';

		let headerContent = '';
		let footerContent = '';
		let bodyContent = '';

		// 1. Lookup the chapter list
		if( 'book' === selectionLevel ) {

			// 1.b. Set our progress variables
			$( '#cpl-scripture-current-selection' ).attr( 'data-value', inputValue );
			$( '#cpl-scripture-selection-level' ).attr( 'data-value', 'chapter' );

			// 2. Lookup the chapter list for this book
			let verseCountArray = availableScriptures[ inputValue ]['verse_counts'];
			let numChapters = (undefined !== verseCountArray && verseCountArray.length > 0) ? verseCountArray.length : 0;
			headerContent = '<strong>Current Selection</strong>:&nbsp;&nbsp;' + inputValue;

			// 2.b. Redraw the UI
			bodyContent = '<ul id="cpl-chapter-list">';

			for( let i = 0; i < numChapters; i++ ) {
				let showValue = (i +1).toString();
				bodyContent += '<li class="cpl-scripture-selection-number" data-value="' + showValue + '"> ' + showValue + ' </li>';
			}
			bodyContent += '</ul>';
			footerContent = '<a class="cpl-scripture-cancel-modal" href="#">Cancel</a><div class="preview button">SELECT &apos;' + inputValue + '&apos;</div>';

			$( '#cpl-scripture-list-chapter .cpl-scripture-progress-display').html( headerContent );
			$( '#cpl-scripture-list-chapter .cpl-scripture-progress-content').html( bodyContent );
			$( '#cpl-scripture-list-chapter .cpl-scripture-finish-progress').html( footerContent );

			$( '#cpl-scripture-list' ).addClass( 'cpl-list-closed' );
			$( '#cpl-scripture-list-chapter' ).removeClass( 'cpl-list-closed' );

			setTimeout(
				() => {
					rebindClickHandlers();
				}, 100
			);
		}



		// TODO: 4. Parse and save on WP-post save
	}

	/**
	 * Bible book selected. Sanity check and invoke a handler
	 */
	$( '#cpl-book-list >li' ).on(
		'click',
		(event) => {
			event.preventDefault();

			// `availableScriptures` should have been prepared for us by PHP - sanity check that assumption
			if( undefined === availableScriptures || !availableScriptures || availableScriptures.length < 1 ) {
				return;
			}

			// Normalize the click target
			let target = $( event.target );
			if( !$( target ).hasClass( 'cpl-scripture-book' ) ) {
				target = $( target ).parents( '.cpl-scripture-book' )[0];
			}

			// Get a value for th item that was clicked
			let selectedBook = $( target ).attr( 'data-name' ).trim();

			// This is always a book selection, so we can normalize the hidden inputs - let the handler complete the input updates
			$( '#cpl-scripture-current-selection' ).attr( 'data-value', '' );
			$( '#cpl-scripture-selection-level' ).attr( 'data-value', 'book' );
			handlePassgeSelection( selectedBook );
		}
	);

	/**
	 * Bind Scripture tag removal mechanism on initial page load
	 */
	$( '.cpl-scripture-tag' ).on(
		'click',
		(event) => {
			removeItem( event );
		}
	);


	// BELOW HERE WILL PROBABLY GO AWAY

	// $('.select2-selection__rendered').on(
	// 	'select2:selecting',
	// 	(event) => {
	// 		console.log( "SELECTING" );
	// 	}
	// );

	// $('.select2-selection__rendered').on(
	// 	'click',
	// 	function( event ) {

	// 		event.preventDefault();
	// 		console.log( "OPENED" );

	// 		setTimeout(
	// 			() => {

	// 				console.log( "Timeout" );
	// 				$( '.select2-results__options li' ).off( 'click' );

	// 				console.log( $( '.select2-results__options' ) );

	// 				$( '.select2-results__options li' ).on(
	// 					'click',
	// 					function( event ) {
	// 						event.preventDefault();
	// 						// event.stopPropagation();
	// 						console.log( "SELECTED" );
	// 					}
	// 				);
	// 			}, 500
	// 		);
	// 	}
	// );

});
