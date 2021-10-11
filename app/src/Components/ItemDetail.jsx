import React, { useState, useEffect } from 'react';
import Box from '@mui/material/Box';
import Divider from '@mui/material/Divider';
import { Play, Volume1, Share2 } from "react-feather"
import * as VideoPlayer from "react-player/vimeo";

import useBreakpoints from '../Hooks/useBreakpoints';
import Controllers_WP_REST_Request from '../Controllers/WP_REST_Request';

import LoadingIndicator from './LoadingIndicator';
import ErrorDisplay from './ErrorDisplay';
import AudioPlayer from './AudioPlayer';
import ItemMeta from './ItemMeta';
import RectangularButton from './RectangularButton';

const TESTING_ID = 123;

export default function ItemDetail({
  // TODO: How to get the id? Can we pass it in to the React component?
  itemId = TESTING_ID,
}) {
  const { isDesktop } = useBreakpoints();
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
      itemIdToFetch = urlSearchParams.get("itemId");
    }

    (async () => {
      try {
        setLoading(true);
        const restRequest = new Controllers_WP_REST_Request();
        const data = await restRequest.get( {endpoint: `items/${itemIdToFetch}`} );
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
      <Box display="flex" flexDirection={isDesktop ? "row" : "column"}>
        <Box className="itemDetail__leftContent" flex={1} flexBasis="40%" marginRight={isDesktop ? 2 : 0}>
          <h1 className="itemDetail__title">{item.title}</h1>
          <h2 className="itemDetail__series">Series Name</h2>
          {isDesktop ? (
            <>
              <Box className="itemDetail__itemMeta" marginTop={3}>
                <ItemMeta date={item.date} category={item.category} />
              </Box>

              <Box className="itemDetail__description" marginTop={3}>
                <p>{item.desc}</p>
              </Box>
            </>
          ) : (
            <Divider className="itemDetail__divider" color="#4D6CFA" sx={{ width: 58, height: 6 }} />
          )}
        </Box>

        <Box className="itemDetail__rightContent" flex={1} flexBasis="60%">
          {/* TODO: Componentize as <FeatureImage />. These could be the same thing as the ones in the item list */}
          <Box
            className="itemDetail__featureImage"
            position="relative"
            paddingTop="56.26%"
            backgroundColor="#C4C4C4"
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
                <img
                  alt="Richard Ellis Talks logo"
                  src="http://churchplugin.local/wp-content/themes/rer/library/images/re-icon.svg"
                />
              </Box>
            )}
          </Box>

          {isDesktop ? null : (
            <Box className="itemDetail__category" marginTop={1}>
              <span>CATEGORIES: {item.category.join(", ")}</span>
            </Box>
          )}

          <Box className="itemDetail__actions" display="flex" alignItems="stretch" marginTop={2}>
            <Box className="itemDetail__playVideo" flex={1}>
              <RectangularButton
                leftIcon={<Play />}
                onClick={() => setMode("video")}
                disabled={!item.video || mode === "video"}
                fullWidth
              >
                Play Video
              </RectangularButton>
            </Box>
            <Box className="itemDetail__playAudio" flex={1} marginLeft={1}>
              <RectangularButton
                variant="outlined"
                leftIcon={<Volume1 />}
                onClick={() => setMode("audio")}
                disabled={!item.audio || mode === "audio"}
                fullWidth
              >
                Play Audio
              </RectangularButton>
            </Box>
            <Box
              className="itemDetail__share"
              flex={0}
              marginLeft={1}
              display="flex"
              alignItems="stretch"
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

      <AudioPlayer open={mode === "audio"} src={item.audio} />
    </Box>
  );
}
