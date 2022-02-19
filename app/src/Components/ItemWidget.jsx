import React, { useState, useEffect } from 'react';
import Box from '@mui/material/Box';
import Divider from '@mui/material/Divider';
import { Play, Volume1, Share2 } from 'react-feather';
import * as VideoPlayer from 'react-player/vimeo';
import ReactDOM from 'react-dom';

import useBreakpoints from '../Hooks/useBreakpoints';
import Controllers_WP_REST_Request from '../Controllers/WP_REST_Request';

import LoadingIndicator from '../Elements/LoadingIndicator';
import ErrorDisplay from '../Elements/ErrorDisplay';
import ItemMeta from './ItemMeta';
import SearchInput from '../Elements/SearchInput';
import RectangularButton from '../Elements/RectangularButton';
import { usePersistentPlayer, PersistentPlayerProvider } from '../Contexts/PersistentPlayerContext';
import { ThemeProvider } from '@mui/material/styles';
import theme from "../Components/Theme";

const TESTING_ID = 123;

export default function ItemWidget() {

  return (
  	<ThemeProvider theme={theme}>
	    <PersistentPlayerProvider>
	      <ItemWidgetContent />
	    </PersistentPlayerProvider>
	  </ThemeProvider>
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
		<Box className="itemWidget__root">
			<Box className="itemWidget__content">
				<Box className="itemWidget__itemMeta">
					<ItemMeta date={item.date.date} category={[]}/>
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
							 sx={{height: 55, borderRadius: 2, color: "#fff", borderColor: "#fff"}}
						 >
							 Play Audio
						 </RectangularButton>
					 </Box>
					}

				</Box>

			</Box>
		</Box>
	);
}
