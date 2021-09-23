import React, { Component } from 'react';
import CircularProgress from '@mui/material/CircularProgress';
import Box from '@mui/material/Box';

import Components_WP_REST_Request from './WP_REST_Request';

/**
 * Top-level SourceList implementation class
 */
class Components_Item_List extends Component {

	state = {
		itemList: null
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
		restRequest.get( {endpoint: 'items'} )
			.then(
				(restResponse) => {
					// Set the Component state member
					this.setState(
						{itemList: restResponse}
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
	 * Component DOM renderer
	 *
	 * @returns String
	 */
	render() {

		// Initial load or no list - show the wait element
		if( !this.state || !this.state.itemList ) {
			return this.renderWait();
		} else {
			let template = require( '../templates/item-list.rt' );
			// Clear elements that may have "dangerous" HTML
			this.state.itemList.forEach(
				(listItem, listIndex) => {
					this.state.itemList[listIndex].desc	= <div dangerouslySetInnerHTML={ {__html: listItem.desc} } />;
					this.state.itemList[listIndex].thumb	= <div dangerouslySetInnerHTML={ {__html: listItem.thumb} } />;
				}
			);
			return React.createElement( template, {listData: this.state.itemList} );
		}
	}
}

export default Components_Item_List;