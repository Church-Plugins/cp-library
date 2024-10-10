import { AnalyticsProvider } from "./AnalyticsContext";
import { PersistentPlayerProvider } from "./PersistentPlayerContext";
import { ThemeProvider } from "@mui/material";
import theme from "../utils/theme";

export default function Providers({ children }) {
  return (
    <ThemeProvider theme={theme}>
      <AnalyticsProvider>
        <PersistentPlayerProvider>
          { children }
        </PersistentPlayerProvider>
      </AnalyticsProvider>
    </ThemeProvider>
  )
}