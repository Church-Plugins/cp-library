import React, { useState, useEffect, useRef } from 'react';
import Box from '@mui/material/Box';
import Divider from '@mui/material/Divider';
import { Play, Volume1, Share2 } from "react-feather"
import VideoPlayer from "react-player";
import { Link } from 'react-router-dom';

import useBreakpoints from '../Hooks/useBreakpoints';
import Controllers_WP_REST_Request from '../Controllers/WP_REST_Request';
import { usePersistentPlayer } from '../Contexts/PersistentPlayerContext';

import LoadingIndicator from './LoadingIndicator';
import ErrorDisplay from './ErrorDisplay';
import AudioPlayer from './AudioPlayer';
import ItemMeta from './ItemMeta';
import SearchInput from './SearchInput';
import RectangularButton from './RectangularButton';
import Logo from './Logo';


import { ExternalLink } from "react-feather"
import { Cancel, Forward30, Replay10, Share } from "@mui/icons-material"
import FilePlayer from 'react-player/file';
import Slider from '@mui/material/Slider';
import IconButton from '@mui/material/IconButton';
import ReactDOM from 'react-dom';
import screenful from 'screenfull';

import formatDuration from '../utils/formatDuration';

import RoundButton from './RoundButton';
import ButtonPlay from './ButtonPlay';
import { ThemeProvider } from '@mui/material/styles';
import theme from "../Components/Theme";


