import jQuery from "jquery";
import apiFetch from '@wordpress/api-fetch';
import { debounce } from "@wordpress/compose";

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
			if(res.success) {
				$(this).addClass('success')
			} else {
				$(this).parent().append('<div class="error">' + res.data.error + '</div>');
				$(this).attr('disabled', false);
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
 * View transcript button
 */
jQuery($ => {
	// get search param from URL
	const searchParams = new URLSearchParams(window.location.search)
	const searchTerm = searchParams.get('s') || ''

	// cache fetched transcripts
	const transcriptCache = new Map()

	const modalWrapper = $(`
		<div class="cpl-modal-wrapper">
			<div class="cpl-modal cpl-transcript-modal" style="margin-left: auto; margin-right: auto;">
				<div class="cpl-modal-close">
					<span class="material-icons-outlined">close</span>
				</div>
				<div class="cpl-modal-header">
					<div class="cpl-search">
						<input type="text" placeholder="Search transcript" value="${searchTerm}" />
						<span class="cpl-search-matches"></span>
					</div>
				</div>
				<div class="cpl-modal-content"></div>
			</div>
		</div>
	`)

	const modal    = modalWrapper.find('.cpl-modal')
	const closeBtn = modal.find('.cpl-modal-close')
	const content  = modal.find('.cpl-modal-content')
	const search   = modal.find('.cpl-search input')
	
	const decodeHTMLEntities = (text) => {
		const textArea = document.createElement('textarea')
		textArea.innerHTML = text
		return textArea.value
	}
	
	const closeModal = () => {
		modalWrapper.removeClass('open')
		setTimeout(() => {
			modalWrapper.attr('style', 'display: none;')
		}, 300)
	}

	const formatTimestamp = (seconds) => {
		const hours = Math.floor(seconds / 3600)
		const minutes = Math.floor(seconds / 60) % 60
		const secs = seconds % 60
		let str = hours ? `${hours}:` : ''
		str += `${minutes.toString().padStart(hours ? 2 : 1, '0')}:`
		str += `${secs.toString().padStart(2, '0')}`
		return str
	}

	const parseTranscript = (videoURL, queryVar, transcript) => {
		const url = new URL(videoURL)

		const lines = transcript.split('\n')

		if(lines.length === 1) {
			// if we don't have paragraphs, display timestamps inline
			return transcript.replace(/\(t:(\d+)\)/g, (match, timestamp) => {
				// subtract 1 second from timestamp just for some padding
				url.searchParams.set(queryVar, Math.max(Number(timestamp) - 1, 0))
				return `<a class="cpl-timestamp-tag" href="${url.toString()}" target="_blank" rel="noopener noreferrer">${formatTimestamp(timestamp)}</a>`
			}	)
		} else {
			// display as paragraphs with timestamps at the beginning of each
			return transcript.split('\n').map((line, i) => {
				const timestamp = line.match(/\(t:(\d+)\)/) // gets the first timestamp in the line
				const content = line.replace(/\(t:\d+\)/g, '')
				if(timestamp) {
					// subtract 1 second from timestamp just to make sure we begin at the start of the paragraph
					url.searchParams.set(queryVar, Math.max(Number(timestamp[1]) - 1, 0))
					return `<p><a class="cpl-timestamp-tag" href="${url.toString()}" target="_blank" rel="noopener noreferrer">${formatTimestamp(timestamp[1])}</a>${content}</p>`
				}
				return `<p>${content}</p>`
			}).join('')
		}		
	}

	const highlight = (text, keyword) => {
		// get rid of any existing highlights
		text = text.replace(/<span class="highlight">(.*?)<\/span>/g, '$1')

		if(!keyword) {
			return {
				matches: 0,
				html: text
			}
		}

		const regex = new RegExp(keyword.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'), 'gi')
		
		let matches = 0
		text = text.replace(regex, match => {
			matches++
			return `<span class="highlight">${match}</span>`
		})
		return {
			matches,
			html: text
		}
	}

	const runHighlight = (text, keyword) => {	
		const highlightData = highlight(text, keyword)

		content.html(highlightData.html)
		search.parent().find('.cpl-search-matches').text(
			keyword ? `${highlightData.matches} matches` : ''
		)

		// scroll to first highlighted element
		const highlighted = content.find('.highlight').first()
		if(highlighted.length) {
			highlighted[0].scrollIntoView({ block: 'center' })
		}
	}

	const debouncedHighlight = debounce(runHighlight, 300)

	modal.on('click', (e) => e.stopPropagation())

	modalWrapper.on('click', () => closeModal())

	closeBtn.on('click', () => closeModal())

	search.on('input', (e) => debouncedHighlight(content.html(), e.target.value))

	$(document).on('click', '.cpl-transcript-btn', function(e) {
		e.preventDefault()
		
		const postId = $(this).data('post-id')

		// add loader
		content.html('<span class="spinner" style="visibility: visible; display: block; margin: 0 auto; float: none;"></span>')

		modalWrapper.attr('style', 'display: block;')
		modalWrapper.addClass('open')

		if (transcriptCache.has(postId)) {
			runHighlight(transcriptCache.get(postId), searchTerm) // use cached transcript
			return
		} else {
			apiFetch({
				path: `/wp/v2/${cplAdmin.postTypes.cpl_item.postType}/${postId}?_fields=cpl_transcript,cmb2.item_meta.video_url`,
			}).then(data => {
				// decode HTML entities
				let transcript = decodeHTMLEntities(data?.cpl_transcript || '')

				// if video has a URL, parse the transcript with links to timestamps
				if (
					data.cmb2.item_meta.video_url &&
					( data.cmb2.item_meta.video_url.includes('youtube.com') ||
					data.cmb2.item_meta.video_url.includes('youtu.be') )
				) {
					transcript = parseTranscript(data.cmb2.item_meta.video_url, 't', transcript)
				}
				
				transcriptCache.set(postId, transcript) // save to cache
	
				runHighlight(transcript, searchTerm)
			}).catch(err => {
				content.html('<div class="error">' + err.message + '</div>')
			})
		}		
	})

	modalWrapper.attr('style', 'display: none;')

	$('body').append(modalWrapper)
})

/**
 * Import transcript buttons
 */
jQuery($ => {
	$('td.column-transcript .cpl-import-transcript-btn').on('click', function(e) {
		const url    = $(this).data('url')
		const postId = url.split('post_id=')[1]

		$(this).addClass('loading');

		const savedHTML = $(this).html()

		$(this).html('Importing...')
		$(this).attr('disabled', true)

		$.post(url, (response) => {
			$(this).removeClass('loading');			
			if(response.success) {
				$(this).addClass('success')
				$(this).replaceWith(`<button type="button" class="button cpl-transcript-btn" data-post-id="${postId}">View</button>`)
			} else {
				alert(response.data)
				$(this).attr('disabled', false)
				$(this).html(savedHTML)
				console.warn(response)
			}
		})
	})
})
