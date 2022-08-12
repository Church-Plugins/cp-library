jQuery( function( $ ){

	const wp_inline_edit_function = inlineEditPost.edit;

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
});
