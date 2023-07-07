import { AnalyticsProvider } from "./AnalyticsContext";
import { PersistentPlayerProvider } from "./PersistentPlayerContext";

export default function Providers({ children }) {
  return (
    <AnalyticsProvider>
      <PersistentPlayerProvider>
        { children }
      </PersistentPlayerProvider>
    </AnalyticsProvider>
  )
}