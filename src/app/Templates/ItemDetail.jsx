import React, { useState, useEffect, useRef } from 'react';
import Box from '@mui/material/Box';
import { cplVar } from '../utils/helpers';
import debounce from '@mui/utils/debounce';

import { Link, useLocation, useNavigate, useParams } from 'react-router-dom';

import Controllers_WP_REST_Request from '../Controllers/WP_REST_Request';
import LoadingIndicator from '../Elements/LoadingIndicator';
import ErrorDisplay from '../Elements/ErrorDisplay';
import ItemMeta from './ItemMeta';
import SearchInput from '../Elements/SearchInput';

import Player from '../Components/Item/Player';

export default function ItemDetail() {
	const { itemId }   = useParams();

  const [item, setItem] = useState();
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState();
	const itemContainer = useRef(false);
  const navigate     = useNavigate();
	const location     = useLocation();

	const handleSearchInputChange = debounce((value) => {
		if ( ! value ) {
			return;
		}

		if ( 4 > value.length ) {
			return;
		}

		navigate(`${cplVar( 'path', 'site' )}/${cplVar( 'slug', 'item' )}?s=${value}`);
	}, 1000);

  // Fetch the individual item when mounted.
  useEffect(() => {

		// allow for an item passed by state
		if ( location.state?.item ) {
			setItem(location.state.item);
			setLoading(false);
			itemContainer.current.scrollIntoView({behavior: 'smooth'});
		} else {
			(
				async () => {
					try {
						setLoading(true);
						const restRequest = new Controllers_WP_REST_Request();
						const data = await restRequest.get({endpoint: `items/${itemId}`});
						setItem(data);
					} catch (error) {
						setError(error);
					} finally {
						setLoading(false);
					}
				}
			)();
		}

  }, []);

  return loading ? (
  	<Box ref={itemContainer} className={"itemDetail__root"}>
      <LoadingIndicator />
	  </Box>
  ) : error ? (
    <ErrorDisplay error={error} />
  ) : (
    // Margin bottom is to account for audio player. Making sure all content is still visible with
    // the player is up.
    <Box ref={itemContainer} className="cpl-single">
      <Link className="back-link cpl-single--back" to={cplVar( 'path', 'site' ) + "/" + cplVar( 'slug', 'item' )}>Back to {cplVar('labelPlural', 'item')}</Link>

      <Box className="cpl-single--header cpl-columns cpl-touch-hide">
        <h1 className="cpl-single--header--title">{cplVar('labelPlural', 'item')}</h1>

        <Box className="cpl-filter--search" marginLeft={1} display="flex" alignItems="center">
          <Box className="cpl-filter--search--box">
            <SearchInput onValueChange={handleSearchInputChange} />
          </Box>
        </Box>
      </Box>

      <Box className="cpl-single-item">

        <Box className="cpl-single-item--title">
          <h1 dangerouslySetInnerHTML={{ __html: item.title }} />
        </Box>

	      <Box className="cpl-columns">
	        <Box className="cpl-single-item--content">
	          <ItemMeta date={item.date.desc} category={item.category || []} />

	          <Box className="cpl-single-item--desc" dangerouslySetInnerHTML={{ __html: item.desc }} />
	        </Box>

		      <Box className="cpl-single-item--media">
			      <Player item={item} />
		      </Box>
	      </Box>

      </Box>

    </Box>
  );
}
