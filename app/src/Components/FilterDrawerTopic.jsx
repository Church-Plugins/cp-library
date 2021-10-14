import React from "react";
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import Drawer from '@mui/material/Drawer';
import IconButton from '@mui/material/IconButton';
import Portal from '@mui/material/Portal';
import { XCircle } from 'react-feather';
import { noop } from "../utils/noop";
import Grid from '@mui/material/Grid';

import ArrowBackIcon from '@mui/icons-material/ArrowBack';

export default function FilterDrawerTopic({
  open = false,
  onClose = noop,
  onFilterChange = noop,
  FormatFilter = noop,
  TopicFilter = noop,
  activeFilters = noop
}) {
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
          <Box flex={1} className="filterDrawer__title">ALL TOPICS</Box>
		</Box>
		<Box className="filterDrawer__topic_container" sx={{ flexGrow: 1 }}>
			<Grid container spacing={2}>
				<Grid item xs={10} className="topic__column_left">
					<Grid container spacing={2}>
						<Grid item xs={4}>
							1
						</Grid>
						<Grid item xs={4}>
							2
						</Grid>
						<Grid item xs={4}>
							3
						</Grid>
						<Grid item xs={4}>
							4
						</Grid>
						<Grid item xs={4}>
							5
						</Grid>
						<Grid item xs={4}>
							6
						</Grid>

						<Grid item xs={4}>
							A
						</Grid>
						<Grid item xs={4}>
							B
						</Grid>
						<Grid item xs={4}>
							C
						</Grid>
						<Grid item xs={4}>
							D
						</Grid>
						<Grid item xs={4}>
							E
						</Grid>
						<Grid item xs={4}>
							F
						</Grid>

						<Grid item xs={4}>
							1
						</Grid>
						<Grid item xs={4}>
							2
						</Grid>
						<Grid item xs={4}>
							3
						</Grid>
						<Grid item xs={4}>
							4
						</Grid>
						<Grid item xs={4}>
							5
						</Grid>
						<Grid item xs={4}>
							6
						</Grid>

						<Grid item xs={4}>
							A
						</Grid>
						<Grid item xs={4}>
							B
						</Grid>
						<Grid item xs={4}>
							C
						</Grid>
						<Grid item xs={4}>
							D
						</Grid>
						<Grid item xs={4}>
							E
						</Grid>
						<Grid item xs={4}>
							F
						</Grid>


					</Grid>
				</Grid>
				<Grid item xs={2} className="topic__column_right">
					<Box className="toc__alph_select">A</Box>
					<Box className="toc__alph_select selected">B</Box>
					<Box className="toc__alph_select">C</Box>
					<Box className="toc__alph_select">D</Box>
					<Box className="toc__alph_select">E</Box>
					<Box className="toc__alph_select">F</Box>
					<Box className="toc__alph_select">G</Box>
					<Box className="toc__alph_select">H</Box>
					<Box className="toc__alph_select">I</Box>
					<Box className="toc__alph_select">J</Box>
					<Box className="toc__alph_select">K</Box>
					<Box className="toc__alph_select">L</Box>
					<Box className="toc__alph_select">M</Box>
					<Box className="toc__alph_select">N</Box>
					<Box className="toc__alph_select">O</Box>
					<Box className="toc__alph_select">P</Box>
					<Box className="toc__alph_select">Q</Box>
					<Box className="toc__alph_select">R</Box>
					<Box className="toc__alph_select">S</Box>
					<Box className="toc__alph_select">T</Box>
					<Box className="toc__alph_select">U</Box>
					<Box className="toc__alph_select">V</Box>
					<Box className="toc__alph_select">W</Box>
					<Box className="toc__alph_select">X</Box>
					<Box className="toc__alph_select">Y</Box>
					<Box className="toc__alph_select">Z</Box>
				</Grid>
			</Grid>
		</Box>
		{/*
		<Box flex={1} className="filterDrawer__format">
			<Box className="format__less">
				<IconButton aria-label="Back">
					<ArrowBackIcon />
					<Typography className="less__label">BACK</Typography>

				</IconButton>
			</Box>
			<Box className="format__title">
				<Typography>FOO FORMAT</Typography>
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
		</Box>
		*/}
      </Drawer>
    </Portal>
  );
}
