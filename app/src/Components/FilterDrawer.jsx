import React from "react";
import Box from '@mui/material/Box';
import Drawer from '@mui/material/Drawer';
import IconButton from '@mui/material/IconButton';
import Portal from '@mui/material/Portal';
import { XCircle } from 'react-feather';
import { noop } from "../utils/noop";

export default function FilterDrawer({
  open = false,
  onClose = noop,
}) {
  return (
    <Portal>
      <Drawer
        className="filterDrawer"
        anchor="right"
        open={open}
        onClose={onClose}
        // So it shows on top of header/nav
        sx={{ zIndex: 6000 }}
        PaperProps={{ sx: { width: "100%" } }}
      >
        <Box display="flex">
          <Box flex={1} className="filterDrawer__title"><h1>Filter</h1></Box>
          <Box flex={0} className="filterDrawer__close" display="flex" alignItems="center">
            <IconButton onClick={onClose}><XCircle /></IconButton>
          </Box>
          {/* TODO: Put filter criteria */}
        </Box>
      </Drawer>
    </Portal>
  );
}
