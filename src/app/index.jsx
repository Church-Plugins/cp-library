import { createRoot } from '@wordpress/element';
import ItemWidget from './Templates/ItemWidget';
import VideoWidget from './Templates/VideoWidget';
import ItemActions from './Templates/ItemActions';
import ItemPlayer from './Templates/ItemPlayer';
import PlayOverlay from './Templates/PlayOverlay';
import api from './api';

/**
 * Expose the api to the window object.
 */
window.cp_library = api;

const itemWidget = document.getElementById( 'cpl_item_widget' );
const videoWidget = document.getElementById( 'cpl_video_widget' );
const itemActions = document.querySelectorAll( '.cpl_item_actions' );
const itemPlayers = document.querySelectorAll( '.cpl_item_player' );
const playBtnOverlays = document.querySelectorAll( '.cpl_play_overlay' )

const renderEvent = new Event( 'cpl-rendered' );

if (itemWidget) {
	let item = JSON.parse(itemWidget.getAttribute('data-item'));

	if (item) {
		createRoot(itemWidget).render(<ItemWidget item={item}/>);
	}
}

if (videoWidget) {
	let item = JSON.parse(videoWidget.getAttribute('data-item'));

	if ( item ) {
		createRoot(videoWidget).render(<VideoWidget item={item}/>);
	}
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
