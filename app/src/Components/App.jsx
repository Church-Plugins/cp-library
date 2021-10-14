
import { MemoryRouter, Switch, Route } from "react-router-dom";

import Talks from "./Talks";
import ItemDetail from "./ItemDetail";
import { PersistentPlayerProvider } from "../Contexts/PersistentPlayerContext";

export default function App({
  itemId,
}) {
  const initialPath = itemId === undefined ? "/talks" : `/talks/${itemId}`;

  return (
    <PersistentPlayerProvider>
      <MemoryRouter initialEntries={[initialPath]}>
        <Switch>
          <Route
            path="/talks/:itemId"
            render={({ match, location, history}) => <ItemDetail itemId={match.params.itemId} />}
          />
          <Route path="/talks">
            <Talks />
          </Route>
        </Switch>
      </MemoryRouter>
    </PersistentPlayerProvider>
  );
};
