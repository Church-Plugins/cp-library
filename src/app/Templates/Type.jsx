import Box from '@mui/material/Box';
import ListItem from '@mui/material/ListItem';
import IconButton from '@mui/material/IconButton';
import { ChevronRight } from "react-feather"
import { useNavigate } from "react-router-dom";
import { cplVar } from '../utils/helpers';

import useBreakpoints from '../Hooks/useBreakpoints';
import TypeMeta from "./TypeMeta";
import Logo from "../Elements/Logo";

export default function Type({ item, isNew }) {
  const { isDesktop } = useBreakpoints();
  const displayTitle = item.title.replace( "&#8217;", "'" );
  const displayBg    = item.thumb ? { background: "url(" + item.thumb + ")", backgroundSize: "cover" } : {backgroundColor: "#C4C4C4"};
  const navigate     = useNavigate();

  return (
    <ListItem
      className="cpl-item--root"
      sx={{
        padding: isDesktop ? 2 : 1,
        borderRadius: 2,
        background: !isDesktop && isNew ? 'linear-gradient(180deg, rgba(77, 108, 250, 0.5) 0%, rgba(196, 196, 196, 0) 109.68%)' : 'transparent',
        '&:hover': {
          background: 'linear-gradient(180deg, rgba(255, 255, 255, 0.3) 0%, rgba(196, 196, 196, 0) 109.68%)'
        }
      }}
      onClick={() => navigate(`/${cplVar( 'slug', 'item_type' )}/${item.originID}`)}
    >
      <Box className="cpl-item--content" display="flex" flexDirection="row" width="100%">
        <Box className="cpl-item--thumb" flex={0} display="flex" alignItems="center">
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
            	<></>
            ) : (
              <Logo height="50%"/>
	            )}
          </Box>
        </Box>
        <Box
          className="cpl-item--details"
          flex={1}
          display="flex"
          flexDirection="column"
          marginLeft={2}
          justifyContent={isDesktop ? "space-between" : "center"}
        >
          <h3 className="cpl-item--title">{displayTitle}</h3>
          <Box marginTop={1} className="cpl-item--item-meta">
            <TypeMeta date={item.date.desc} items={item.items} />
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
      </Box>
    </ListItem>
  );
}

export function ItemActions({ item }) {
  const navigate = useNavigate();

  return (
    <IconButton className="cplItem__toItem" onClick={() => navigate(`/talks/${item.originID}`)}>
      <ChevronRight/>
    </IconButton>
  );
}
