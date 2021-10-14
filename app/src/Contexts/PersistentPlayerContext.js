import { createContext, useReducer, useContext, useEffect, useCallback } from "react";
import ReactDOM from 'react-dom';

import PersistentPlayer from "../Components/PersistentPlayer";

const PersistentPlayerContext = createContext()

const defaultState = {
  isActive: undefined,
  item: undefined,
  mode: undefined,
  isPlaying: false,
}

function reducer(state, action) {
  switch (action.type) {
    case "PLAYER_MOUNTED": {
      return { ...state, item: action.item, isActive: Boolean(action.item) };
    }
    case "PLAYER_UNMOUNTED": {
      return { ...state, item: undefined, isActive: false };
    }
    case "PLAYER_CLOSED": {
      return { ...state, item: undefined, isActive: false };
    }
    case "ITEM_PERSISTED": {
      return { ...state, item: action.item, isActive: true };
    }
    default: {
      throw new Error(`Unhandled action type: ${action.type}`)
    }
  }
}

function PersistentPlayerProvider({children}) {
  const [state, dispatch] = useReducer(reducer, defaultState);

  useEffect(() => {
    function handleMessage(event) {
      if (event.data.action === "CPL_PERSISTENT_PLAYER_MOUNTED") {
        dispatch({ type: "PLAYER_MOUNTED", item: event.data.item });
      } else if (event.data.action === "CPL_PERSISTENT_PLAYER_MOUNTED") {
        dispatch({ type: "PLAYER_UNMOUNTED" });
      } else if (event.data.action === "CPL_PERSISTENT_PLAYER_CLOSED") {
        dispatch({ type: "PLAYER_CLOSED" })
      } else if (event.data.action === "CPL_PERSISTENT_RECEIVED_ITEM") {
        dispatch({ type: "ITEM_PERSISTED", item: event.data.item })
      } 
    }

    window.top.addEventListener("message", handleMessage);

    return () => {
      window.top.removeEventListener("message", handleMessage);
    }
  }, [])

  const passToPersistentPlayer = useCallback(({ item, mode, isPlaying, playedSeconds }) => {
    if (state.isActive !== true) {
      const player = window.top.document.getElementById('cpl_persistent_player');
      ReactDOM.render(<PersistentPlayer />, player);
    }
    
    window.top.postMessage({
      action: "CPL_HANDOVER_TO_PERSISTENT",
      item,
      mode,
      isPlaying,
      playedSeconds,
    });
  }, [])

  // NOTE: you *might* need to memoize this value
  // Learn more in http://kcd.im/optimize-context
  const value = {
    ...state,
    passToPersistentPlayer,
  }

  return <PersistentPlayerContext.Provider value={value}>{children}</PersistentPlayerContext.Provider>
}

function usePersistentPlayer() {
  const context = useContext(PersistentPlayerContext)
  if (context === undefined) {
    throw new Error('usePersistentPlayer must be used within a PersistentPlayerComm.Provider')
  }
  return context
}

export {PersistentPlayerProvider, usePersistentPlayer}
