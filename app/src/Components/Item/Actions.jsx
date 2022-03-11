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

export default function Actions({
  item,
}) {

  const { passToPersistentPlayer } = usePersistentPlayer();
  const { isDesktop } = useBreakpoints();

	const playVideo = (e) => {
		e.stopPropagation();
		passToPersistentPlayer({
			item,
			mode         : 'video',
			isPlaying    : true,
			playedSeconds: 0.0,
		});
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
			{isDesktop ? (
				<Box className="cpl-item--actions--buttons">
					{item.video.value && (
						<PlayVideo onClick={playVideo}/>
					)}
					{item.audio && (
						<PlayAudio onClick={playAudio}/>
					)}
				</Box>
			) : (
				<IconButton className="cpl-item--to-item" onClick={() => history.push(`${cplVar('path', 'site')}/${cplVar('slug', 'item')}/${item.slug}`)}>
					<ChevronRight/>
				</IconButton>
			)}
		</Box>
	);
}
