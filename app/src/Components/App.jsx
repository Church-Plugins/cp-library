
import { MemoryRouter, Switch, Route } from "react-router-dom";

import Items from "./Items";
import ItemDetail from "./ItemDetail";
import { PersistentPlayerProvider } from "../Contexts/PersistentPlayerContext";
import BottomNavigation from '@mui/material/BottomNavigation';
import BottomNavigationAction from '@mui/material/BottomNavigationAction';
import AddComment from '@mui/icons-material/AddComment';
import Link from '@mui/icons-material/Link';
import VolunteerActivismIcon from '@mui/icons-material/VolunteerActivism';
import useBreakpoints from '../Hooks/useBreakpoints';

export default function App({
  itemId,
}) {
  const initialPath = itemId === undefined ? "/talks" : `/talks/${itemId}`;
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
            path="/talks/:itemId"
            render={({ match, location, history}) => <ItemDetail itemId={match.params.itemId} />}
          />
          <Route path="/talks">
            <Items />
          </Route>
          <Route path="/series">
            <Items />
          </Route>
        </Switch>
      </MemoryRouter>
    </PersistentPlayerProvider>
  );
};
