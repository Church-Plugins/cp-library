
import { Route, BrowserRouter, Routes } from "react-router-dom";

import Items from "./Items";
import Types from "./Types";
import ItemDetail from "./ItemDetail";
import TypeDetail from "./TypeDetail";
import { cplVar } from '../utils/helpers';
import Providers from "../Contexts/Providers";

const App = () => {
  return (
    <Providers>
      <BrowserRouter basename="/">

	      <div className="cpl-touch-only" dangerouslySetInnerHTML={{__html: cplVar( 'mobileTop', 'components' ) }} />

        <Routes>
          <Route
            path="/talks/:itemId"
            element={<ItemDetail />}
         />
          <Route
            path={cplVar( 'path', 'site' ) + "/" + cplVar( 'slug', 'item_type' ) + "/:typeId"}
            render={({ match, location, history}) => <TypeDetail typeId={match.params.typeId} />}
          />
          <Route
            path={cplVar( 'path', 'site' ) + "/" + cplVar( 'slug', 'item' )}
            element={<Items />} 
          />
          <Route
            path={cplVar( 'path', 'site' ) + "/" + cplVar( 'slug', 'item_type' )}
            element={<Types />}
          />
        </Routes>
      </BrowserRouter>
    </Providers>
  );
};

export default App;
