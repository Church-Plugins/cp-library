/**
 * Displays a play overlay on top of the thumbnail.
 */

import React from 'react';
import Providers from '../Contexts/Providers';
import { usePersistentPlayer } from '../Contexts/PersistentPlayerContext';

function PlayIcon({ item }) {
	const { passToPersistentPlayer } = usePersistentPlayer()
	
	const handleClick = () => {
		passToPersistentPlayer({
			item,
			mode          : 'video',
			isPlaying     : true,
			playedSeconds : 0.0,
		});
	}

	return (
		<div className='cpl-play-btn-overlay' onClick={handleClick}>
			<i data-feather="play" width="30%" height="30%" fill="currentColor"></i>
		</div>
	)
}
 
export default function PlayOverlay( { item } ) {
	return (
		<Providers>
			<PlayIcon item={item} />
		</Providers>
	)
}