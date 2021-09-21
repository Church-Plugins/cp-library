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
	 * Render an item list
	 *
	 * @param Object[] listData 		Data returned from `getData`
	 * @returns
	 */
	renderList( {listData = null} ) {

		if( !listData || listData.length < 1 ) {

			return(
				<div class='cpl-item-list--item'>This list is empty.</div>
			);
		}

		let returnValue = "";

		listData.forEach(
			(listItem) => {

				returnValue +=
				"<div class='cpl-item-list--item'>" +
				"	<div class='cpl-item-list--item--details'>" +
				"		<div class='cpl-item-list--item--title'>" + listItem.title + "</div>" +
				"		<div class='cpl-item-list--item--desc'>" + listItem.desc + "</div>" +
				"	</div>" +
				"	<div class='cpl-item-list--item--thumb'>" +
				"		<div class='cpl-item-list--item--thumb'>" +
							listItem.thumb +
				"		</div>" +
				"	</div>" +
				"	<div class='cpl-item-list--item--meta'>" +
				"		<div class='cpl-item-list--item--date'>" + listItem.date + "</div>" +
				"		<div class='cpl-item-list--item--category'></div>" +
				"	</div>" +
				"	<div class='cpl-item-list--item--actions'>" +
				"		<div class='cpl-item-list--item--actions--video'><a href='" + listItem.video + "'>Download Video</a><br></div>" +
				"		<div class='cpl-item-list--item--actions--audio'><a href='" + listItem.audio + "'>Download Audio</a></div>" +
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
		if( !this.state || !this.state.itemList ) {
			return this.renderWait();
		} else {
			return this.renderList( {listData: this.state.itemList} );
		}
	}

	// Below here will probably go away
	getThumb() {
		return "THE ITEM THUMB";
	}

	getDetails() {
		return "SOME ITEM DETAILS";
	}

	getActions() {
		return "SOME ITEM ACTIONS";
	}
}

export default Components_Item_List;