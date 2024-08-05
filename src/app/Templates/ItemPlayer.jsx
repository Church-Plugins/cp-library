import React from 'react';
import { PersistentPlayerProvider } from '../Contexts/PersistentPlayerContext';
import { ThemeProvider } from '@mui/material/styles';
import theme from "../Templates/Theme";
import Player from '../Components/Item/Player';
import Providers from '../Contexts/Providers';

export default function ItemPlayer( { item } ) {
  return (
  	<ThemeProvider theme={theme}>
	    <Providers>
	      <Player item={item} />
	    </Providers>
	  </ThemeProvider>
  );
};
