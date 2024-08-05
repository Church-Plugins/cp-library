import React 		from 'react';
import { createRoot } from 'react-dom/client';

import App from "./Templates/App";
import ItemDetail from "./Templates/ItemDetail";
import ItemList from './Templates/ItemList';
import ItemWidget from './Templates/ItemWidget';
import VideoWidget from './Templates/VideoWidget';
import ItemActions from './Templates/ItemActions';
import ItemPlayer from './Templates/ItemPlayer';
import PlayOverlay from './Templates/PlayOverlay';

// Possible elements that we may find for shortcodes
const root = document.getElementById( 'cpl_root' );
const item = document.getElementById( 'cpl_item' );
const itemList = document.getElementById( 'cpl_item_list' );
const itemWidget = document.getElementById( 'cpl_item_widget' );
const videoWidget = document.getElementById( 'cpl_video_widget' );
const itemActions = document.querySelectorAll( '.cpl_item_actions' );
const itemPlayers = document.querySelectorAll( '.cpl_item_player' );
const playBtnOverlays = document.querySelectorAll( '.cpl_play_overlay' )

const renderEvent = new Event( 'cpl-rendered' );

if (root) {
	let itemId = root.getAttribute( 'data-item-id' );
	const urlParams = new URLSearchParams(window.location.search);
	const talkID = urlParams.get('talk_id');

	if ( 0 < talkID ) {
		itemId = talkID;
	}

	if ( 0 < itemId ) {
		createRoot(root).render( <App itemId={itemId} /> );
	} else {
		createRoot(root).render(<App />);
	}
}

if (itemList) {
	createRoot(itemList).render(<ItemList/>);
}

if (itemWidget) {
	let item = JSON.parse(itemWidget.getAttribute('data-item'));

	if (item) {
		createRoot(itemWidget).render(<ItemWidget item={item}/>);
	} else {
		createRoot(itemWidget).render(<ItemWidget/>);
	}
}

if (videoWidget) {
	let item = JSON.parse(videoWidget.getAttribute('data-item'));

	if ( item ) {
		createRoot(videoWidget).render(<VideoWidget item={item}/>);
	} else {
		createRoot(videoWidget).render(<VideoWidget />);
	}
}

if( item ) {
	createRoot(root).render( <ItemDetail /> );
}

if ( itemActions.length ) {
	for ( let i = 0; i < itemActions.length; i++ ) {
		let item = JSON.parse(itemActions[i].getAttribute('data-item'));

		createRoot(itemActions[i]).render(
			<ItemActions
				callback={() => itemActions[i].dispatchEvent(renderEvent)}
				item={item}
			/>
		);
	}
}

if ( itemPlayers.length ) {
	for ( let i = 0; i < itemPlayers.length; i++ ) {
		let item = JSON.parse(itemPlayers[i].getAttribute('data-item'));
		createRoot(itemPlayers[i]).render(<ItemPlayer item={item}/>);
	}
}

if ( playBtnOverlays.length ) {
	for ( const overlay of playBtnOverlays ) {
		const item = JSON.parse(overlay.getAttribute('data-item'));
		createRoot(overlay).render(<PlayOverlay item={item} />);
	}
}

window.addEventListener( 'cp-render-item-actions', function(e) {
	let actions = document.querySelectorAll( '.cpl_item_actions' );

	if ( actions.length ) {
		for ( let i = 0; i < actions.length; i++ ) {
			let item = JSON.parse(actions[i].getAttribute('data-item'));
			createRoot(actions[i]).render(<ItemActions item={item}/>);
		}
	}
}, false );
