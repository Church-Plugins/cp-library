import IconButton from '@mui/material/IconButton';
import { ChevronRight } from "react-feather"
import { isURL } from '../../utils/helpers';
import Box from '@mui/material/Box';
import PlayAudio from '../../Elements/Buttons/PlayAudio';
import PlayVideo from '../../Elements/Buttons/PlayVideo';
import jQuery from 'jquery';
import api from '../../api';

// Global token management to prevent memory leaks
// Periodically clean up stale interaction tokens (older than 2 minutes)
setInterval(() => {
  if (window._cplUserInteractions) {
    const now = Date.now();
    Object.keys(window._cplUserInteractions).forEach(token => {
      // If token is older than 2 minutes, remove it
      if (parseInt(token, 10) < now - 120000) {
        delete window._cplUserInteractions[token];
      }
    });
  }
}, 60000); // Run cleanup every minute

export default function Actions({ item, callback }) {
	const viewItem = (e) => {
		e.stopPropagation();
		window.location.href = item.permalink;
	};

	const playVideo = (e) => {

		e.stopPropagation();
		e.preventDefault(); // Prevent any default actions

		// Check if the persistent player is already active with this video playing
		if (api.playerIsActive()) {
			// Get the current player DOM node
			const playerNode = window.top.document.getElementById('cpl_persistent_player');
			
			// Only toggle play/pause if this is the same video currently playing
			if (playerNode && playerNode.querySelector('.title-text a')?.innerHTML === item.title) {
				// Just toggle play/pause instead of opening a new player
				const event = new CustomEvent('message');
				event.data = {
					action: 'CPL_TOGGLE_PLAY',
					item: item
				};
				window.top.dispatchEvent(event);
				return;
			}
		}

		// Capture user interaction at the earliest possible moment
		const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) ||
		            (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

		// Create user interaction token with timestamp for tracking
		const userInteractionToken = Date.now();

		// Store the token in a global scope to maintain the user interaction context
		window._cplUserInteractions = window._cplUserInteractions || {};
		window._cplUserInteractions[userInteractionToken] = true;

		// For iOS, we need a simple approach to maintain the gesture chain
		if (isIOS) {
			try {
				// Create/resume audio context - crucial for iOS audio unlock
				window._cplAudioContext = window._cplAudioContext || new (window.AudioContext || window.webkitAudioContext)();

				if (window._cplAudioContext.state === 'suspended') {
					window._cplAudioContext.resume();
				}

				// Create a quick silent sound (needed for iOS)
				const silentSource = window._cplAudioContext.createOscillator();
				const gain = window._cplAudioContext.createGain();
				gain.gain.value = 0.01; // Nearly silent
				silentSource.connect(gain);
				gain.connect(window._cplAudioContext.destination);
				silentSource.start();
				silentSource.stop(window._cplAudioContext.currentTime + 0.01); // Very short duration

				// IMMEDIATELY call the API - this is critical for iOS gesture chain
				api.passToPersistentPlayer({
					item,
					mode: 'video',
					isPlaying: true,
					playedSeconds: 0.0,
					userInteractionToken: userInteractionToken,
					isIOS: true,
				});
			} catch (err) {
				console.log('Actions: Error with iOS handling:', err);

				// Fallback to direct call
				api.passToPersistentPlayer({
					item,
					mode: 'video',
					isPlaying: true,
					playedSeconds: 0.0,
					userInteractionToken: userInteractionToken,
					isIOS: true,
				});
			}
		} else {
			// Standard approach for non-iOS
			api.passToPersistentPlayer({
				item,
				mode: 'video',
				isPlaying: true,
				playedSeconds: 0.0,
				userInteractionToken: userInteractionToken,
			});
		}

		// Slider mark may load up to a second after the frame is open
		setTimeout(
			() => jQuery( '.MuiSlider-root.MuiSlider-marked .MuiSlider-mark' ).attr( 'title', 'Jump to Sermon' ),
			1500
		);

		// Clean up the token after a reasonable timeout to prevent memory leaks
		setTimeout(() => {
			if (window._cplUserInteractions && window._cplUserInteractions[userInteractionToken]) {
				delete window._cplUserInteractions[userInteractionToken];
			}
		}, 30000); // 30 seconds is sufficient for player initialization
	};

	const playAudio = (e) => {

		e.stopPropagation();
		e.preventDefault(); // Prevent any default actions

		// Check if the persistent player is already active with this audio playing
		if (api.playerIsActive()) {
			// Get the current player DOM node
			const playerNode = window.top.document.getElementById('cpl_persistent_player');
			
			// Only toggle play/pause if this is the same audio currently playing
			if (playerNode && playerNode.querySelector('.title-text a')?.innerHTML === item.title) {
				// Just toggle play/pause instead of opening a new player
				const event = new CustomEvent('message');
				event.data = {
					action: 'CPL_TOGGLE_PLAY',
					item: item
				};
				window.top.dispatchEvent(event);
				return;
			}
		}

		// Check if this is iOS for consistent handling
		const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) ||
					(navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

		// Create user interaction token for both iOS and non-iOS
		const userInteractionToken = Date.now();
		window._cplUserInteractions = window._cplUserInteractions || {};
		window._cplUserInteractions[userInteractionToken] = true;

		// For iOS, create an audio context to maintain the user gesture chain
		if (isIOS) {
			try {
				// Initialize or resume audio context
				window._cplAudioContext = window._cplAudioContext || new (window.AudioContext || window.webkitAudioContext)();
				if (window._cplAudioContext.state === 'suspended') {
					window._cplAudioContext.resume();
				}
			} catch (err) {
				console.log('Actions: Error with iOS audio context:', err);
			}
		}

		// Immediate call to persistent player - consistent with video approach
		api.passToPersistentPlayer({
			item,
			mode: "audio",
			isPlaying: true,
			playedSeconds: 0.0,
			userInteractionToken: userInteractionToken,
			isIOS: isIOS,
		});

		// Clean up token after a timeout
		setTimeout(() => {
			if (window._cplUserInteractions && window._cplUserInteractions[userInteractionToken]) {
				delete window._cplUserInteractions[userInteractionToken];
			}
		}, 30000);
	};

	// Ensure we're correctly determining if this is a URL video (should use onClick) or not (should use href)
	// We want isVideoURL to be TRUE when it's a video URL, which means href should be FALSE to use onClick
	const isVideoURL = item.video && item.video.value && typeof item.video.value === 'string' && isURL(item.video.value);

	// Similarly for audio, make sure we handle it properly
	const isAudioURL = item.audio && typeof item.audio === 'string' && isURL(item.audio);

	return (
		<Box className="cpl-list-item--actions" ref={callback}>
			<Box className="cpl-list-item--actions--buttons">
				{!!item.video.value && (
					// when an href is provided, the onclick is ignored and an anchor tag is rendered instead of a button
					<PlayVideo onClick={playVideo} href={isVideoURL ? false : item.permalink} />
				)}
				{!!item.audio && (
					<PlayAudio onClick={playAudio} href={isAudioURL ? false : item.permalink} />
				)}
			</Box>
		</Box>
	);
}
