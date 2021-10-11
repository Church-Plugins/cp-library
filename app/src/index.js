import React 		from 'react';
import ReactDOM 	from 'react-dom';

import './css/App.css';

import './css/base.css';
import './css/main.css';
import './css/index.css';

import './css/SourceList.css';
import './css/ItemList.css';
import './css/Filter.css';

// TODO: combine and minify generated CSS

// In dev mode, we get an error because we require template files in these components. It seems like
// the path resolution is different in dev vs. in prod builds.
// import Components_Source_List 	from 	'./Components/Source_List'
// import Components_Item_List 	from 	'./Components/Item_List'

// Possible elements that we may find for shortcodes
const root = document.getElementById( 'cpl_root' );
const item = document.getElementById( 'cpl_item' );
// const source = document.getElementById( 'cpl_source' );
// const player = document.getElementById( 'cpl_player' );
// const itemList = document.getElementById( 'cpl_item_list' );
// const sourceList = document.getElementById( 'cpl_source_list' );

//
// if( itemList ) {
// 	ReactDOM.render( <Components_Item_List />, itemList );
// }
// if( sourceList ) {
// 	ReactDOM.render( <Components_Source_List />, sourceList );
// }

if (root) {
	import Talks from "./Components/Talks"
	ReactDOM.render( <Talks />, root );
}
if( item ) {
	import ItemDetail from "./Components/ItemDetail"
	ReactDOM.render( <ItemDetail />, root );
}
// if( itemList ) {
// 	ReactDOM.render( <App />, itemList );
// }
// if( source ) {
// 	ReactDOM.render( <App />, source );
// }
// if( player ) {
// 	ReactDOM.render( <App />, player );
// }
