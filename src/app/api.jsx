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

	isIframe = window !== window.parent

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
		if (!CP_Library.__root && !this.isIframe) {
			const player = window.top.document.getElementById('cpl_persistent_player');
			CP_Library.__root = createRoot(player)
			CP_Library.__root.render(
				<Providers>
					<PersistentPlayer />
				</Providers>
			)
			CP_Library.__domNode = player
			console.log("Created persistent player")
		}

		console.log("Passed to persistent player")
	
		 setTimeout(() => {
			window.top.postMessage({
				action: 'CPL_HANDOVER_TO_PERSISTENT',
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
		CP_Library.__root?.unmount()
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
		return !!CP_Library.__root;
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
}

const api = new CP_Library();

export default api;
