import React, { useState, useEffect } from 'react';
import Box from '@mui/material/Box';
import Divider from '@mui/material/Divider';
import { Play, Volume1, Share2 } from 'react-feather';
import * as VideoPlayer from 'react-player/vimeo';
import ReactDOM from 'react-dom';

import useBreakpoints from '../Hooks/useBreakpoints';
import Controllers_WP_REST_Request from '../Controllers/WP_REST_Request';

import LoadingIndicator from './LoadingIndicator';
import ErrorDisplay from './ErrorDisplay';
import AudioPlayer from './AudioPlayer';
import ItemMeta from './ItemMeta';
import SearchInput from './SearchInput';
import RectangularButton from './RectangularButton';
import { usePersistentPlayer, PersistentPlayerProvider } from '../Contexts/PersistentPlayerContext';

const TESTING_ID = 123;

export default function ItemWidget() {

  return (
    <PersistentPlayerProvider>
      <ItemWidgetContent />
    </PersistentPlayerProvider>
  );
};

export function ItemWidgetContent ({
	// TODO: How to get the id? Can we pass it in to the React component?
	itemId = TESTING_ID,
}) {
  const { passToPersistentPlayer } = usePersistentPlayer();
	const {isDesktop} = useBreakpoints();
	const [item, setItem] = useState();
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState();

	useEffect(() => {
		let itemIdToFetch = itemId;

		if (itemIdToFetch === undefined) {
			// TODO: Parse the URL to get the real item id?
			const urlSearchParams = new URLSearchParams(window.location.search);
			itemIdToFetch = urlSearchParams.get('itemId');
		}

		(
			async () => {
				try {
					setLoading(true);
					const restRequest = new Controllers_WP_REST_Request();
					const data = await restRequest.get({endpoint: `items/?count=1&media-type=audio`});
					setItem(data.items[0]);
				} catch (error) {
					setError(error);
				} finally {
					setLoading(false);
				}
			}
		)();
	}, []);

	const playVideo = () => {
		passToPersistentPlayer({
			item,
			mode         : 'video',
			isPlaying    : true,
			playedSeconds: 0.0,
		});
	};

	const playAudio = () => {
		passToPersistentPlayer({
      item,
      mode: "audio",
      isPlaying: true,
      playedSeconds: 0.0,
    });
	};

	return loading ? (
		<LoadingIndicator/>
	) : error ? (
		<ErrorDisplay error={error}/>
	) : (
		// Margin bottom is to account for audio player. Making sure all content is still visible with
		// the player is up.
    <PersistentPlayerProvider>
			<Box className="itemWidget__root">
				<Box className="itemWidget__content">
					<Box className="itemWidget__itemMeta">
						<ItemMeta date={item.date} category={[]}/>
					</Box>
					<h1 className="itemWidget__title">{item.title}</h1>
					<Box className="itemWidget__description">
						<p>{item.desc}</p>
					</Box>

					<Box className="itemWidget__actions" display="flex" alignItems="flex-start">

						{item.video.value &&
						 <Box className="itemWidget__playVideo" marginRight={1}>
							 <RectangularButton
								 leftIcon={<Play/>}
								 onClick={playVideo}
							 >
								 Play Video
							 </RectangularButton>
						 </Box>
						}

						{item.audio &&
						 <Box className="itemWidget__playAudio">
							 <RectangularButton
								 variant="outlined"
								 leftIcon={<Volume1/>}
								 onClick={playAudio}
							 >
								 Play Audio
							 </RectangularButton>
						 </Box>
						}

					</Box>

				</Box>

			</Box>
    </PersistentPlayerProvider>
	);
}
