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

	let toggleList = ( target ) => {
		if( $( target ).hasClass( 'cpl-list-closed' ) ) {
			$( target ).removeClass( 'cpl-list-closed' );
		} else {
			$( target ).addClass( 'cpl-list-closed' );
		}
	}

	$( '#cpl-scripture-input' ).on(
		'click',
		(event) => {
			event.preventDefault();
			// Toggle the list
			let listTarget = $( '#cpl-scripture-list' );
			toggleList( listTarget );
		}
	);

	$( '#cpl-book-list >li' ).on(
		'click',
		(event) => {
			event.preventDefault();

			// Normalize the click target
			let target = $( event.target );
			if( !$( target ).hasClass( 'cpl-scripture-book' ) ) {
				target = $( target ).parents( '.cpl-scripture-book' )[0];
			}

			let addingBookName = $( target ).attr( 'data-name' ).trim();
			console.log( 'CLICKED: ' + addingBookName );

			// TODO: 1. Lookup the chapter list and redraw the UI
			// TODO: 2. Lookup the verse list and redraw the UI
			// TODO: 3. Save the selection thus far into our hidden field
			// TODO: 4. Save the final selection to the metabox UI
			// TODO: 5. Parse and save on WP-post save
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
