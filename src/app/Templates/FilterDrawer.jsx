import React, { useState } from 'react';
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import Drawer from '@mui/material/Drawer';
import IconButton from '@mui/material/IconButton';
import Portal from '@mui/material/Portal';
import { XCircle } from 'react-feather';
import { noop } from "../utils/noop";

import ArrowForwardIcon from '@mui/icons-material/ArrowForward';

import FilterDrawerTopic from './FilterDrawerTopic';
import FilterBoxFormat from './FilterBoxFormat';
import FilterBoxPopular from './FilterBoxPopular';

export default function FilterDrawer({
  open = false,
  onClose = noop,
  onFilterChange = noop,
  FormatFilter = noop,
  TopicFilter = noop,
  activeFilters = noop
}) {

  const [topicDrawerIsOpen, setTopicDrawerIsOpen] = useState( false );

  return (
    <Portal>
      <Drawer
        className="filterDrawer__popular"
        anchor="right"
        open={open}
        onClose={onClose}
        // So it shows on top of header/nav
        sx={{ zIndex: 6000 }}
        PaperProps={{ sx: { width: "100%" } }}
      >
        <Box display="flex" className="filterDrawer__header">
          <Box flex={1} className="filterDrawer__title">FILTER</Box>
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
				<FormatFilter />
			</Box>
		</Box>
		<Box flex={1} className="filterDrawer__topic">
			<Box className="format__title">
				<Typography>POPULAR TOPICS</Typography>
			</Box>
			<Box className="format__items">
				<TopicFilter />
			</Box>
			<Box className="format__more">
				<IconButton
					aria-label="View All"
					onClick={() => setTopicDrawerIsOpen(!topicDrawerIsOpen)} >

						<Typography className="more__label">VIEW ALL</Typography>
						<ArrowForwardIcon />
				</IconButton>
			</Box>
		</Box>
		<FilterDrawerTopic
				open={topicDrawerIsOpen}
				onClose={() => setTopicDrawerIsOpen(false)}
				onFilterChange={onFilterChange}
				activeFilters={activeFilters}
				whichView="mobile"
			/>
      </Drawer>
    </Portal>
  );
}
