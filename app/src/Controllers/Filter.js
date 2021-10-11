import { Component } from 'react';
import $ from 'jquery';

/**
 * Perform REST requests against the WP host
 *
 */
class Controllers_Filter extends Component {

	/**
	 * Class constructor
	 * @param object props 				Input properties
	 */
	constructor( props ) {
		super( props );
	}

	/**
	 * Event handler for checkbox selection of the "Format" filter
	 *
	 * @param DOMevent event
	 * @returns void
	 */
	handleFormatChange( event ) {

		// Simple sanity check
		if( !event || !event.target || !event.target.name ) {
			return
		}

		let parent = $( event.target ).parents( '.MuiFormGroup-root' )[0];

		let audio = $( parent ).find( 'input[name="filter__audio"]' );
		let video = $( parent ).find( 'input[name="filter__video"]' );
		let audio_target = $( audio ).parents( 'span.MuiCheckbox-root' )[0];
		let video_target = $( video ).parents( 'span.MuiCheckbox-root' )[0];

		// Set checkbox state
		if( 'filter__all' === event.target.name ) {

			if( event.target.checked ) {

				if( !$( audio ).prop( 'checked' ) ) {
					$( audio_target ).trigger( 'click' );
				}
				if( !$( video ).prop( 'checked' ) ) {
					$( video_target ).trigger( 'click' );
				}
			} else {

				if( $( audio ).prop( 'checked' ) ) {
					$( audio_target ).trigger( 'click' );
				}
				if( $( video ).prop( 'checked' ) ) {
					$( video_target ).trigger( 'click' );
				}
			}
		}

		// TODO: Load the data
	}

}
export default Controllers_Filter;