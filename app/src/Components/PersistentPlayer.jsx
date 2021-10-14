import React, { useState, useEffect } from 'react';
import Box from '@mui/material/Box';
import Divider from '@mui/material/Divider';
import { Play, Pause, Volume1, Share2 } from "react-feather"
import * as VideoPlayer from "react-player/vimeo";
import ReactPlayer from "react-player";
import CardMedia from '@mui/material/CardMedia';
import Slider from '@mui/material/Slider';
import ReactDOM from 'react-dom';

import useBreakpoints from '../Hooks/useBreakpoints';
import Controllers_WP_REST_Request from '../Controllers/WP_REST_Request';

import LoadingIndicator from './LoadingIndicator';
import ErrorDisplay from './ErrorDisplay';
import AudioPlayer from './AudioPlayer';
import ItemMeta from './ItemMeta';
import SearchInput from './SearchInput';
import RoundButton from './RoundButton';

const TESTING_ID = 123;

export default function PersistentPlayer( propItem ) {
  const { isDesktop } = useBreakpoints();
  const [item, setItem] = useState( propItem.item );
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState();
  // Video or audio
  const [mode, setMode] = useState();
  const [playing, setPlaying] = useState( false );
  const [progress, setProgress] = useState( 0 );
	const [player, setPlayer] = useState();

	const ref = thisPlayer => {
		setPlayer(thisPlayer);
	};

	const closePlayer = () => {
		let player = document.getElementById('cpl_persistent_player');
		ReactDOM.unmountComponentAtNode(player);
    document.body.classList.remove('cpl-persistent-player');
	};

  useEffect(() => {
    (async () => {
      try {
//        setLoading(true);
        const restRequest = new Controllers_WP_REST_Request();
        const data = await restRequest.get( {endpoint: `items/${item.id}`} );
        setItem(data);
      } catch (error) {
        setError(error);
      } finally {
        setLoading(false);
      }
    })();
  }, []);

  useEffect(() => {
    if (!item) return;

    document.body.classList.add('cpl-persistent-player');

    if (item.video && item.video.value) {
      setMode("video");
    } else if (item.audio) {
      setMode("audio");
    }
  }, [item]);

  return loading ? (
    <LoadingIndicator />
  ) : error ? (
    <ErrorDisplay error={error} />
  ) : (
    <Box className="persistentPlayer__root" padding={2}>

	    <Box className="persistentPlayer__controls" display="flex" flexDirection="row">

		    <RoundButton flex={0} onClick={() => setPlaying(!playing)}>{playing ? <Pause/> : <Play/>}</RoundButton>

		    <Box className="persistentPlayer__info" flex={1} display="flex" flexDirection="column" marginLeft={2}>
			    <span>{item.title}</span>
			    <Slider min={0} defaultValue={0} max={1} step={.01} value={progress} onMouseUp={() => player.seekTo(progress)}
			            onChange={(_, value) => setProgress(value)}/>
		    </Box>

		    <Box flex={0} display="flex" flexDirection="column" marginLeft={2}>
            <RoundButton onClick={() => closePlayer()}>X</RoundButton>
		    </Box>



            {mode === "video" ? (
              <VideoPlayer
                className="itemDetail__video"
                // TODO: Replace with real item.video
                url="https://player.vimeo.com/video/621748162"
                controls={true}
                width="100%"
                height="100%"
                style={{ position: "absolute", top: 0, left: 0 }}
                playing={playing}
              />
            ) : (
              <Box
                className="itemDetail__audio"
                display="block"
                alignItems="center"
                justifyContent="center"
//                height="100%"
//                width="100%"
//                position="absolute"
//                top={0}
//                left={0}
              >
	              <Box
		              className="audioPlayer__controls"
		              paddingX={2}
		              paddingY={1}
		              sx={{pointerEvents: 'auto'}}
	              >
		              <ReactPlayer
			              ref={ref}
			              className="itemDetail__audio"
			              url={item.audio}
			              controls={true}
			              width="100%"
			              height="100%"
			              playing={playing}
			              onProgress={(state) => {console.log(state);setProgress(state.played);}}
		              />
		              {/*<CardMedia component="audio" controls src={item.audio} sx={{width: '300px'}} autoPlay>*/}
			            {/*  Your browser does not support the audio element.*/}
		              {/*</CardMedia>*/}

	              </Box>
              </Box>
            )}

	    </Box>
    </Box>
  );
}
