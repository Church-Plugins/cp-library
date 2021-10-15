import React, { useState, useEffect, useRef } from 'react';
import Box from '@mui/material/Box';
import { ExternalLink } from "react-feather"
import { Cancel } from "@mui/icons-material"
import VideoPlayer from "react-player";
import FilePlayer from 'react-player/file';
import Slider from '@mui/material/Slider';
import IconButton from '@mui/material/IconButton';
import ReactDOM from 'react-dom';
import screenful from 'screenfull';

import useBreakpoints from '../Hooks/useBreakpoints';
import Controllers_WP_REST_Request from '../Controllers/WP_REST_Request';

import LoadingIndicator from './LoadingIndicator';
import ErrorDisplay from './ErrorDisplay';
import ItemMeta from './ItemMeta';
import RoundButton from './RoundButton';
import ButtonPlay from './ButtonPlay';
import formatDuration from '../utils/formatDuration';

export default function PersistentPlayer(props) {
  const { isDesktop } = useBreakpoints();
  const [item, setItem] = useState(props.item);
  const [loading, setLoading] = useState(false);
  const [isPlaying, setIsPlaying] = useState(false);
  const [playedSeconds, setPlayedSeconds] = useState(0.0);
  const [error, setError] = useState();
  const [duration, setDuration] = useState(0.0);
  // Video or audio
  const [mode, setMode] = useState();

	const closePlayer = () => {
		const player = window.top.document.getElementById('cpl_persistent_player');
		ReactDOM.unmountComponentAtNode(player);
    window.top.document.body.classList.remove('cpl-persistent-player');
    window.top.postMessage({
      action: "CPL_PERSISTENT_PLAYER_CLOSED",
    });
	};

	const handleClickFullscreen = () => {
		const instance = ReactDOM.findDOMNode(playerInstance.current);
		screenful.request( instance )
	};

  const playerInstance = useRef();

  useEffect(() => {
    window.top.postMessage({
      action: "CPL_PERSISTENT_PLAYER_MOUNTED",
      item,
    });

    return () => {
      window.top.postMessage({
        action: "CPL_PERSISTENT_PLAYER_UNMOUNTED",
      });
    }
  }, []);

  useEffect(() => {
    function handleMessage(event) {
      if (event.data.action === "CPL_HANDOVER_TO_PERSISTENT") {
        setItem(event.data.item);
        console.log( item );
        console.log( event.data );
				setMode(event.data.mode);
        setPlayedSeconds(event.data.playedSeconds);
        setIsPlaying(event.data.playedSeconds > 0 ? false : event.data.isPlaying);
      }
    }

    window.top.addEventListener("message", handleMessage);

    return () => {
      window.top.removeEventListener("message", handleMessage);
    }
  }, [])

  useEffect(() => {
    // Since item and mode are different pieces of state that are set separately, we wait until both
    // are set.
    if (!item || !mode) return;

    if (!window.top.document.body.classList.contains("cpl-persistent-player")) {
      window.top.document.body.classList.add('cpl-persistent-player');
    }

    window.top.postMessage({
      action: "CPL_PERSISTENT_RECEIVED_ITEM",
      item,
      mode,
    });
  }, [item, mode]);

  return loading ? (
    <LoadingIndicator />
  ) : error ? (
    <ErrorDisplay error={error} />
  ) : item ? (
    <Box className="persistentPlayer__root">

	    {mode === 'video' &&
		     <Box className="persistentPlayer__video">
			     <VideoPlayer
				     ref={playerInstance}
				     className="itemDetail__video"
				     url={item.video.value}
				     width="100%"
				     height="100%"
				     controls={false}
				     playing={isPlaying}
				     onPlay={() => setIsPlaying(true)}
				     onPause={() => setIsPlaying(false)}
				     onDuration={duration => {
					     setDuration(duration);
					     playerInstance.current.seekTo(playedSeconds, 'seconds');
					     setIsPlaying(true);
				     }}
				     onProgress={progress => setPlayedSeconds(progress.playedSeconds)}
				     progressInterval={100}
			     />

			     <Box className="persistentPlayer__video__controls">

				     <Box display="flex" alignItems="center" justifyContent="space-around" height="100%" width="100%" position="absolute" zIndex={50} top={0} right={0} >
					     <ButtonPlay size="small" flex={0} padding={2} isPlaying={isPlaying}
					                 onClick={() => setIsPlaying(!isPlaying)}/>
				     </Box>

				     <Box position="absolute" zIndex={50} top={0} right={0} className="persistentPlayer__close">
					     <IconButton sx={{color: '#ffffff'}} onClick={closePlayer}><Cancel/></IconButton>
				     </Box>

				     <Box position="absolute" zIndex={50} top={0} left={0} className="persistentPlayer__fullscreen">
					     <IconButton sx={{color: '#ffffff', transform: 'scalex(-1)'}}
					                 onClick={handleClickFullscreen}><ExternalLink/></IconButton>
				     </Box>

			     </Box>


		     </Box>
	    }

	    <Box className="persistentPlayer__controls" display="flex" flexDirection="row" padding={2}>

		    <Box display="flex" alignItems="center">
			    <ButtonPlay size="small" flex={0} padding={2} isPlaying={isPlaying} onClick={() => setIsPlaying(!isPlaying)} />
		    </Box>

		    <Box className="persistentPlayer__info" flex={1} display="flex" flexDirection="column" marginLeft={2}>
			    <Box display="flex" flexDirection="row" alignItems="center" fontSize={14} >
				    <Box marginRight={1} maxWidth={"1.5em"}><img src={window.cplParams.logo} /></Box>
				    <Box>{item.title}</Box>
			    </Box>
          <Box display="flex" flexDirection="row" alignItems="center">

            <Slider
              min={0}
              defaultValue={0}
              max={duration}
              step={.01}
              size="small"
              value={playedSeconds}
              onChange={(_, value) => {
                setIsPlaying(false);
                setPlayedSeconds(value)
              }}
              onChangeCommitted={(_, value) => {
                setIsPlaying(true);
                playerInstance.current.seekTo(playedSeconds);
                setPlayedSeconds(value);
              }}
            />

          </Box>
			    <Box className="persistentPlayer__duration" display="flex" flexDirection="row" justifyContent="space-between">
				    <Box
					    display="flex"
					    justifyContent="flex-start"
				    >
					    {formatDuration(playedSeconds)}
				    </Box>
				    <Box
					    display="flex"
					    justifyContent="flex-end"
				    >
					    -{formatDuration(duration - playedSeconds)}
				    </Box>
			    </Box>
		    </Box>

		    <Box flex={0} display="flex" flexDirection="column" marginLeft={2}>
		    </Box>

        {mode === "audio" &&
          <FilePlayer
            ref={playerInstance}
            controls={false}
            url={item.audio}
            width="0"
            height="0"
            playing={isPlaying}
            onPlay={() => setIsPlaying(true)}
            onPause={() => setIsPlaying(false)}
            onDuration={duration => {
              setDuration(duration);
              if (playedSeconds > 0) {
                playerInstance.current.seekTo(playedSeconds, "seconds");
                setIsPlaying(true);
              }
            }}
            onProgress={progress => setPlayedSeconds(progress.playedSeconds)}
            progressInterval={100}
          >
            Your browser does not support the audio element.
          </FilePlayer>
        }
	    </Box>
    </Box>
  ) : null;
}
