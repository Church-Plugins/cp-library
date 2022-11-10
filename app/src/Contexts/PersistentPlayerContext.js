import { createContext, useReducer, useContext, useEffect, useCallback } from "react";
import ReactDOM from 'react-dom';
import { ThemeProvider } from '@mui/material/styles';
import { cplLog } from '../utils/helpers';

import PersistentPlayer from "../Templates/PersistentPlayer";
import theme from "../Templates/Theme";

const PersistentPlayerContext = createContext()

const defaultState = {
  isActive: undefined,
  item: undefined,
  mode: undefined,
  isPlaying: false,
}

function reducer(state, action) {
  switch (action.type) {
    case "PLAYER_ALREADY_ACTIVE": {
      return { ...state, isActive: true };
    }
    case "PLAYER_MOUNTED": {
      return { ...state, item: action.item, isActive: true };
    }
    case "PLAYER_UNMOUNTED": {
      return { ...state, item: undefined, isActive: false };
    }
    case "PLAYER_CLOSED": {
      return { ...state, item: undefined, isActive: false };
    }
    case "ITEM_PERSISTED": {
      return { ...state, item: action.item, isActive: true, mode: action.mode };
    }
    default: {
      throw new Error(`Unhandled action type: ${action.type}`)
    }
  }
}

function PersistentPlayerProvider({children}) {
  const [state, dispatch] = useReducer(reducer, defaultState);

  useEffect(() => {
    const alreadyActive = window.top.document.body.classList.contains("cpl-persistent-player");

    if (alreadyActive) {
      dispatch(({ type: "PLAYER_ALREADY_ACTIVE" }))
    }
  }, [])

  useEffect(() => {
    function handleMessage(event) {
      if (event.data.action === "CPL_PERSISTENT_PLAYER_MOUNTED") {
        dispatch({ type: "PLAYER_MOUNTED", item: event.data.item });
      } else if (event.data.action === "CPL_PERSISTENT_PLAYER_MOUNTED") {
        dispatch({ type: "PLAYER_UNMOUNTED" });
      } else if (event.data.action === "CPL_PERSISTENT_PLAYER_CLOSED") {
        dispatch({ type: "PLAYER_CLOSED" })
      } else if (event.data.action === "CPL_PERSISTENT_RECEIVED_ITEM") {
        dispatch({ type: "ITEM_PERSISTED", item: event.data.item, mode: event.data.mode })
      }
    }

    window.top.addEventListener("message", handleMessage);

    return () => {
      window.top.removeEventListener("message", handleMessage);
    }
  }, [])

  const passToPersistentPlayer = useCallback(({ item, mode, isPlaying, playedSeconds }) => {
    if (!state.isActive) {
      const player = window.top.document.getElementById('cpl_persistent_player');
      ReactDOM.render(<PersistentPlayer />, player);
    }

 	  setTimeout(() => {
		  window.top.postMessage({
			  action: 'CPL_HANDOVER_TO_PERSISTENT',
			  item,
			  mode,
			  isPlaying,
			  playedSeconds,
		  });
	  }, 50);

		cplLog( item.id, 'persistent' );

		// also log a play action if we are not currently playing
		if ( ! playedSeconds > 0 ) {
			cplLog( item.id, 'play' );
		}

  }, [state.isActive])

  // NOTE: you *might* need to memoize this value
  // Learn more in http://kcd.im/optimize-context
  const value = {
    ...state,
    passToPersistentPlayer,
  }

  return <PersistentPlayerContext.Provider value={value}><ThemeProvider theme={theme}>{children}</ThemeProvider></PersistentPlayerContext.Provider>
}

function usePersistentPlayer() {
  const context = useContext(PersistentPlayerContext)
  if (context === undefined) {
    throw new Error('usePersistentPlayer must be used within a PersistentPlayerComm.Provider')
  }
  return context
}

export {PersistentPlayerProvider, usePersistentPlayer}
