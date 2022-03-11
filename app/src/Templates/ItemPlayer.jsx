import React from 'react';
import { PersistentPlayerProvider } from '../Contexts/PersistentPlayerContext';
import { ThemeProvider } from '@mui/material/styles';
import theme from "../Templates/Theme";
import Player from '../Components/Item/Player';

export default function ItemPlayer( { item } ) {
  return (
  	<ThemeProvider theme={theme}>
	    <PersistentPlayerProvider>
	      <Player item={item} />
	    </PersistentPlayerProvider>
	  </ThemeProvider>
  );
};
