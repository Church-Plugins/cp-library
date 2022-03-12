import { createTheme } from '@mui/material/styles';

const root = window.document.documentElement;
const primaryColor = root.style.getPropertyValue('--cpl-primary');
const theme = createTheme({
  palette: {
    primary: {
      main: "#333333",
      dark: "#333333"
    }
  },
});

export default theme;
