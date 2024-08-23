
jQuery($ => {
	const uploadFileFormConfig = {
		form: $( '#cp-upload-import-file' ),
		init() {
			this.form.ajaxForm( this );
		},
		beforeSubmit( arr, form, options ) {
			form.find( '.notice-wrap' ).remove();
			form.append( '<div class="notice-wrap"><div class="cp-progress"><div></div></div></div>' );
	
			//check whether client browser fully supports all File API
			if ( window.File && window.FileReader && window.FileList && window.Blob ) {
	
				// HTML5 File API is supported by browser
				return true;
			} else {
				const import_form = $( '.cp-import-form' ).find( '.cp-progress' ).parent().parent();
				const notice_wrap = import_form.find( '.notice-wrap' );
	
				import_form.find( '.button:disabled' ).attr( 'disabled', false );
	
				//Error for older unsupported browsers that doesn't support HTML5 File API
				notice_wrap.html( '<div class="update error"><p>We are sorry but your browser is not compatible with this kind of file upload. Please upgrade your browser.</p></div>' );
				return false;
			}
		},
		success( responseText, statusText, xhr, form ) {
			const data = $.parseJSON( xhr.responseText );

			form.hide()

			// trigger next step via event
			$( document ).trigger( 'cp-import-step-1', data );
		},
		error( xhr ) {
			const data = $.parseJSON( xhr.responseText );
			this.form.find( '.button:disabled' ).attr( 'disabled', false )
			
			if ( data.message ) {
				const noticeWrap = $( `<div class="notice-wrap">
					<div class="update error"><p>${data.message}</p></div>
				</div>` )
				this.form.append( noticeWrap )
			} else {
				this.form.find( '.notice-wrap' ).remove()
			}
		},
		dataType: 'json',
	}

	const importFormConfig = {
		form: $( '#cp-import-form' ),
		init( data ) {
			const form = this.form;

			form.slideDown();

			form.find('[name="file_url"]').val( data.file_url )

			// Show column mapping
			const select = form.find( 'select.cp-import-csv-column' )

			select.append(
				data.columns
					.sort( (a, b) => a < b ? -1 : a > b ? 1 : 0 )
					.map( column => `<option value="${column}">${column}</option>` )
					.join('')
			);

			select.on( 'change', function() {
				const key = $( this ).val();

				if ( ! key ) {
					$( this ).parent().next().html( '' );
				} else if ( false !== data.first_row[ key ] ) {
					$( this ).parent().next().html( data.first_row[ key ] );
				} else {
					$( this ).parent().next().html( '' );
				}
			} );

			select.each( function() {
				$( this ).val( $( this ).attr( 'data-field' ) ).change();
			} )

			form.ajaxForm( this ); // setup ajax form
		},
		success( responseText, statusText, xhr, form ) {
			form.hide()

			this.form.insertBefore( '<div class="notice-wrap"><div class="update update-success"><p>Import started.</p></div></div>' );

			// trigger next step via event
			$( document ).trigger( 'cp-import-step-2' );
		},
		error() {

		},
		dataType: 'json',
	}

	const importProgress = {
		target: $('.cpl-import-progress'),
		controller: new AbortController(),
		isPolling: false,
		init() {
			this.target.show()
			this.target.parent().find('p').hide()
			this.itemLabel = this.target.data( 'item-label' )
			this.update(
				this.target.data( 'percentage-complete' ),
				this.target.data( 'progress' ),
				this.target.data( 'total' ),
			)
			this.isPolling = true
			this.getProgress()
			this.target.find('.cpl-import-progress--cancel').on('click', this.cancelImport.bind(this))
		},
		isActive() {
			return this.target.data( 'is-active' ) === true
		},
		getProgress() {
			wp.apiFetch({
				path: '/cp-library/v1/import/sermons/progress',
				signal: this.controller.signal
			})
			.then(this.handleProgressUpdate.bind(this))
			.catch(this.handleProgressError.bind(this))
		},
		handleProgressUpdate(data) {
			if(!this.isPolling) {
				return
			}
			if(data.in_progress) {
				this.update(data.percentage_complete, data.progress, data.total)
				setTimeout(this.getProgress.bind(this), 3000) // re-trigger update after 3 seconds
			} else {
				this.handleComplete()
			}
		},
		handleComplete() {
			this.target.html('')
			this.displayMessage('Import complete', 'success')
			this.isPolling = false
		},
		update(percentage, progress, total) {
			this.target.find('.cpl-progressbar').attr( 'style', `width: ${percentage}%`)
			this.target.find('.cpl-progressbar-label span').html( `${percentage.toFixed(1)}` )
			this.target.find('.cpl-progressbar-progress-text').html( `Imported ${progress} of ${total} ${this.itemLabel}`)
		},
		handleProgressError(data) {
			this.displayMessage('Error getting import progress', 'error')
		},
		cancelImport() {
			if( ! confirm( 'Are you sure you want to cancel the import? You won\'t be able to restart it.' ) ) {
				return
			}
			this.target.find('.cpl-import-progress--cancel').attr('disabled', true)
			wp.apiFetch({
				path: '/cp-library/v1/import/sermons/cancel',
				method: 'POST',
			})
			.then(this.handleCancelSuccess.bind(this))
			.catch(err => {
				console.error(err)
				this.displayMessage('Error cancelling import', 'error')
				this.target.find('.cpl-import-progress--cancel').attr('disabled', false)
			})
		},
		handleCancelSuccess() {
			this.target.html('')
			this.displayMessage('Import cancelled', 'success')
			this.isPolling = false
		},
		displayMessage(message, type = 'error') {
			if( ! this.target.find( '.cpl-import-progress--notice' ).length ) {
				this.target.append( '<div class="cpl-import-progress--notice"></div>' )
			}
			this.target.find( '.cpl-import-progress--notice' ).html(`<div class="notice notice-${type}"><p>${message}</p></div>`)
		}
	}

	if ( importProgress.isActive() ) {
		importProgress.init()
	} else {
		uploadFileFormConfig.init();
	}

	$( document ).on( 'cp-import-step-1', function( _event, data ) {
		importFormConfig.init( data )
	})

	$( document ).on( 'cp-import-step-2', function( _event ) {
		importProgress.init()
	})
});
