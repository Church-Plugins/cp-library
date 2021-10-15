import React, { useState, useEffect, useRef } from 'react';
import Box from '@mui/material/Box';
import Divider from '@mui/material/Divider';
import { Play, Volume1, Share2 } from "react-feather"
import * as VideoPlayer from "react-player/vimeo";
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

export default function ItemDetail({
  itemId,
}) {
  const { isDesktop } = useBreakpoints();
  const [item, setItem] = useState();
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState();
  // Video or audio
  const [mode, setMode] = useState();
  // Keep frequently-updated states (mainly the progress from the media player) as a ref so they
  // don't trigger re-render.
  const mediaState = useRef({});
  const { isActive: persistentPlayerIsActive, passToPersistentPlayer } = usePersistentPlayer();

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
    // Margin bottom is to account for audio player. Making sure all content is still visible with
    // the player is up.
    <Box className="itemDetail__root" padding={2} marginBottom={mode === "audio" ? 10 : 0}>
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
          <h2 className="itemDetail__series">Series Name</h2>
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
            {mode === "video" ? (
              <VideoPlayer
                className="itemDetail__video"
                // TODO: Replace with real item.video
                url="https://player.vimeo.com/video/621748162"
                controls={true}
                width="100%"
                height="100%"
                style={{ position: "absolute", top: 0, left: 0 }}
              />
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
                <Logo />
              </Box>
            )}
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
                onClick={() => setMode("video")}
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
                    setMode("audio");
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
        </Box>
      </Box>

      {isDesktop ? null : (
        <Box className="itemDetail__description" marginTop={2}>
          <p>{item.desc}</p>
        </Box>
      )}

      {!persistentPlayerIsActive && (
        <AudioPlayer
          open={mode === "audio"}
          src={item.audio}
          onStart={() => {
            mediaState.current = { ...mediaState.current, isPlaying: true };
          }}
          onPlay={() => {
            mediaState.current = { ...mediaState.current, isPlaying: true };
          }}
          onPause={() => {
            mediaState.current = { ...mediaState.current, isPlaying: false };
          }}
          onEnded={() => {
            mediaState.current = { ...mediaState.current, isPlaying: false, isFinished: true };
          }}
          onProgress={progress => {
            mediaState.current = { ...mediaState.current, playedSeconds: progress.playedSeconds };
          }}
        />
      )}
    </Box>
  );
}
