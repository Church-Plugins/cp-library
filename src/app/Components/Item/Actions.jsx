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
		
		// Create user interaction token that can be used to initialize playback
		const userInteractionToken = Date.now();
		
		// Store the token in a global scope to maintain the user interaction context
		window._cplUserInteractions = window._cplUserInteractions || {};
		window._cplUserInteractions[userInteractionToken] = true;
		
		api.passToPersistentPlayer({
			item,
			mode: 'video',
			isPlaying: true,
			playedSeconds: 0.0,
			userInteractionToken: userInteractionToken,
		});

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
		api.passToPersistentPlayer({
      item,
      mode: "audio",
      isPlaying: true,
      playedSeconds: 0.0,
    });
	};

	const isVideoURL = item.video.value && isURL(item.video.value);
	const isAudioURL = item.audio       && isURL(item.audio);

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