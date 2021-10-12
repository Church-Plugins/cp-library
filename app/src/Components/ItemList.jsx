import React, { useState, useEffect } from "react";
import Box from '@mui/material/Box';
import List from '@mui/material/List';

import Item from "./Item";
import LoadingIndicator from "./LoadingIndicator";
import ErrorDisplay from "./ErrorDisplay";
import Controllers_WP_REST_Request from '../Controllers/WP_REST_Request';

function ItemList({
  activeFilters,
}) {
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState();

  useEffect(() => {
    (async () => {
      try {
        setLoading(true);
        const restRequest = new Controllers_WP_REST_Request();
		let inputParams 	=	't=' +
								activeFilters.topics.join(",") + '&' +
								'f=' +
								activeFilters.formats.join(",");
        const data = await restRequest.get( {endpoint: 'items', params: inputParams} );;
        setItems( data.items );
      } catch (error) {
        setError(error);
      } finally {
        setLoading(false);
      }
    })();
  }, [activeFilters]);

  return loading ? (
    <LoadingIndicator />
  ) : error ? (
    <ErrorDisplay error={error} />
  ) : items.length === 0 ? (
    <Box>No items</Box>
  ) : (
    <List className="itemList__root">
      {items.map((item, index) => (
        // "isNew" assumes the items are sorted by creation date descendingly.
        <Item key={item.title} item={item} isNew={index === 0}/>
      ))}
    </List>
  )
}

export default ItemList;
