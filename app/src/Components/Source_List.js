import React, { Component } from 'react';
import CircularProgress from '@mui/material/CircularProgress';
import Box from '@mui/material/Box';

import Components_WP_REST_Request from './WP_REST_Request';

/**
 * Top-level SourceList implementation class
 */
class Components_Source_List extends Component {

	state = {
		sourceList: null
	};

	/**
	 * Class constructor
	 * @param object props 				Input properties
	 */
	constructor( props ) {
		super( props );
	}

	/**
	 * Load data after the initial React component has loaded
	 *
	 * @returns void
	 */
	componentDidMount() {
		// Load data, which will trigger a DOM reload
		this.getData();
	}

	/**
	 * Get data for the view and set the instance's `status` member with values
	 *
	 * @returns void
	 */
	getData() {

		let restRequest = new Components_WP_REST_Request();
		restRequest.get( {endpoint: 'sources'} )
			.then(
				(restResponse) => {
					// Set the Component state member
					this.setState(
						{sourceList: restResponse}
					);
				}
			)
			.catch(
				(error) => { // We should probably do something here

				}
			)
			.then(
				() => { // Final then
					this.forceUpdate();
				}
			);

	}

	/**
	 * Convenience for calling a wait spinner
	 *
	 * @returns String
	 */
	renderWait() {
		return (
			<Box sx={{ display: 'flex' }}>
				<CircularProgress />
		  	</Box>
		);
	}

	/**
	 *
	 * @param Object[] listData 		Data returned from `getData`
	 * @returns
	 */
	renderList( {listData = null} ) {

		return(
			<div class='cpl-source-list--source'>
				<div class='cpl-source-list--source--thumb'>
					<div class='cpl-source-list--source--thumb'>
						{this.getThumb()}
					</div>
					<div class='cpl-source-list--source--details'>
						{this.getDetails()}
					</div>
					<div class='cpl-source-list--source--actions'>
						{this.getActions()}
					</div>
				</div>
			</div> );
	}

	/**
	 * Component DOM renderer
	 *
	 * @returns String
	 */
	render() {

		// Initial load or no list - show the wait element
		if( !this.state || !this.state.sourceList ) {
			return this.renderWait();
		} else {
			return this.renderList( {listData: this.state.sourceList} );
		}
	}

	// Below here will probably go away
	getThumb() {
		return "THE THUMB";
	}

	getDetails() {
		return "SOME DETAILS";
	}

	getActions() {
		return "SOME ACTIONS";
	}
}

export default Components_Source_List;