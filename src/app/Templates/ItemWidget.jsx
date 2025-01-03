import { useState, useEffect } from 'react';
import Box from '@mui/material/Box';
import { Play, Volume1 } from 'react-feather';
import Controllers_WP_REST_Request from '../Controllers/WP_REST_Request';
import { cplLog } from '../utils/helpers';
import LoadingIndicator from '../Elements/LoadingIndicator';
import ErrorDisplay from '../Elements/ErrorDisplay';
import ItemMeta from './ItemMeta';
import Rectangular from '../Elements/Buttons/Rectangular';
import api from '../api';
import Providers from '../Contexts/Providers';

export default function ItemWidget( { item } ) {
  return (
  	<Providers>
			<ItemWidgetContent widgetItem={item}  />
		</Providers>
  );
};

export function ItemWidgetContent ({
	widgetItem
}) {
	const [item, setItem] = useState();
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState();

	useEffect(() => {
		if ( undefined !== widgetItem ) {
			setItem(widgetItem);
			setLoading(false);
		} else {
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
		}
	}, []);

	const playVideo = () => {
		cplLog( item.id, 'video_widget_play' );
		api.passToPersistentPlayer({
			item,
			mode         : 'video',
			isPlaying    : true,
			playedSeconds: 0.0,
		});
	};

	const playAudio = () => {
		cplLog( item.id, 'audio_widget_play' );
		api.passToPersistentPlayer({
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
					<ItemMeta date={item.date.desc} category={[]}/>
				</Box>

				<h1 className="itemWidget__title" dangerouslySetInnerHTML={{ __html: item.title }} />
				<Box className="itemWidget__description" dangerouslySetInnerHTML={{ __html: item.desc }}/>

				<Box className="itemWidget__actions" display="flex" alignItems="flex-start">

					{item.video.value &&
					 <Box className="itemWidget__playVideo" marginRight={1}>
						 <Rectangular
							 leftIcon={<Play/>}
							 onClick={playVideo}
						 >
							 Play Video
						 </Rectangular>
					 </Box>
					}

					{item.audio &&
					 <Box className="itemWidget__playAudio">
						 <Rectangular
							 variant="outlined"
							 leftIcon={<Volume1/>}
							 onClick={playAudio}
							 sx={{height: 55, borderRadius: 2, color: "#fff", borderColor: "#fff"}}
						 >
							 Play Audio
						 </Rectangular>
					 </Box>
					}

				</Box>

			</Box>
		</Box>
	);
}
