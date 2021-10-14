import React from "react";
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import Drawer from '@mui/material/Drawer';
import IconButton from '@mui/material/IconButton';
import Portal from '@mui/material/Portal';
import { XCircle } from 'react-feather';
import { noop } from "../utils/noop";
import Grid from '@mui/material/Grid';
import Link from '@mui/material/Link';

import ArrowBackIcon from '@mui/icons-material/ArrowBack';

export default function FilterDrawerTopic({
  open = false,
  onClose = noop,
  onFilterChange = noop,
  FormatFilter = noop,
  TopicFilter = noop,
  activeFilters = noop
}) {

const alphabet = [
	'A', 'B', 'C', 'D', 'E', 'F', 'G',
	'H', 'I', 'J', 'K', 'L', 'M', 'N',
	'O', 'P', 'Q', 'R', 'S', 'T', 'U',
	'V', 'W', 'X', 'Y', 'Z'
];

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

						<Grid item xs={12}>
							<Box className="format__less">
								<IconButton onClick={onClose} aria-label="Back">
									<ArrowBackIcon />
									<Typography className="less__label">BACK</Typography>
								</IconButton>
							</Box>
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
					{alphabet.map(
						(letter, index) => {
							return <Box
								className={`toc__alph_select select__${letter}`}
							>
									<Link
										className="filterDrawer__alph_link"
										underline="none"
										href={`#${letter}`}
									>
										{letter}
									</Link>
							</Box>
						}
					)}
				</Grid>
			</Grid>
		</Box>
      </Drawer>
    </Portal>
  );
}
