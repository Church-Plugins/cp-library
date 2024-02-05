jQuery( function( $ ){

	let wp_inline_edit_function = false;
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

	// Bind click handlers on initial pageload

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

	// Functionized click handlers

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
	 * Click handler for removing a previously selected item from the Taxonomy input
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
		let targetName = $( target ).html().trim().replace( /(<([^>]+)>)/gi, '' );

		$( target ).remove();
		let listTarget = $( '#cpl-book-list li.cpl-scripture-book[data-name="' + targetName + '"]' );
		if( undefined !== listTarget ) {
			$( listTarget ).removeClass( 'cpl-selected' );
		}
		tinymce.activeEditor.isNotDirty = false;
	}

	/**
	 * Click handler to close all Scripture selection modals
	 *
	 * @param {DOMEvent} event
	 */
	let cancelModal = (event) => {
		event?.preventDefault();
		$( '#cpl-scripture-list' ).addClass( 'cpl-list-closed' );
		$( '#cpl-scripture-list-chapter' ).addClass( 'cpl-list-closed' );
		$( '#cpl-scripture-list-verse' ).addClass( 'cpl-list-closed' );
	}

	/**
	 * After user has finished selecting a scripture, add it to the list to save
	 *
	 * @param {DOMEvent} event
	 */
	let finalizeScriptureSelection = (event) => {
		event.preventDefault();
		// Save the final selection to the metabox UI
		let target = $( '#cpl-scripture-input' );
		let source =  $( '#cpl-scripture-current-selection' );

		let newElement =
			'<span class="cpl-scripture-tag" data-id="0">' +
				$( source ).attr( 'data-value' ) +
				'<input type="hidden" name="cpl-scripture-tag-selections[]" data-id="0" data-name="' + $( source ).attr( 'data-value' ) + '" value="' + $( source ).attr( 'data-value' ) + '">' +
			'</span>';
		$( target ).append( newElement );
		cancelModal( event );

		let listTarget = $( '#cpl-book-list li.cpl-scripture-book[data-name="' + $( source ).attr( 'data-value' ) + '"]' );
		if( undefined !== listTarget ) {
			$( listTarget ).addClass( 'cpl-selected' );
		}

		tinymce.activeEditor.isNotDirty = false;

		setTimeout(
			() => {
				rebindClickHandlers();
			}, 100
		);


	}

	// Complicated handlers for end-user workflow checkpoints

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

		// Lookup the chapter list
		if( 'book' === selectionLevel ) {

			// Set our progress variables
			$( '#cpl-scripture-current-selection' ).attr( 'data-value', inputValue );
			$( '#cpl-scripture-selection-level' ).attr( 'data-value', 'chapter' );
			$( '#cpl-scripture-current-selection-book' ).attr( 'data-value', inputValue );

			// Lookup the chapter list for this book
			let verseCountArray = availableScriptures[ inputValue ]['verse_counts'];
			let numChapters = (undefined !== verseCountArray && verseCountArray.length > 0) ? verseCountArray.length : 0;
			headerContent = '<strong>Current Selection</strong>:&nbsp;&nbsp;' + inputValue;

			// Redraw the UI
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
	}

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

		let selectedBook = $( '#cpl-scripture-current-selection-book' ).attr( 'data-value' );
		let currentSelection = selectedBook + ' ' +  $( target ).attr( 'data-value' );
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

		let numVerses = availableScriptures[ selectedBook ]['verse_counts'][ (intSelection - 1) ];
		// Redraw the UI
		let bodyContent = '<ul id="cpl-verse-list">';

		for( let i = 0; i < numVerses; i++ ) {
			let showValue = (i +1).toString();
			bodyContent += '<li class="cpl-verse-selection-number" data-value="' + showValue + '"> ' + showValue + ' </li>';
		}
		bodyContent += '</ul>';

		$( '#cpl-scripture-list-chapter .cpl-scripture-progress-content').html( bodyContent );

		setTimeout(
			() => {
				rebindClickHandlers();
			}, 100
		);
	}

	/**
	 * Click handler for selecting a Bible chapter
	 *
	 * @param {DOMEvent} event
	 */
	let handleVerseSelection = ( event ) => {
		event.preventDefault();

		$( 'cpl-verse-selection-number' ).removeClass( 'cpl-selected' );

		let target = $( event.target );
		if( ! $( target ).hasClass( 'cpl-verse-selection-number' ) ) {
			target = $( target ).parents( '.cpl-verse-selection-number' )[0];
		}
		$( target ).addClass( 'cpl-selected' );

		let currentLevel = $( '#cpl-scripture-selection-level' ).attr( 'data-value' );
		let intSelection = parseInt( $( target ).attr( 'data-value' ), 10 );
		let currentSelection = '';

		if( 'verse' === currentLevel ) {
			currentSelection = $( '#cpl-scripture-current-selection' ).attr( 'data-value' ) + ':' +  $( target ).attr( 'data-value' );
		} else {
			currentSelection = $( '#cpl-scripture-current-selection' ).attr( 'data-value' ) + '-' +  $( target ).attr( 'data-value' );
		}



		$( '#cpl-scripture-list-chapter .cpl-scripture-progress-display').html(
			'<strong>Current Selection</strong>:&nbsp;&nbsp;' + currentSelection
		);
		$( '#cpl-scripture-list-chapter .cpl-scripture-finish-progress').html(
			'<a class="cpl-scripture-cancel-modal" href="#">Cancel</a>' +
			'<div class="preview button">SELECT &apos;' + currentSelection + '&apos;</div>'
		);

		$( '#cpl-scripture-current-selection' ).attr( 'data-value', currentSelection );
		$( '#cpl-scripture-selection-level' ).attr( 'data-value', 'verse_end' );

		$( '.cpl-verse-selection-number' ).each(
			( index, element ) => {
				let loopNumber = parseInt( $( element ).attr( 'data-value' ), 10 );
				if( loopNumber < intSelection ) {
					$( element ).addClass( 'disabled' );
				}
			}
		);

		// If this is the second verse, finish the completion automatically
		if( 'verse_end' === currentLevel ) {
			finalizeScriptureSelection( event );
			setTimeout(
				() => {
					rebindClickHandlers();
					cancelModal();
				}, 100
			);

		} else {
			setTimeout(
				() => {
					rebindClickHandlers();
				}, 100
			);
		}
	}

	// Reconnect functionality after DOM change

	/**
	 * Since items come and go from the DOM, we need to re-bind handlers regularly
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

		$( 'li.cpl-verse-selection-number' ).off( 'click' );
		$( 'li.cpl-verse-selection-number' ).on(
			'click',
			(event) => {
				handleVerseSelection( event );
			}
		);

		$( '.cpl-scripture-finish-progress .cpl-finish-selection-icon' ).off( 'click' );
		$( '.cpl-scripture-finish-progress .cpl-finish-selection-icon' ).on(
			'click',
			(event) => {
				finalizeScriptureSelection( event );
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

});


/**
 * Check for basic submit buttons
 */
