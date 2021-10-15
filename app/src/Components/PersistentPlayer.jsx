import React, { useState, useEffect, useRef } from 'react';
import Box from '@mui/material/Box';
import { Play, Pause, Volume1, Share2 } from "react-feather"
import * as VideoPlayer from "react-player/vimeo";
import FilePlayer from 'react-player/file';
import Slider from '@mui/material/Slider';
import ReactDOM from 'react-dom';

import useBreakpoints from '../Hooks/useBreakpoints';
import Controllers_WP_REST_Request from '../Controllers/WP_REST_Request';

import LoadingIndicator from './LoadingIndicator';
import ErrorDisplay from './ErrorDisplay';
import ItemMeta from './ItemMeta';
import RoundButton from './RoundButton';
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
    <Box className="persistentPlayer__root" padding={2}>
	    <Box className="persistentPlayer__controls" display="flex" flexDirection="row">

        <RoundButton flex={0} onClick={() => setIsPlaying(!isPlaying)}>
          {isPlaying ? <Pause/> : <Play/>}
        </RoundButton>

		    <Box className="persistentPlayer__info" flex={1} display="flex" flexDirection="column" marginLeft={2}>
			    <span>{item.title}</span>
          <Box display="flex" flexDirection="row" alignItems="center">
            <Box
              className="persistentPlayer__progress"
              width={72}
              display="flex"
              justifyContent="flex-start"
            >
              {formatDuration(playedSeconds)}
            </Box>
            <Slider
              min={0}
              defaultValue={0}
              max={duration}
              step={.01}
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
              sx={{ marginX: 2 }}
            />
            <Box
              className="persistentPlayer__duration"
              width={72}
              display="flex"
              justifyContent="flex-end"
            >
              {formatDuration(duration)}
            </Box>
          </Box>
		    </Box>

		    <Box flex={0} display="flex" flexDirection="column" marginLeft={2}>
          <RoundButton onClick={closePlayer}>X</RoundButton>
		    </Box>

        {mode === "video" ? (
          <VideoPlayer
            className="itemDetail__video"
            url={item.video}
            controls={true}
            width="100%"
            height="100%"
            style={{ position: "absolute", top: 0, left: 0 }}
            playing={true}
          />
        ) : (
          <FilePlayer
            ref={playerInstance}
            controls={false}
            url={item.audio}
            width="0"
            height="0"
            playing={isPlaying}
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
        )}
	    </Box>
    </Box>
  ) : null;
}
