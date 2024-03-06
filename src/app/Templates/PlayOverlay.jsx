/**
 * Displays a play overlay on top of the thumbnail.
 */

import React from 'react';
import Providers from '../Contexts/Providers';
import { usePersistentPlayer } from '../Contexts/PersistentPlayerContext';
import { isURL } from '../utils/helpers';

export const PlayOverlayWrapper = ( { item, onClick } ) => {
	const isVideo = item.video?.value && isURL(item.video.value);
	const isAudio = item.audio && isURL(item.audio) && !isVideo;
	
	const handleClick = () => {
		onClick?.(isVideo ? 'video' : isAudio ? 'audio' : null);
	}

	if ( ! isVideo && ! isAudio ) {
		return null;
	}
	
	return (
		<div className='cpl-play-btn-overlay' onClick={handleClick}>
			{
				isVideo ?
				<svg xmlns="http://www.w3.org/2000/svg" width="25%" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-play">
					<polygon points="5 3 19 12 5 21 5 3"/>
				</svg> :
				<svg xmlns="http://www.w3.org/2000/svg" width="25%" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="lucide lucide-volume-2">
					<polygon points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"/>
					<path fill="none" stroke-width="2" d="M15.54 8.46a5 5 0 0 1 0 7.07"/>
					<path fill="none" stroke-width="2" d="M19.07 4.93a10 10 0 0 1 0 14.14"/>
				</svg>
			}
		</div>
	)
}

function PlayIcon({ item }) {
	const { passToPersistentPlayer } = usePersistentPlayer()

	const handleClick = (mode) => {
		passToPersistentPlayer({
			item,
			mode          : mode || 'audio',
			isPlaying     : true,
			playedSeconds : 0.0,
		});
	}

	return (
		<PlayOverlay item={item} onClick={handleClick} />
	)
}
 
export default function PlayOverlay( { item } ) {
	return (
		<Providers>
			<PlayIcon item={item} />
		</Providers>
	)
}