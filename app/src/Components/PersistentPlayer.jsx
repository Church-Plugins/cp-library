import React, { useState, useEffect } from 'react';
import Box from '@mui/material/Box';
import Divider from '@mui/material/Divider';
import { Play, Volume1, Share2 } from "react-feather"
import * as VideoPlayer from "react-player/vimeo";
import CardMedia from '@mui/material/CardMedia';

import useBreakpoints from '../Hooks/useBreakpoints';
import Controllers_WP_REST_Request from '../Controllers/WP_REST_Request';

import LoadingIndicator from './LoadingIndicator';
import ErrorDisplay from './ErrorDisplay';
import AudioPlayer from './AudioPlayer';
import ItemMeta from './ItemMeta';
import SearchInput from './SearchInput';
import RectangularButton from './RectangularButton';

const TESTING_ID = 123;

export default function PersistentPlayer( propItem ) {
  const { isDesktop } = useBreakpoints();
  const [item, setItem] = useState( propItem.item );
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState();
  // Video or audio
  const [mode, setMode] = useState();

  useEffect(() => {
    if (!item) return;

    document.body.classList.add('cpl-persistent-player');

    if (item.video) {
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

            {mode === "video" ? (
              <VideoPlayer
                className="itemDetail__video"
                // TODO: Replace with real item.video
                url="https://player.vimeo.com/video/621748162"
                controls={true}
                width="100%"
                height="100%"
                style={{ position: "absolute", top: 0, left: 0 }}
                playing={true}
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
		              <CardMedia component="audio" controls src={item.audio} sx={{width: '300px'}} autoPlay>
			              Your browser does not support the audio element.
		              </CardMedia>
	              </Box>
              </Box>
            )}

    </Box>
  );
}
