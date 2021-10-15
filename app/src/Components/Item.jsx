import React from 'react';
import Box from '@mui/material/Box';
import { PlayArrow, PlayCircleOutline } from "@mui/icons-material"
import ListItem from '@mui/material/ListItem';
import IconButton from '@mui/material/IconButton';
import { ChevronRight, Volume1 } from "react-feather"
import ReactDOM from 'react-dom';
import { useHistory } from "react-router-dom";

import useBreakpoints from '../Hooks/useBreakpoints';
import { usePersistentPlayer } from '../Contexts/PersistentPlayerContext';
import RectangularButton from './RectangularButton';
import ItemMeta from "./ItemMeta";
import Logo from "./Logo";

export default function Item({
  item,
  isNew,
}) {
  const { isDesktop } = useBreakpoints();

  const displayTitle = item.title.replace( "&#8217;", "'" );
  const displayBg    = item.thumb ? { background: "url(" + item.thumb + ")", backgroundSize: "cover" } : {backgroundColor: "#C4C4C4"};

  return (
    <ListItem
      className="item__root"
      sx={{
        padding: isDesktop ? 2 : 1,
        borderRadius: 2,
        background: !isDesktop && isNew ? 'linear-gradient(180deg, rgba(77, 108, 250, 0.5) 0%, rgba(196, 196, 196, 0) 109.68%)' : 'transparent',
        '&:hover': {
          background: 'linear-gradient(180deg, rgba(255, 255, 255, 0.3) 0%, rgba(196, 196, 196, 0) 109.68%)'
        }
      }}
    >
      <Box className="item__content" display="flex" flexDirection="row" width="100%">
        <Box className="item__thumb" flex={0} display="flex" alignItems="center">
          <Box
            sx={displayBg}
            borderRadius={1}
            width={isDesktop ? 184 : 57}
            height={isDesktop ? 111 : 47}
            display="flex"
            alignItems="center"
            justifyContent="center"
          >
            {item.thumb ? (
            	<PlayCircleOutline sx={{fontSize: 40}} />
            ) : (
              <Logo height="50%"/>
	            )}
          </Box>
        </Box>
        <Box
          className="item__details"
          flex={1}
          display="flex"
          flexDirection="column"
          marginLeft={2}
          justifyContent={isDesktop ? "space-between" : "center"}
        >
          <span className="item__title">{displayTitle}</span>
          <Box marginTop={1} className="item__itemMeta">
            <ItemMeta date={item.date.date} category={Object.values(item.category) || []} />
          </Box>
        </Box>
        {!isDesktop && isNew && (
          <Box
            className="item__new"
            flex={0}
            display="flex"
            alignItems="center"
            marginLeft={1}
            sx={{ textTransform: "uppercase" }}
          >
            New
          </Box>
        )}
        <Box className="item__actions" display="flex" alignItems="center" marginLeft={1}>
          <ItemActions isDesktop={isDesktop} item={item} />
        </Box>
      </Box>
    </ListItem>
  );
}

export function ItemActions({
  isDesktop = false,
  item,
}) {
  const { passToPersistentPlayer } = usePersistentPlayer();

	const playVideo = () => {
		passToPersistentPlayer({
			item,
			mode         : 'video',
			isPlaying    : true,
			playedSeconds: 0.0,
		});
	};

	const playAudio = () => {
		passToPersistentPlayer({
      item,
      mode: "audio",
      isPlaying: true,
      playedSeconds: 0.0,
    });
	};

  const history = useHistory();

  if (isDesktop) {
    return (
      <>
        {item.video.value && (
          <RectangularButton variant="contained" leftIcon={<PlayArrow />} onClick={playVideo}>
            Play Video
          </RectangularButton>
        )}
        {item.audio && (
          <Box marginLeft={item.video ? 2 : 0}>
            <RectangularButton variant="outlined" leftIcon={<Volume1 />} onClick={playAudio}>
              Play Audio
            </RectangularButton>
          </Box>
        )}
      </>
    );
  }

  return (
    <IconButton onClick={() => history.push(`/talks/${item.id}`)}>
      <ChevronRight/>
    </IconButton>
  );
}
