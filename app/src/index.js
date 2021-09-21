import React from 'react';
import ReactDOM from 'react-dom';

import './css/index.css';

import Components_Source_list from './Components/Source_List'

// Possible elements that we may find for shortcodes
// const root = document.getElementById( 'cpl-root' );
// const item = document.getElementById( 'cpl-item' );
// const source = document.getElementById( 'cpl-source' );
// const player = document.getElementById( 'cpl-player' );
// const itemList = document.getElementById( 'cpl-item_list' );
const sourceList = document.getElementById( 'cpl-source_list' );

// if( itemList ) {
// 	ReactDOM.render( <App />, itemList );
// }
if( sourceList ) {
	ReactDOM.render( <Components_Source_list />, sourceList );
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