jQuery($ => {
	$('.cpl_admin_submit_button').on('click', function(e) {
		e.preventDefault()
		const url = $(this).data('url')

		$(this).addClass('loading');
		$(this).attr('disabled', true);
		$(this).parent().find('.error').remove();

		fetch(url, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
			}
		})
		.then(res => res.json())
		.then(res => {
			$(this).removeClass('loading');
			$(this).attr('disabled', false);
			if(res.success) {
				$(this).addClass('success');
				setTimeout(() => {
					$(this).removeClass('success');
				}, 2000);
			}
			else {
				$(this).parent().append('<div class="error">' + res.data.error + '</div>');
			}
		})
		.catch(err => {
			console.warn(err)
			$(this).removeClass('loading');
			$(this).attr('disabled', false);
			$(this).parent().append('<div class="error">' + err.message + '</div>');
		})
	})
})

/**
 * Merge speakers
 */
jQuery($ => {
	const mergeSpeakers = (e) => {
		$.ajax({
			url: e.target.dataset.ajaxurl,
			method: 'POST',
			data: {
				action: 'cpl_merge_speakers',
				nonce: e.target.dataset.nonce
			},
			success: res => {
				e.target.parentElement.innerHTML = '<div class="success">' + res.data.html + '</div>'
			},
			error: err => {
				$(e.target.parentElement).append('<div class="error">' + err.message + '</div>');
			}
		})
	}

	$('#cpl_merge_speakers').on('click', mergeSpeakers)
})

/**
 * Series deletion detection
 */
jQuery($ => {
	const { __, sprintf } = wp.i18n;

	const message = sprintf(
		/* translators: %1$s is the plural label for cpl_item, %2$s is the single label for cpl_item_type */
		__('Click confirm to delete all %1$s associated with this %2$s only. WARNING: This action cannot be undone.', 'cp-library'),
		cplAdmin.postTypes.cpl_item.pluralLabel,
		cplAdmin.postTypes.cpl_item_type.singleLabel
	)

	console.log("Setting up event listeners")

	$('body.post-type-cpl_item_type a.submitdelete').on('click', (e) => {
		if(!confirm(message)) {
			e.preventDefault();
			return false;
		}
	})
	$('body.post-type-cpl_item_type .bulkactions #doaction').on('click', (e) => {
		if($('#bulk-action-selector-top').val() === 'trash') {
			if(!confirm(message)) {
				e.preventDefault();
				return false;
			}
		}
	})
})
