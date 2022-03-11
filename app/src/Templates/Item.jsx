import React from 'react';
import Box from '@mui/material/Box';
import { PlayCircleOutline } from "@mui/icons-material"
import ListItem from '@mui/material/ListItem';
import { useHistory } from "react-router-dom";
import { cplVar } from '../utils/helpers';

import useBreakpoints from '../Hooks/useBreakpoints';
import ItemMeta from "./ItemMeta";
import Logo from "../Elements/Logo";

import Actions from "../Components/Item/Actions";

export default function Item({
  item,
  isNew,
}) {
  const { isDesktop } = useBreakpoints();

  const displayTitle = item.title.replace( "&#8217;", "'" );
  const displayBg    = item.thumb ? { background: "url(" + item.thumb + ")", backgroundSize: "cover" } : {backgroundColor: "#C4C4C4"};
  const history      = useHistory();

  return (
    <ListItem
      className="cplItem__root"
      sx={{
        padding: isDesktop ? 2 : 1,
        borderRadius: 2,
        background: !isDesktop && isNew ? 'linear-gradient(180deg, rgba(77, 108, 250, 0.5) 0%, rgba(196, 196, 196, 0) 109.68%)' : 'transparent',
        '&:hover': {
          background: 'linear-gradient(180deg, rgba(255, 255, 255, 0.3) 0%, rgba(196, 196, 196, 0) 109.68%)'
        }
      }}
      onClick={() => history.push(`${cplVar( 'path', 'site' )}/${cplVar( 'slug', 'item' )}/${item.slug}`)}
    >
      <Box className="cplItem__content" display="flex" flexDirection="row" width="100%">
        <Box className="cplItem__thumb" flex={0} display="flex" alignItems="center">
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
          className="cplItem__details"
          flex={1}
          display="flex"
          flexDirection="column"
          marginLeft={2}
          justifyContent={isDesktop ? "space-between" : "center"}
        >
          <h3 className="cplItem__title">{displayTitle}</h3>
          <Box marginTop={1} className="cplItem__itemMeta">
            <ItemMeta date={item.date.date} category={Object.values(item.category) || []} />
          </Box>
        </Box>
        {!isDesktop && isNew && (
          <Box
            className="cplItem__new"
            flex={0}
            display="flex"
            alignItems="center"
            marginLeft={1}
            sx={{ textTransform: "uppercase" }}
          >
            New
          </Box>
        )}

	      <Actions item={item} />
      </Box>
    </ListItem>
  );
}
