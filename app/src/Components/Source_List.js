import React, { Component } from 'react';
import ReactDOMServer from 'react-dom/server';
import CircularProgress from '@mui/material/CircularProgress';
import Box from '@mui/material/Box';

import Components_WP_REST_Request from './WP_REST_Request';

/**
 * Prepares the content and handles the view for cpl_source_list
 *
 * @author costmo
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
		this.listData = null;
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
	 * Component DOM renderer
	 *
	 * @returns String
	 */
	render() {

		// Initial load or no list - show the wait element
		if( !this.state || !this.state.sourceList ) {
			window.listData = [];
			return this.renderWait();
		} else {
			let template = require( '../templates/source-list.rt' );
			// Clear elements that may have "dangerous" HTML
			this.state.sourceList.forEach(
				(listItem, listIndex) => {
					this.state.sourceList[listIndex].desc	= <div dangerouslySetInnerHTML={ {__html: listItem.desc} } />;
					this.state.sourceList[listIndex].thumb	= <div dangerouslySetInnerHTML={ {__html: listItem.thumb} } />;
				}
			);
			return React.createElement( template, {listData: this.state.sourceList} );
		}
	};

}

export default Components_Source_List;