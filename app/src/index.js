import React 		from 'react';
import ReactDOM 	from 'react-dom';

import './css/index.scss';
import './css/SourceList.scss';
import './css/ItemList.scss';

import Components_Source_List 	from 	'./Components/Source_List'
import Components_Item_List 	from 	'./Components/Item_List'

// Possible elements that we may find for shortcodes
// const root = document.getElementById( 'cpl-root' );
// const item = document.getElementById( 'cpl-item' );
// const source = document.getElementById( 'cpl-source' );
// const player = document.getElementById( 'cpl-player' );
const itemList = document.getElementById( 'cpl-item_list' );
const sourceList = document.getElementById( 'cpl-source_list' );

if( itemList ) {
	ReactDOM.render( <Components_Item_List />, itemList );
}
if( sourceList ) {
	// let template = require( './templates/source-list.rt' );
	// ReactDOM.render( React.createElement( template ), sourceList );
	ReactDOM.render( <Components_Source_List />, sourceList );
}

// if( root ) {
// 	ReactDOM.render( <App />, root );
// }
// if( itemList ) {
// 	ReactDOM.render( <App />, itemList );
// }
// if( item ) {
// 	ReactDOM.render( <App />, item );
// }
// if( source ) {
// 	ReactDOM.render( <App />, source );
// }
// if( player ) {
// 	ReactDOM.render( <App />, player );
// }

