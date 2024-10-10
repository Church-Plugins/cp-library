import { createContext, useReducer, useContext, useEffect, useRef } from "react";

const defaultState = {
  isActive: undefined,
  item: undefined,
  mode: undefined,
  isPlaying: false,
}

const PersistentPlayerContext = createContext(defaultState)

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

export function PersistentPlayerProvider({children}) {
  const [state, dispatch] = useReducer(reducer, defaultState);
  const root = useRef(null);

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
  
  
  // NOTE: you *might* need to memoize this value
  // Learn more in http://kcd.im/optimize-context
  const value = {
    ...state
  }

  return (
    <PersistentPlayerContext.Provider value={value}>
      {children}
    </PersistentPlayerContext.Provider>
  )
}

export function usePersistentPlayer() {
  const context = useContext(PersistentPlayerContext)
  if (context === undefined) {
    throw new Error('usePersistentPlayer must be used within a PersistentPlayerContext.Provider')
  }
  return context
}
