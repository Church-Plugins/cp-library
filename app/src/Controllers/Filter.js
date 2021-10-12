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

	handleTopicSelection( event ) {

		// Simple sanity check
		if( !event || !event.target || ! event.target.value ) {
			return
		}

		let topics = [];

		let parent = $( event.target ).parents( '.MuiFormControlLabel-root' )[0];
		let grandParent = $( parent ).parents( '.MuiFormGroup-root' )[0];

		$( grandParent ).find( 'label span input[type="checkbox"]' ).each(
			(index, element) => {
				if( $( element ).is( ':checked' ) ) {
					topics.push( $( element ).val() );
				}
			}
		);

		console.log( "CHECKED ITEMS" );
		console.log( topics );

		// TODO: Load the data
	}

}
export default Controllers_Filter;