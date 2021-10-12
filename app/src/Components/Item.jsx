import React from 'react';
import Box from '@mui/material/Box';
import ListItem from '@mui/material/ListItem';
import IconButton from '@mui/material/IconButton';
import { ChevronRight, Play, Volume1 } from "react-feather"

import RectangularButton from './RectangularButton';
import ItemMeta from "./ItemMeta";
import useBreakpoints from '../Hooks/useBreakpoints';

export default function Item({
  item: {
    title,
    desc,
    thumb,
    date,
    video,
    audio,
    category = [],
  },
  isNew,
}) {
  const { isDesktop } = useBreakpoints();

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
          <Box sx={{ backgroundColor: "#C4C4C4" }} borderRadius={1} width={isDesktop ? 184 : 57} height={isDesktop ? 111 : 47}>
            {video && (
              <video width="100%" height="100%" poster={thumb || undefined}></video>
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
          <span className="item__title">{title}</span>
          <Box marginTop={1} className="item__itemMeta">
            <ItemMeta date={date} category={category} />
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
          <ItemActions isDesktop={isDesktop} video={video} audio={audio} />
        </Box>
      </Box>
    </ListItem>
  );
}

export function ItemActions({
  isDesktop = false,
  audio,
  video,
}) {
  if (isDesktop) {
    return (
      <>
        {video && (
          <RectangularButton variant="contained" leftIcon={<Play />}>
            Play Video
          </RectangularButton>
        )}
        {audio && (
          <Box marginLeft={video ? 2 : 0}>
            <RectangularButton variant="outlined" leftIcon={<Volume1 />}>
              Play Audio
            </RectangularButton>
          </Box>
        )}
      </>
    );
  }

  return (
    <IconButton onClick={() => console.log(`go to item`)}>
      <ChevronRight/>
    </IconButton>
  );
}