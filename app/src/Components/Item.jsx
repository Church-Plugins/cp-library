import React from 'react';
import Box from '@mui/material/Box';
import ListItem from '@mui/material/ListItem';
import IconButton from '@mui/material/IconButton';
import Button from '@mui/material/Button';
import { ChevronRight, Calendar, Tag, Play, Volume1 } from "react-feather"
import relativeDate from "tiny-relative-date";
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
        background: isNew ? 'linear-gradient(180deg, rgba(77, 108, 250, 0.5) 0%, rgba(196, 196, 196, 0) 109.68%)' : 'transparent',
        '&:hover': {
          background: 'linear-gradient(180deg, rgba(255, 255, 255, 0.3) 0%, rgba(196, 196, 196, 0) 109.68%)'
        }
      }}
    >
      <Box className="item__content" display="flex" flexDirection="row" width="100%">
        <Box className="item__thumb" flex={0} display="flex" alignItems="center">
          <Box sx={{ backgroundColor: "#C4C4C4" }} borderRadius={1} width={isDesktop ? 184 : 57} height={isDesktop ? 111 : 47}>
            {video && (
              <video width="100%" height="100%" poster={thumb}></video>
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
          <Box marginTop={1}>
            <Box className="item__relativeReleaseDate" display="inline-block">
              <Calendar />
              <Box component="span" marginLeft={1}>{relativeDate(date)}</Box>
            </Box>
            {category && category.length > 0 && (
              <Box className="item__categories" display="inline-block">
                <Tag />
                <Box component="span" marginLeft={1}>{category.join(",")}</Box>
              </Box>
            )}
          </Box>
        </Box>
        {!isDesktop && isNew && (
          <Box className="item__new" flex={0} display="flex" alignItems="center" marginLeft={1}>
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
          <Button variant="contained" startIcon={<Play />}>
            Play Video
          </Button>
        )}
        {audio && (
          <Box marginLeft={video ? 2 : 0}>
            <Button variant="outlined" startIcon={<Volume1 />}>
              Play Audio
            </Button>
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