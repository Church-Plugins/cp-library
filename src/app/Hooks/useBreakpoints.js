
import useMediaQuery from '@mui/material/useMediaQuery';

export default function useBreakpoints() {
  const isDesktop = useMediaQuery('(min-width:768px)');
  return {
    isDesktop
  };
}
