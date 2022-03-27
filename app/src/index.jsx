import React 		from 'react';
import ReactDOM 	from 'react-dom';

import App from "./Templates/App";
import ItemDetail from "./Templates/ItemDetail";
import ItemList from './Templates/ItemList';
import ItemWidget from './Templates/ItemWidget';
import VideoWidget from './Templates/VideoWidget';
import ItemActions from './Templates/ItemActions';
import ItemPlayer from './Templates/ItemPlayer';

// Possible elements that we may find for shortcodes
const root = document.getElementById( 'cpl_root' );
const item = document.getElementById( 'cpl_item' );
const itemList = document.getElementById( 'cpl_item_list' );
const itemWidget = document.getElementById( 'cpl_item_widget' );
const videoWidget = document.getElementById( 'cpl_video_widget' );
const itemActions = document.querySelectorAll( '.cpl_item_actions' );
const itemPlayers = document.querySelectorAll( '.cpl_item_player' );

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

if ( itemActions.length ) {
	for ( let i = 0; i < itemActions.length; i++ ) {
		let item = JSON.parse(itemActions[i].getAttribute('data-item'));
		ReactDOM.render(<ItemActions item={item}/>, itemActions[i]);
	}
}

if ( itemPlayers.length ) {
	for ( let i = 0; i < itemPlayers.length; i++ ) {
		let item = JSON.parse(itemPlayers[i].getAttribute('data-item'));
		ReactDOM.render(<ItemPlayer item={item}/>, itemPlayers[i]);
	}
}

window.addEventListener( 'cp-render-item-actions', function(e) {
	let actions = document.querySelectorAll( '.cpl_item_actions' );

	if ( actions.length ) {
		for ( let i = 0; i < actions.length; i++ ) {
			let item = JSON.parse(actions[i].getAttribute('data-item'));
			ReactDOM.render(<ItemActions item={item}/>, actions[i]);
		}
	}
}, false );
