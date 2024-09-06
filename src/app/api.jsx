/**
 * API that can be used both internally and externally.
 */
import Providers from "./Contexts/Providers";
import PersistentPlayer from "./Templates/PersistentPlayer";
import { createRoot } from "@wordpress/element";
import { cplLog, cplVar } from "./utils/helpers";
import screenfull from "screenfull";

class CP_Library {
	/** @type {import('react-dom/client').Root | null} */
	static __root = null

	/** @type {HTMLElement | null} */
	static __domNode = null

	/** @type {{[key:string]: Function[]}} */
	__listeners = {}

	/** @type {boolean} */
	__listener = false

	/** @type {boolean} */
	isIframe = window !== window.parent

	/**
	 * Class constructor.
	 */
	constructor() {
		if( ! this.isIframe ) {
			this.listen('CPL_OPEN_PERSISTENT_PLAYER', this.__createRoot.bind(this));
		}
	}

	/**
	 * Create the root element for the persistent player.
	 */
	__createRoot() {
		if (!CP_Library.__root) {
			CP_Library.__domNode = window.top.document.getElementById('cpl_persistent_player');
			CP_Library.__root    = createRoot(CP_Library.__domNode)
			CP_Library.__root.render(
				<Providers>
					<PersistentPlayer />
				</Providers>
			)
			window.top.document.body.classList.add('cpl-persistent-player');
		}
	}

	/**
	 * Pass an item to the persistent player.
	 *
	 * @param {object} args
	 * @param {object} args.item The item to pass to the player.
	 * @param {string} args.mode The mode to pass to the player. One of 'audio' or 'video'.
	 * @param {boolean} args.isPlaying Whether the player should start playing immediately.
	 * @param {number} args.playedSeconds The number of seconds to start playing from.
	 */
	passToPersistentPlayer({ item, mode, isPlaying, playedSeconds }) {
		this.triggerEvent('CPL_OPEN_PERSISTENT_PLAYER', {
			item,
			mode,
			isPlaying,
			playedSeconds,
		});
	
		setTimeout(() => {
			this.triggerEvent('CPL_HANDOVER_TO_PERSISTENT', {
				item,
				mode,
				isPlaying,
				playedSeconds,
			});
		}, 50);
	
		cplLog( item.id, 'persistent' );
	
		// also log a play action if we are not currently playing
		if ( ! (playedSeconds > 0) ) {
			cplLog( item.id, 'play' );
		}
	}

	/**
	 * Close the persistent player.
	 *
	 * @returns {void}
	 */
	closePersistentPlayer() {
		CP_Library.__root.unmount()
		CP_Library.__root = null
		CP_Library.__domNode = null
		window.top.document.body.classList.remove('cpl-persistent-player');
		window.top.postMessage({
			action: "CPL_PERSISTENT_PLAYER_CLOSED",
		});
	}

	/**
	 * Whether the player is currently active.
	 *
	 * @returns {boolean}
	 */
	playerIsActive() {
		return window.top.document.body.classList.contains('cpl-persistent-player');
	}

	/**
	 * Open the player in fullscreen.
	 *
	 * @returns {void}
	 */
	openInFullscreen() {
		if( ! CP_Library.__domNode ) {
			return;
		}
		screenfull.request( CP_Library.__domNode.querySelector( '.persistentPlayer__video' ) );
	}

	/**
	 * Get a variable from the global object.
	 *
	 * @param {string} key The subgroup of the settings
	 * @param {string|number} index The index of the setting
	 * @returns {any} The value of the setting
	 */
	getVar(key, index) {
		return cplVar(key, index);
	}

	/**
	 * Log a message to CP Library's internal logging system.
	 *
	 * @param {number} itemID The ID of the item to log.
	 * @param {string} action The action to log.
	 * @param {any} payload The payload to log.
	 */
	log(itemID, action, payload = null) {
		cplLog(itemID, action, payload);
	}

	/**
	 * Trigger an event that happens globally, including sending to the parent window when in iframe.
	 *
	 * @param {string} action The action to trigger.
	 * @param {Object} payload The payload to send.
	 * @returns {void}
	 */
	triggerEvent(action, payload = {}) {
		window.top.postMessage({
			action,
			...payload,
		});
	}

	/**
	 * Setup a global event listener for messages.
	 *
	 * @param {string} action The action to listen for.
	 * @param {Function} callback The callback to run when the action is triggered.
	 * @returns {void}
	 */
	listen(action, callback) {
		this.__listeners[action] = [ ...(this.__listeners[action] || []), callback ];
		if( ! this.__listener ) {
			window.top.addEventListener('message', this.__onEvent.bind(this));
			this.__listener = true;
		}
	}

	/**
	 * Stop listening for an action.
	 *
	 * @param {string} action The action to stop listening for.
	 * @param {Function} callback The callback to remove.
	 * @returns {void}
	 */
	removeListener(action, callback) {
		this.__listeners[action] = this.__listeners[action].filter(cb => cb !== callback);
	}

	/**
	 * Global listener for messages.
	 *
	 * @param {MessageEvent} e
	 * @returns {void}
	*/
	__onEvent(e) {
		if (e.origin !== window.location.origin) {
			return;
		}

		if(!e.data.action) {
			return;
		}

		if(e.data.action in this.__listeners) {
			this.__listeners[e.data.action].forEach(callback => {
				callback(e.data);
			})
		}
	}
}

const api = new CP_Library();

export default api;
