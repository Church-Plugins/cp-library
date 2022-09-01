import React from 'react';
import IconButton from '@mui/material/IconButton';
import { ChevronRight } from "react-feather"
import { useHistory } from "react-router-dom";
import { cplVar } from '../../utils/helpers';
import Box from '@mui/material/Box';
import useBreakpoints from '../../Hooks/useBreakpoints';

import { usePersistentPlayer } from '../../Contexts/PersistentPlayerContext';

import PlayAudio from '../../Elements/Buttons/PlayAudio';
import PlayVideo from '../../Elements/Buttons/PlayVideo';

import jQuery from 'jquery';

export default function Actions({
  item,
}) {

  const { passToPersistentPlayer } = usePersistentPlayer();
  const { isDesktop } = useBreakpoints();

	const viewItem = (e) => {
		e.stopPropagation();

		if ( undefined !== history ) {
			history.push(`${cplVar('path', 'site')}/${cplVar('slug', 'item')}/${item.slug}`);
		} else {
			window.location = item.permalink;
		}
	};

	const playVideo = (e) => {
		e.stopPropagation();
		passToPersistentPlayer({
			item,
			mode         : 'video',
			isPlaying    : true,
			playedSeconds: 0.0,
		});

		// Slider mark may load up to a second after the frame is open
		setTimeout(
			() => {
				let element = jQuery( '.MuiSlider-root.MuiSlider-marked .MuiSlider-mark' );
				jQuery( element ).attr( 'title', 'Jump to Sermon' );
			}, 1500
		);

	};

	const playAudio = (e) => {
		e.stopPropagation();
		passToPersistentPlayer({
      item,
      mode: "audio",
      isPlaying: true,
      playedSeconds: 0.0,
    });
	};

  const history = useHistory();

	return (
		<Box className="cpl-list-item--actions">
				<Box className="cpl-list-item--actions--buttons">
					{item.video.value && (
						<PlayVideo onClick={playVideo}/>
					)}
					{item.audio && (
						<PlayAudio onClick={playAudio}/>
					)}
				</Box>
		</Box>
	);
}