export default function ItemDetail({
  itemId,
}) {
  const { isDesktop } = useBreakpoints();
  const [item, setItem] = useState();
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState();
  // Video or audio
  const [mode, setMode] = useState();
  const [showPlayer, setShowPlayer] = useState(false);
  const [isPlaying, setIsPlaying] = useState(false);
  const [playedSeconds, setPlayedSeconds] = useState(0.0);
  const [duration, setDuration] = useState(0.0);
  const [playingURL, setPlayingURL] = useState('');

  // Keep frequently-updated states (mainly the progress from the media player) as a ref so they
  // don't trigger re-render.
  const mediaState = useRef({});
  const { isActive: persistentPlayerIsActive, passToPersistentPlayer } = usePersistentPlayer();
  const playerInstance = useRef();
	const playingClass   = isPlaying ? ' is_playing' : '';

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

	const updateMode = (mode) => {
		setMode(mode);
		setPlayedSeconds(0);
		setPlayingURL( 'video' === mode ? item.video.value : item.audio );
	};

  // Fetch the individual item when mounted.
  useEffect(() => {
    (async () => {
      try {
        setLoading(true);
        const restRequest = new Controllers_WP_REST_Request();
        const data = await restRequest.get( {endpoint: `items/${itemId}`} );
        setItem(data);
      } catch (error) {
        setError(error);
      } finally {
        setLoading(false);
      }
    })();
  }, []);

  // Sync some states to be possibly passed to the persistent player. These states could be gone by
  // the time the clean up function is done during unmounting.
  useEffect(() => {
    mediaState.current = { ...mediaState.current, item, mode };
  }, [item, mode])

  // When unmounted, if media is still playing, hand it off to the persistent player.
  useEffect(() => {
    return () => {
      if (mediaState.current.isPlaying) {
        passToPersistentPlayer({
          item: mediaState.current.item,
          mode: mediaState.current.mode,
          isPlaying: true,
          playedSeconds: mediaState.current.playedSeconds,
        });
      }
    }
  }, [])

  // If item has both video and audio, prefer video.
  useEffect(() => {
    if (!item) return;

    if (item.video.value) {
      updateMode("video");
    } else if (item.audio) {
      updateMode("audio");
    }
  }, [item]);

  return loading ? (
    <LoadingIndicator />
  ) : error ? (
    <ErrorDisplay error={error} />
  ) : (
    // Margin bottom is to account for audio player. Making sure all content is still visible with
    // the player is up.
    <Box className={"itemDetail__root" + playingClass} padding={2} marginBottom={mode === "audio" ? 10 : 0}>
      <Link to="/talks">{"<"} Back to talks</Link>
      {isDesktop && (
        <>
          <Box display="flex" justifyContent="space-between">
            <h1 className="itemDetail__header">Talks</h1>
            {/* TODO: Think about who's responsible for search, e.g. here or a global search provider */}
            <Box className="itemDetail__search" marginLeft={1} display="flex" alignItems="center">
              <SearchInput onValueChange={console.log} />
            </Box>
          </Box>
          <Divider className="itemDetail__divider" sx={{ marginY: 2 }} />
        </>
      )}
      <Box display="flex" flexDirection={isDesktop ? "row" : "column"}>
        <Box className="itemDetail__leftContent" flex={1} flexBasis="40%" marginRight={isDesktop ? 2 : 0}>
          <h1 className="itemDetail__title">{item.title}</h1>
          {isDesktop ? (
            <>
              <Box className="itemDetail__itemMeta" marginTop={4}>
                <ItemMeta date={item.date.date} category={item.category} />
              </Box>

              <Box className="itemDetail__description" marginTop={4}>
                <p>{item.desc}</p>
              </Box>
            </>
          ) : (
            <Divider
              className="itemDetail__divider itemDetail__shortDivider"
              sx={{ width: 58, height: 6, marginY: 2 }}
            />
          )}
        </Box>

        <Box className="itemDetail__rightContent" flex={1} flexBasis="60%">
          {/* TODO: Componentize as <FeatureImage />. These could be the same thing as the ones in the item list */}
          <Box
            className="itemDetail__featureImage"
            position="relative"
            paddingTop="56.26%"
            backgroundColor={mode === "audio" ? "#C4C4C4" : "transparent"}
            marginTop={isDesktop ? 0 : 1}
          >
	          <Box className="itemPlayer__video"
	               position="absolute"
	               top={0}
	               left={0}
	               width="100%"
	               height="100%"
	          >
		          <VideoPlayer
			          ref={playerInstance}
			          className="itemDetail__video"
			          url={playingURL}
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

		          {mode === 'video' ? (
			          <Box className="itemPlayer__video__controls">

				          <Box display="flex" alignItems="center" justifyContent="space-around" height="100%" width="100%"
				               position="absolute" zIndex={50} top={0} right={0}>
					          <ButtonPlay size={48} flex={0} padding={2} isPlaying={isPlaying}
					                      onClick={() => setIsPlaying(!isPlaying)}/>
				          </Box>

				          <Box position="absolute" zIndex={50} top={0} left={0} className="itemPlayer__fullscreen">
					          <IconButton sx={{color: '#ffffff', transform: 'scalex(-1)'}}
					                      onClick={handleClickFullscreen}><ExternalLink/></IconButton>
				          </Box>

			          </Box>

		          ) : (
			          <Box
				          className="itemDetail__audio"
				          display="flex"
				          alignItems="center"
				          justifyContent="center"
				          height="100%"
				          width="100%"
				          position="absolute"
				          top={0}
				          left={0}
			          >
				          <Logo/>
			          </Box>
		          )}
	          </Box>


          </Box>

          {isDesktop ? null : (
            <Box className="itemDetail__category" marginTop={1}>
              <span>CATEGORIES: {Object.values(item.category).join(", ")}</span>
            </Box>
          )}

          <Box className="itemDetail__actions" display="flex" alignItems="stretch" marginTop={2}>
            <Box className="itemDetail__playVideo" flex={1}>
              <RectangularButton
                leftIcon={<Play />}
                onClick={() => {updateMode("video")}}
                // disabled={!item.video || mode === "video"}
                fullWidth
              >
                Play Video
              </RectangularButton>
            </Box>
            <Box className="itemDetail__playAudio" flex={1} marginLeft={1}>
              <RectangularButton
                variant="outlined"
                leftIcon={<Volume1 />}
                onClick={() => {
                  if (persistentPlayerIsActive) {
                    passToPersistentPlayer({
                      item: mediaState.current.item,
                      mode: "audio",
                      isPlaying: true,
                      playedSeconds: 0.0,
                    });
                  } else {
                    updateMode("audio");
                  }
                }}
                // disabled={!item.audio || mode === "audio"}
                fullWidth
              >
                Play Audio
              </RectangularButton>
            </Box>
            <Box
              className="itemDetail__share"
              flex={0}
              marginLeft={1}
            >
              <RectangularButton variant="outlined">
                <Share2 />
              </RectangularButton>
            </Box>
          </Box>

	        <Box className="itemPlayer__controlsWrapper">
		        <Box className="itemPlayer__progress" flex={1} display="flex" flexDirection="column" marginLeft={1}
		             marginRight={1}>
			        <Box display="flex" flexDirection="row" alignItems="center">

				        <Slider
					        min={0}
					        defaultValue={0}
					        max={duration}
					        step={.01}
					        size="medium"
					        value={playedSeconds}
					        sx={{padding: '10px 0 !important'}}
					        onChange={(_, value) => {
						        setIsPlaying(false);
						        setPlayedSeconds(value);
					        }}
					        onChangeCommitted={(_, value) => {
						        setIsPlaying(true);
						        playerInstance.current.seekTo(playedSeconds);
						        setPlayedSeconds(value);
					        }}
				        />

			        </Box>
			        <Box className="itemPlayer__duration" display="flex" flexDirection="row" justifyContent="space-between">
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

		        <Box className="itemPlayer__controls" display="flex" flexDirection="row" padding={1}
		             justifyContent="space-around" margin="auto">

			        <Box display="flex" alignItems="center">
				        <span>1x</span>
			        </Box>

			        <IconButton><Replay10/></IconButton>

			        <Box display="flex" alignItems="center">
				        <ButtonPlay flex={0} padding={2} isPlaying={isPlaying} onClick={() => setIsPlaying(!isPlaying)}/>
			        </Box>
			        <IconButton><Forward30/></IconButton>
			        <IconButton sx={{color: '#ffffff', transform: 'scaley(-1)'}}
			                    onClick={handleClickFullscreen}><ExternalLink/></IconButton>

			        <Box flex={0} display="flex" flexDirection="column" marginLeft={1}>
			        </Box>

			        {mode === 'audio1324' &&
			         <Box>
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
							         playerInstance.current.seekTo(playedSeconds, 'seconds');
							         setIsPlaying(true);
						         }
					         }}
					         onProgress={progress => setPlayedSeconds(progress.playedSeconds)}
					         progressInterval={100}
				         >
					         Your browser does not support the audio element.
				         </FilePlayer>

				         <Box position='absolute' zIndex={50} top={0} right={0} className='itemPlayer__close'>
					         <IconButton onClick={closePlayer}><Cancel/></IconButton>
				         </Box>
			         </Box>
			        }
		        </Box>

	        </Box>


        </Box>
      </Box>

      {isDesktop ? null : (
        <Box className="itemDetail__description" marginTop={2}>
          <p>{item.desc}</p>
        </Box>
      )}

    </Box>
  );
}
