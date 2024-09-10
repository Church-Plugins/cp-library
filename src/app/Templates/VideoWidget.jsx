import { useState, useEffect } from 'react';
import Box from '@mui/material/Box';
import useBreakpoints from '../Hooks/useBreakpoints';
import Controllers_WP_REST_Request from '../Controllers/WP_REST_Request';
import { cplLog } from '../utils/helpers';
import LoadingIndicator from '../Elements/LoadingIndicator';
import ErrorDisplay from '../Elements/ErrorDisplay';
import PlayCircleOutline from "@mui/icons-material/PlayCircleOutline";
import Logo from '../Elements/Logo';
import Providers from '../Contexts/Providers';
import api from '../api';

export default function ItemWidget( { item } ) {
  return (
  	<Providers>
			<ItemWidgetContent widgetItem={item} />
		</Providers>
  );
};

export function ItemWidgetContent({ widgetItem }) {
	const {isDesktop} = useBreakpoints();
	const [item, setItem] = useState();
	const [loading, setLoading] = useState(true);
	const [error, setError] = useState();
	const [displayBg, setDisplayBG]   = useState( {backgroundColor: "#C4C4C4"} );

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
						const data = await restRequest.get({endpoint: `items/?count=1&media-type=video`});
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

  useEffect(() => {
    if (!item) return;

    if ( item.thumb ) {
    	setDisplayBG( { background: "url(" + item.thumb + ")", backgroundSize: "cover" } );
    }

  }, [item]);

	const playVideo = () => {
		cplLog( item.id, 'video_widget_play' );
		api.passToPersistentPlayer({
			item,
			mode         : 'video',
			isPlaying    : true,
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

          <Box
            className="itemDetail__featureImage"
            position="relative"
            paddingTop="56.26%"
            backgroundColor={"transparent"}
            marginTop={isDesktop ? 0 : 1}
            onClick={playVideo}
            style={{cursor: 'pointer'}}
          >
	          <Box className="itemPlayer__video"
	               position="absolute"
	               top={0}
	               left={0}
	               width="100%"
	               height="100%"
	          >
		          <Box
			          className="itemDetail__audio"
			          sx={displayBg}
			          display="flex"
			          alignItems="center"
			          justifyContent="center"
			          height="100%"
			          width="100%"
			          position="absolute"
			          top={0}
			          left={0}
		          >
			          {item.thumb ? (
				          <PlayCircleOutline sx={{fontSize: 56, color: "white"}}/>
			          ) : (
				          <Logo height="50%"/>
			          )}
		          </Box>

          </Box>

				</Box>

			</Box>
		</Box>
	);
}
