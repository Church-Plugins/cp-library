import React, { useState, useEffect } from "react";
import CircularProgress from '@mui/material/CircularProgress';
import Box from '@mui/material/Box';
import List from '@mui/material/List';

import Item from "./Item";
import Controllers_WP_REST_Request from '../Controllers/WP_REST_Request';

function ItemList({}) {
  const [items, setItems] = useState([]);
  const [loading, setLoading] = useState(false);
  const [error, setError] = useState();

  useEffect(() => {
    (async () => {
      try {
        setLoading(true);
        const restRequest = new Controllers_WP_REST_Request();
        const data = await restRequest.get( {endpoint: 'items'} );
        setItems(data);
      } catch (error) {
        setError(error);
      } finally {
        setLoading(false);
      }
    })();
  }, []);

  return loading ? (
    <Box textAlign="center" paddingY={4}>
      <CircularProgress />
    </Box>
  ) : error ? (
    // Probably want to handle this more gracefully.
    <Box><pre>{JSON.stringify(error, null, 2)}</pre></Box>
  ) : items.length === 0 ? (
    <Box>No items</Box>
  ) : (
    <List className="itemList__root">
      {items.map((item, index) => (
        <Item key={item.title} item={item} isNew={index === 0}/>
      ))}
    </List>
  )
}

export default ItemList;
