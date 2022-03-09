import React 		from 'react';
import ReactDOM 	from 'react-dom';

//import './css/App.css';

import './css/main.css';
import './css/index.css';

//import './css/SourceList.css';
//import './css/ItemList.css';
//import './css/Filter.css';

import App from "./Components/App";
import ItemDetail from "./Components/ItemDetail";
import ItemList from './Components/ItemList';
import ItemWidget from './Components/ItemWidget';
import VideoWidget from './Components/VideoWidget';
import PersistentPlayer from './Components/PersistentPlayer';

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
const itemList = document.getElementById( 'cpl_item_list' );
const itemWidget = document.getElementById( 'cpl_item_widget' );
const videoWidget = document.getElementById( 'cpl_video_widget' );
const persistentPlayer = document.getElementById( 'cpl_persistent_player' );
// const sourceList = document.getElementById( 'cpl_source_list' );

//
// if( itemList ) {
// 	ReactDOM.render( <Components_Item_List />, itemList );
// }
// if( sourceList ) {
// 	ReactDOM.render( <Components_Source_List />, sourceList );
// }

if (root) {
	let itemId = root.getAttribute( 'data-item-id' );
	const urlParams = new URLSearchParams(window.location.search);
	const talkID = urlParams.get('talk_id');

	if ( 0 < talkID ) {
		itemId = talkID;
	}

	if ( 0 < itemId ) {
		ReactDOM.render(<App itemId={itemId} />, root );
	} else {

		ReactDOM.render(<App />, root );
	}
}
//if( root ) {
//	ReactDOM.render( <ItemDetail />, root );
//}
if (itemList) {
	ReactDOM.render(<ItemList/>, itemList);
}
if (itemWidget) {
	ReactDOM.render(<ItemWidget/>, itemWidget);
}
if (videoWidget) {
	ReactDOM.render(<VideoWidget/>, videoWidget);
}
if( item ) {
	ReactDOM.render( <ItemDetail />, root );
}
if (window === window.top && persistentPlayer) {
//	ReactDOM.render(<PersistentPlayer />, persistentPlayer);
}
// if( source ) {
// 	ReactDOM.render( <App />, source );
// }
// if( player ) {
// 	ReactDOM.render( <App />, player );
// }
