import { createContext, useContext, useState } from "react"

const AnalyticsContext = createContext()

export function useAnalytics() {
  const analytics = useContext(AnalyticsContext)

  if(!analytics) {
    throw new Error("useAnalytics must be used inside an AnalyticsProvider context")
  }

  return analytics
}


export function AnalyticsProvider({ children }) {
  const [mode, setMode] = useState()
  const [item, setItem] = useState()

  const value = {
    mode,
    setMode,
    item,
    setItem
  }

  return (
    <AnalyticsContext.Provider value={value}>
      { children }
    </AnalyticsContext.Provider>
  )
}

