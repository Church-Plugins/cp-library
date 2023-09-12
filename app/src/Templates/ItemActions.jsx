import React from 'react';
import { PersistentPlayerProvider } from '../Contexts/PersistentPlayerContext';
import { ThemeProvider } from '@mui/material/styles';
import theme from "../Templates/Theme";
import Actions from '../Components/Item/Actions';
import Providers from '../Contexts/Providers';

export default function ItemActions( { item } ) {
  return (
  	<ThemeProvider theme={theme}>
	    <Providers>
	      <Actions item={item} />
	    </Providers>
	  </ThemeProvider>
  );
};
