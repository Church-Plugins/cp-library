import React from 'react';
import { PersistentPlayerProvider } from '../Contexts/PersistentPlayerContext';
import { ThemeProvider } from '@mui/material/styles';
import theme from "../Templates/Theme";
import Actions from '../Components/Item/Actions';

export default function ItemActions( { item } ) {
  return (
  	<ThemeProvider theme={theme}>
	    <PersistentPlayerProvider>
	      <Actions item={item} />
	    </PersistentPlayerProvider>
	  </ThemeProvider>
  );
};
