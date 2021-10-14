import { Component } from 'react';
import $ from 'jquery';
import Controllers_WP_REST_Request from '../Controllers/WP_REST_Request';
import async from 'async';

let mutating = false;

/**
 * Handle the UX part of front-end interactions
 *
 * TODO: This functionality should not be in Controllers, nor should it be a Component
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
	 * @deprecated
	 * @param DOMevent event
	 * @returns void
	 */
	handleFormatChange( event ) {

		return;

		// Simple sanity check
		// Also, do not continue if the click event originated from another JS action
		if( !event || !event.target || !event.target.name || mutating ) {
			return
		}

		let parent = $( event.target ).parents( '.MuiFormGroup-root' )[0];

		let audio = $( parent ).find( 'input[name="format__audio"]' );
		let video = $( parent ).find( 'input[name="format__video"]' );
		let all = $( parent ).find( 'input[name="format__all"]' );
		let audio_target = $( audio ).parents( 'span.MuiCheckbox-root' )[0];
		let video_target = $( video ).parents( 'span.MuiCheckbox-root' )[0];
		// let all_target = $( all ).parents( 'span.MuiCheckbox-root' )[0];

		// Set checkbox state
		if( 'format__all' === event.target.name ) {

			if( event.target.checked ) {

				if( !$( audio ).is( ':checked' ) ) {
					$( audio_target ).trigger( 'click' );
				}
				if( !$( video ).is( ':checked' ) ) {
					$( video_target ).trigger( 'click' );
				}
			} else {

				if( $( audio ).is( ':checked' ) ) {
					$( audio_target ).trigger( 'click' );
				}
				if( $( video ).is( ':checked' ) ) {
					$( video_target ).trigger( 'click' );
				}
			}
		} else {
			mutating = true;
			if( $( audio ).is( ':checked' ) && $( video ).is( ':checked' ) ) {
				if( !$( all ).is( ':checked' ) ) {
					// TODO: See why this isn't working as expected
					// $( all_target ).trigger( 'click' );
				}
			} else {
				if( $( all ).is( ':checked' ) ) {
					// $( all_target ).trigger( 'click' );
				}
			}
			mutating = false;
		}
	}

	/**
	 * Event handler for checkbox selection of the "Topic" filter
	 *
	 * @deprecated		Remove this once we know it is no longer in use
	 * @param DOMevent event
	 * @returns void
	 */
	async handleTopicSelection( event ) {

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

		let topicString = topics.join();

		const restRequest = new Controllers_WP_REST_Request();
		let data = {};
		if( topicString.length > 0 ) {
	        data = await restRequest.get( {endpoint: 'items', params: 'topic=' + topicString} );
		} else {
			data = await restRequest.get( {endpoint: 'items'} );
		}
	}

}
export default Controllers_Filter;