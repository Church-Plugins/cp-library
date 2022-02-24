
import { MemoryRouter, Switch, Route } from "react-router-dom";
import React, { useState, useEffect } from 'react';

import Items from "./Items";
import Types from "./Types";
import ItemDetail from "./ItemDetail";
import TypeDetail from "./TypeDetail";
import { PersistentPlayerProvider } from "../Contexts/PersistentPlayerContext";
import BottomNavigation from '@mui/material/BottomNavigation';
import BottomNavigationAction from '@mui/material/BottomNavigationAction';
import AddComment from '@mui/icons-material/AddComment';
import Link from '@mui/icons-material/Link';
import VolunteerActivismIcon from '@mui/icons-material/VolunteerActivism';
import useBreakpoints from '../Hooks/useBreakpoints';
import { cplVar } from '../utils/helpers';

export default function App({
  itemId, typeId
}) {
  const initialPath = itemId === undefined ? "/" + cplVar( 'slug', 'item' ) : `/${cplVar( 'slug', 'item' )}/${itemId}`;
  const { isDesktop } = useBreakpoints();

  const navClick = (path, newWindow = false) => {
    const link = document.createElement('a');
    link.href = path;

    if ( newWindow ) {
    	link.setAttribute( 'target', '_blank' );
    }

    // Append to html link element page
    document.body.appendChild(link);

    // Start download
    link.click();

    // Clean up and remove the link
    link.parentNode.removeChild(link);
  };

  return (
    <PersistentPlayerProvider>
      <MemoryRouter initialEntries={[initialPath]}>

	      {!isDesktop && (
		      <BottomNavigation
			      className="cplItems__topNav"
			      showLabels
		      >
			      <BottomNavigationAction label="Subscribe" icon={<AddComment/>} onClick={() => navClick(
				      'https://itunes.apple.com/us/podcast/richard-ellis-talks/id626398296', true)}/>
			      <BottomNavigationAction label="Contribute" icon={<VolunteerActivismIcon/>}
			                              onClick={() => navClick('/contribute/' )}/>
			      <BottomNavigationAction label="Connect" icon={<Link/>} onClick={() => navClick('/connect/' )}/>
		      </BottomNavigation>
	      )}

        <Switch>
          <Route
            path={"/" + cplVar( 'slug', 'item' ) + "/:itemId"}
            render={({ match, location, history}) => <ItemDetail itemId={match.params.itemId} />}
          />
          <Route
            path={"/" + cplVar( 'slug', 'item_type' ) + "/:typeId"}
            render={({ match, location, history}) => <TypeDetail typeId={match.params.typeId} />}
          />
          <Route path={"/" + cplVar( 'slug', 'item' )}>
            <Items />
          </Route>
          <Route path={"/" + cplVar( 'slug', 'item_type' )}>
            <Types />
          </Route>
        </Switch>
      </MemoryRouter>
    </PersistentPlayerProvider>
  );
};
