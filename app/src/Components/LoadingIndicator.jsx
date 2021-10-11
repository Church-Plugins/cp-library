import Box from '@mui/material/Box';
import CircularProgress from '@mui/material/CircularProgress';

export default function LoadingIndicator() {
  return (
    <Box className="loadingIndicator__root" width="100%" paddingY={4} textAlign="center">
      <CircularProgress />
    </Box>
  );
}
