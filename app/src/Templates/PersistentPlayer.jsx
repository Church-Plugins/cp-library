import React, { useState, useEffect, useRef } from 'react';
import Box from '@mui/material/Box';
import VideoPlayer from "react-player";
import FilePlayer from 'react-player/file';
import Slider from '@mui/material/Slider';
import IconButton from '@mui/material/IconButton';
import ReactDOM from 'react-dom';
import screenfull from 'screenfull';

import { Cancel, Fullscreen, PictureInPicture, Forward30, Replay10, OpenInFull, PlayCircleOutline, Facebook, Twitter, Download, Link as LinkIcon } from "@mui/icons-material"

import useBreakpoints from '../Hooks/useBreakpoints';
import formatDuration from '../utils/formatDuration';
import { cplLog } from '../utils/helpers';

import LoadingIndicator from '../Elements/LoadingIndicator';
import ErrorDisplay from '../Elements/ErrorDisplay';
import PlayPause from '../Elements/Buttons/PlayPause';
import Logo from '../Elements/Logo';
import { ThemeProvider } from '@mui/material/styles';
import theme from "../Templates/Theme";
import throttle from 'lodash.throttle';

export default function PersistentPlayer(props) {
  const { isDesktop } = useBreakpoints();
  const [item, setItem] = useState(props.item);
  const [loading, setLoading] = useState(false);
  const [isPlaying, setIsPlaying] = useState(false);
  const [playedSeconds, setPlayedSeconds] = useState(0.0);
  const [playbackRate, setPlaybackRate] = useState(1 );
  const [error, setError] = useState();
  const [duration, setDuration] = useState(0.0);
	const [showFSControls, setShowFSControls]   = useState( false );
  // Video or audio
  const [mode, setMode] = useState();

	const onMouseMove = (e) => {
		if (showFSControls) return;
		showControls();
	};

	const showControls = () => {
		if ( ! screenfull.isFullscreen ) {
			return;
		}

		setShowFSControls( true );
		setTimeout(() => setShowFSControls( false ), 4000 );
	};

	const updatePlaybackRate = () => {
		switch( playbackRate ) {
			case 1:
				setPlaybackRate(1.25);
				break;
			case 1.25:
				setPlaybackRate(1.5);
				break;
			case 1.5:
				setPlaybackRate(2);
				break;
			default:
				setPlaybackRate(1);
				break;
		}
	};

	const closePlayer = () => {
		const player = window.top.document.getElementById('cpl_persistent_player');
		ReactDOM.unmountComponentAtNode(player);
    window.top.document.body.classList.remove('cpl-persistent-player');
    window.top.postMessage({
      action: "CPL_PERSISTENT_PLAYER_CLOSED",
    });
	};

	const handleClickFullscreen = () => {
		cplLog( item.id, 'fullscreen' );
		const instance = ReactDOM.findDOMNode(playerInstance.current);
		screenfull.request( instance.parentElement )
		return false;
	};

  const playerInstance = useRef();
	const desktopClass   = isDesktop ? ' is_desktop' : '';

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
				setMode(event.data.mode);

				if ( event.data.playedSeconds ) {
					playerInstance.current.seekTo(event.data.playedSeconds, 'seconds');
				}

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

  let markPosition = 0;
  let snapDiff = 60;
  const videoMarks = [];

  if( item && item.video && item.video.marker ) {
	markPosition = item.video.marker;
  }
  if( 'video' === mode && markPosition > 0 ) {
	videoMarks.push(
		{
			value: markPosition,
			label: "Sermon Start"
		}
	);
  }

  	let doScroll = ( scrollValue ) => {
		if( markPosition > 0 && Math.abs( (scrollValue - markPosition) ) < snapDiff ) {
			setPlayedSeconds( markPosition );
		} else {
			setPlayedSeconds( scrollValue );
		}
	}

	let throttleScroll = throttle(
		(scrollValue) => {
			doScroll( scrollValue );
		}, 10
	);

  return loading ? (
    <LoadingIndicator />
  ) : error ? (
    <ErrorDisplay error={error} />
  ) : item ? (
  	<ThemeProvider theme={theme}>
    <Box className={"persistentPlayer__root persistentPlayer__mode__" + mode + desktopClass }>

	    {mode === 'video' &&
	     <Box className="persistentPlayer__video">
		     <Box className="itemPlayer__video"
		          position="absolute"
		          top={0}
		          left={0}
		          width="100%"
		          height="100%"
	           onMouseMove={onMouseMove}
		     >
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

				     {!screenfull.isFullscreen && (
					     <>
						     <Box display="flex" alignItems="center" justifyContent="space-around" height="100%" width="100%"
						          position="absolute" zIndex={50} top={0} right={0}>
							     <PlayPause size={48} flex={0} padding={2} isPlaying={isPlaying}
							                onClick={() => setIsPlaying(!isPlaying)}/>
						     </Box>

						     <Box position="absolute" zIndex={50} top={0} right={0} className="persistentPlayer__close">
							     <IconButton sx={{color: '#ffffff'}} onClick={closePlayer}><Cancel/></IconButton>
						     </Box>

						     <Box position="absolute" zIndex={50} top={0} left={0} className="persistentPlayer__fullscreen">
							     <IconButton sx={{color: '#ffffff', transform: 'scalex(-1)'}}
							                 onClick={handleClickFullscreen}><OpenInFull/></IconButton>
						     </Box>
					     </>
				     )}
			     </Box>

			     {screenfull.isFullscreen && (
				     <Box className="itemPlayer__video__playWrap" onClick={() => setIsPlaying(!isPlaying)}></Box>
			     )}

			     {!showFSControls || !screenfull.isFullscreen ? null : (
				     <Box className="itemPlayer__controlsWrapper cpl-touch-hide">

					     <Box className="itemPlayer__controls" display="flex" flexDirection="row"
					          justifyContent="space-around" margin="auto">

						     <Box display="flex" alignItems="center">
							     <PlayPause flex={0} padding={2} isPlaying={isPlaying} circleIcon={false}
							                onClick={() => setIsPlaying(!isPlaying)}/>
						     </Box>

						     <IconButton
							     onClick={() => playerInstance.current.seekTo(playedSeconds - 10, 'seconds')}>
							     <Replay10 fontSize="inherit"/>
						     </IconButton>

						     <IconButton
							     onClick={() => playerInstance.current.seekTo(playedSeconds + 30, 'seconds')}>
							     <Forward30 fontSize="inherit"/>
						     </IconButton>

						     <Box className="itemPlayer__controls__rate" display="flex" alignItems="center"
						          onClick={updatePlaybackRate}>
							     <span>{playbackRate}x</span>
						     </Box>

					     </Box>

					     <Box className="itemPlayer__progress" flex={1} display="flex" flexDirection="column">
						     <Box display="flex" flexDirection="row" alignItems="center">

							 <Slider
								min={0}
								defaultValue={0}
								max={duration}
								step={.01}
								size="medium"
								value={playedSeconds}
								sx={{padding: "10px 0 !important"}}
								onChange={(_, value) => {
									setIsPlaying(false);
									setPlayedSeconds( value );
								}}
								onChangeCommitted={(_, value) => {
									setIsPlaying(true);
									playerInstance.current.seekTo(playedSeconds);
									setPlayedSeconds(value);
								}}
							/>

							     <Box
								     display="flex"
								     className="itemPlayer__remaining"
							     >
								     {formatDuration(duration - playedSeconds)}
							     </Box>
						     </Box>

					     </Box>

					     {!screenfull.isFullscreen && (
						     <Box className="itemPlayer__controls" display="flex" flexDirection="row"
						          justifyContent="space-around" margin="auto">
							     {mode === 'video' && (
								     <IconButton onClick={handleClickFullscreen}><OpenInFull/></IconButton>
							     )}
						     </Box>
					     )}

				     </Box>
			     )}

		     </Box>
	     </Box>
	    }

	    <Box className="persistentPlayer__controls" display="flex" flexDirection="row" padding={1}>


		    {(isDesktop || 'audio' === mode) && (
			    <Box display="flex" alignItems="center">
				    <PlayPause flex={0} padding={2} isPlaying={isPlaying} onClick={() => setIsPlaying(!isPlaying)}/>
			    </Box>
		    )}

		    <Box className="persistentPlayer__info" flex={1} display="flex" flexDirection="column" marginLeft={1} marginRight={1}>
			    <Box display="flex" flexDirection="row" alignItems="center" fontSize={14} >
				    <Box marginRight={1} maxWidth={"1.5em"}><Logo /></Box>
				    <Box><a href={item.permalink} dangerouslySetInnerHTML = {{ __html: item.title }} style={{ color: 'inherit' }}></a></Box>
			    </Box>
          <Box display="flex" flexDirection="row" alignItems="center">

			<Slider
				min={0}
				defaultValue={0}
				max={duration}
				step={.01}
				size="small"
				value={playedSeconds}
				sx={{padding: "10px 0 !important"}}
				marks={videoMarks}
				onChange={(_, value) => {
					setIsPlaying(false);
					throttleScroll( value );
				}}
				onChangeCommitted={(_, value) => {
					setTimeout(
						() => {
							playerInstance.current.seekTo(playedSeconds);
							setPlayedSeconds(value);
							setIsPlaying(true);
						}, 10
					);
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

		    <Box flex={0} display="flex" flexDirection="column" marginLeft={1}>
		    </Box>

        {mode === "audio" &&
         <Box>
	         <VideoPlayer
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
				         playerInstance.current.seekTo(playedSeconds, 'seconds');
				         setIsPlaying(true);
			         }
		         }}
		         onProgress={progress => setPlayedSeconds(progress.playedSeconds)}
		         progressInterval={100}
	         >
		         Your browser does not support the audio element.
	         </VideoPlayer>

	         <Box position='absolute' zIndex={50} top={0} right={0} className='persistentPlayer__close'>
		         <IconButton onClick={closePlayer}><Cancel/></IconButton>
	         </Box>
         </Box>
        }
	    </Box>
    </Box>
	  </ThemeProvider>
  ) : null;
}
