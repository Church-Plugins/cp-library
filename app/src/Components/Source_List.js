import React, { Component } from 'react';
import ReactDOMServer from 'react-dom/server';
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
	 *
	 * @param Object[] listData 		Data returned from `getData`
	 * @returns
	 */
	renderList( {listData = null} ) {

		if( !listData || listData.length < 1 ) {

			return(
				<div class='cpl-source-list--item'>This list is empty.</div>
			);
		}

		let returnValue = "";

		listData.forEach(
			(listItem) => {

				returnValue +=
				"<div class='cpl-source-list--source'>" +
				"	<div class='cpl-source-list--source--details'>" +
				"		<div class='cpl-source-list--source--title'>" + listItem.title + "</div>" +
				"		<div class='cpl-source-list--source--desc'>" + listItem.desc + "</div>" +
				"	</div>" +
				"	<div class='cpl-source-list--source--thumb'>" +
				"		<div class='cpl-source-list--source--thumb'>" +
							listItem.thumb +
				"		</div>" +
				"	</div>" +
				"	<div class='cpl-source-list--source--meta'>" +
				"		<div class='cpl-source-list--source--date'>" + listItem.date + "</div>" +
				"		<div class='cpl-source-list--source--category'></div>" +
				"	</div>" +
				"	<div class='cpl-source-list--source--actions'>" +
				"		<div class='cpl-source-list--source--actions--video'><a href='" + listItem.video + "'>Download Video</a><br></div>" +
				"		<div class='cpl-source-list--source--actions--audio'><a href='" + listItem.audio + "'>Download Audio</a></div>" +
				"	</div>" +
				"</div>";

			}
		);

		return( <div dangerouslySetInnerHTML={ {__html: returnValue} } /> );
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
			this.state.sourceList.forEach(
				(listItem, listIndex) => {
					this.state.sourceList[listIndex].desc = <div dangerouslySetInnerHTML={ {__html: listItem.desc} } />;
				}
			);
			return React.createElement( template, {listData: this.state.sourceList} );
		}
	};

}

export default Components_Source_List;