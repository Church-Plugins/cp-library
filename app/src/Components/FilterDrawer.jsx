import React from "react";
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import Drawer from '@mui/material/Drawer';
import IconButton from '@mui/material/IconButton';
import Portal from '@mui/material/Portal';
import { XCircle } from 'react-feather';
import { noop } from "../utils/noop";

import FormGroup from '@mui/material/FormGroup';
import FormControlLabel from '@mui/material/FormControlLabel';
import Checkbox from '@mui/material/Checkbox';

export default function FilterDrawer({
  open = false,
  onClose = noop,
  onFilterChange = noop
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
		</Box>
		{/* TODO: Put filter criteria */}
		<Box flex={1} className="filterDrawer__format">
			<Box className="format__title">
				<Typography>FORMAT</Typography>
			</Box>
			<Box className="format__items">
			<FormGroup>
				<FormControlLabel
					control={
						<Checkbox
							name = "format__audio"
							/>
					}
					label="Audio" />
				<FormControlLabel
					control={
						<Checkbox
							name = "format__video"
							/>
					}
					label="Video" />
			</FormGroup>
				{/* <FilterAccordionFormat onFilterChange={onFilterChange} /> */}
			</Box>
		</Box>
		<Box flex={1} className="filterDrawer__topic">
			<Box className="format__title">
				<Typography>POPULAR TOPICS</Typography>
			</Box>
			<Box className="format__items">
				<FormGroup>
					<FormControlLabel control={<Checkbox value="worry" />} label="Worry" />
					<FormControlLabel control={<Checkbox value="doubt" />} label="Doubt" />
					<FormControlLabel control={<Checkbox value="fear" />} label="Fear" />
					<FormControlLabel control={<Checkbox value="anxiety" />} label="Anxiety" />
					<FormControlLabel control={<Checkbox value="depression" />} label="Depression" />
					<FormControlLabel control={<Checkbox value="encouragement" />} label="Encouragement" />
				</FormGroup>
				{/* <FilterAccordionPopular onFilterChange={onFilterChange} /> */}
			</Box>
		</Box>
      </Drawer>
    </Portal>
  );
}
