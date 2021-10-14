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
import PersistentPlayer from './PersistentPlayer';

const TESTING_ID = 123;

export default function ItemWidget ({
	// TODO: How to get the id? Can we pass it in to the React component?
	itemId = TESTING_ID,
}) {
	const {isDesktop} = useBreakpoints();
	const [item, setItem] = useState();
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState();
	// Video or audio
	const [mode, setMode] = useState();

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
					const data = await restRequest.get({endpoint: `items/${itemIdToFetch}`});
					setItem(data);
				} catch (error) {
					setError(error);
				} finally {
					setLoading(false);
				}
			}
		)();
	}, []);

	useEffect(() => {
		if (!item) {
			return;
		}

		if (item.video) {
			setMode('video');
		} else if (item.audio) {
			setMode('audio');
		}
	}, [item]);

	const playVideo = () => {
		let player = document.getElementById('cpl_persistent_player');
		ReactDOM.unmountComponentAtNode(player);
		ReactDOM.render(<PersistentPlayer item={{video: item.video}}/>, player);
	};

	const playAudio = () => {
		let player = document.getElementById('cpl_persistent_player');
		ReactDOM.unmountComponentAtNode(player);
		ReactDOM.render(<PersistentPlayer item={{audio: item.audio}}/>, player);
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
					<ItemMeta date={item.date} category={[]}/>
				</Box>
				<h1 className="itemWidget__title">{item.title}</h1>
				<Box className="itemWidget__description">
					<p>{item.desc}</p>
				</Box>

				<Box className="itemWidget__actions" display="flex" alignItems="flex-start">

					{item.video &&
					 <Box className="itemWidget__playVideo">
						 <RectangularButton
							 leftIcon={<Play/>}
							 onClick={playVideo}
						 >
							 Play Video
						 </RectangularButton>
					 </Box>
					}

					{item.audio &&
					 <Box className="itemWidget__playAudio" marginLeft={1}>
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

			<AudioPlayer open={mode === 'audio'} src={item.audio}/>
		</Box>
	);
}
