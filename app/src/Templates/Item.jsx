import React from 'react';
import Box from '@mui/material/Box';
import { useHistory } from "react-router-dom";
import { cplVar } from '../utils/helpers';

import ItemMeta from "./ItemMeta";
import Logo from "../Elements/Logo";

import Actions from "../Components/Item/Actions";

export default function Item({
  item,
  isNew,
	setActiveFilters
}) {
  const displayTitle = item.title.replace( "&#8217;", "'" );
  const displayBg    = item.thumb ? { background: "url(" + item.thumb + ")", backgroundSize: "cover" } : {backgroundColor: "#C4C4C4"};
  const history      = useHistory();
	const getClass = () => {
		let itemClass = 'cpl-list-item';

		if ( isNew ) {
			itemClass += ' is-new';
		}

		if ( item.video.value ) {
			itemClass += ' has-video';
		}

		if ( item.audio ) {
			itemClass += ' has-audio';
		}

		return itemClass;
	}
	const itemClass = getClass();

  return (
    <Box
      className={itemClass}
      onClick={() => history.push(`${cplVar( 'path', 'site' )}/${cplVar( 'slug', 'item' )}/${item.slug}`)}
    >

      <Box className="cpl-list-item--thumb">
        <Box sx={displayBg} className="cpl-list-item--thumb--canvas">
          {item.thumb ? (
		          <img
			          alt={item.title + ' thumbnail'}
			          src={item.thumb}
		          />
          ) : (
            <Logo height="50%"/>
            )}
        </Box>
      </Box>

      <Box className="cpl-list-item--details">
        <h3 className="cpl-list-item--title">{displayTitle}</h3>
        <Box className="cpl-meta">
          <ItemMeta date={item.date.desc} category={item.category || []} setActiveFilters={setActiveFilters} />
        </Box>
      </Box>

      <Actions item={item} />

    </Box>
  );
}
