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

			// Mount up front rather than waiting for the first play. Safari grants a
			// media element permission to play only for a play() call made inside a
			// user gesture, and tracks it per element — so an <audio> element created
			// in response to the click is already too late and the first click does
			// nothing. Mounting here lets PersistentPlayer unlock the element on the
			// first interaction, well before any play button is pressed.
			this.__mountWhenReady();
		}
	}

	/**
	 * Mount the player as soon as its container exists.
	 *
	 * The container is appended by the plugin's own inline jQuery ready handler, so
	 * it is not guaranteed to be in the document when this module runs. Retry across
	 * a few frames, then fall back to window load. The CPL_OPEN_PERSISTENT_PLAYER
	 * listener still mounts on demand, so the worst case is the old behaviour rather
	 * than no player.
	 */
	__mountWhenReady( attempt = 0 ) {
		if ( CP_Library.__root ) {
			return;
		}

		this.__createRoot();

		if ( CP_Library.__root ) {
			return;
		}

		if ( attempt < 20 ) {
			requestAnimationFrame( () => this.__mountWhenReady( attempt + 1 ) );
			return;
		}

		window.addEventListener( 'load', () => this.__createRoot(), { once: true } );
	}

	/**
	 * Create the root element for the persistent player.
	 *
	 * Note this deliberately does not mark the player as active. `cpl-persistent-player`
	 * on the body is what tells the rest of the plugin a sermon is loaded — it drives
	 * playerIsActive() and the SPA-style iframe navigation — and mounting an idle,
	 * silent player is not that. PersistentPlayer adds the class once an item is
	 * actually handed over.
	 */
	__createRoot() {
		if (!CP_Library.__root) {
			CP_Library.__domNode = window.top.document.getElementById('cpl_persistent_player');

			if (!CP_Library.__domNode) {
				return;
			}

			CP_Library.__root = createRoot(CP_Library.__domNode)
			CP_Library.__root.render(
				<Providers>
					<PersistentPlayer />
				</Providers>
			)
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
	 * @param {number} [args.userInteractionToken] Token to maintain user interaction context.
	 */
	passToPersistentPlayer({ item, mode, isPlaying, playedSeconds, userInteractionToken, isIOS }) {
		// Store token for immediate access globally
		if (userInteractionToken) {
			window._activeUserInteractionToken = userInteractionToken;
		}
		
		// Include all parameters when triggering events
		const params = {
			item,
			mode,
			isPlaying,
			playedSeconds,
			userInteractionToken,
			isIOS
		};
		
		// Create the player container immediately
		this.triggerEvent('CPL_OPEN_PERSISTENT_PLAYER', params);

		// Ensure player mounting completes before sending handover
		// A very short timeout ensures DOM updates before handover
		setTimeout(() => {
			this.triggerEvent('CPL_HANDOVER_TO_PERSISTENT', params);
		}, 10);

		cplLog(item.id, 'persistent');

		// also log a play action if we are not currently playing
		if (!(playedSeconds > 0)) {
			cplLog(item.id, 'play');
		}
	}

	/**
	 * Close the persistent player.
	 *
	 * Resets it to idle in place rather than unmounting it. The audio element it
	 * holds is the one Safari granted playback permission on the first
	 * interaction (see __mountWhenReady); a new element created for the next
	 * Listen would arrive without it. An arrow, so it can be passed unbound —
	 * straight into an onClick, say.
	 *
	 * @returns {void}
	 */
	closePersistentPlayer = () => {
		window.top.document.body.classList.remove('cpl-persistent-player');
		this.triggerEvent('CPL_CLOSE_PERSISTENT_PLAYER');
		this.triggerEvent('CPL_PERSISTENT_PLAYER_CLOSED');
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
	 * Whether the persistent player is currently playing.
	 * This is a convenience method that checks if the player is active.
	 * More detailed state would require communication with the PersistentPlayer component.
	 *
	 * @returns {boolean}
	 */
	isPersistentPlayerPlaying() {
		return this.playerIsActive();
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

		if(!screenfull.isEnabled) {
			const node = CP_Library.__domNode.querySelector( '.persistentPlayer__video video' );
			if ( node.webkitEnterFullscreen ) {
				node.webkitEnterFullscreen();
				return;
			}

			throw new Error("Fullscreen is not supported on this device")
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