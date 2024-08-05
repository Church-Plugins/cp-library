import { Button, ClickAwayListener, Popper } from '@mui/material';
import { Box } from '@mui/system';
import Collapse from '@mui/material/Collapse';
import { useState } from 'react';
import { ChevronDown, ChevronUp } from 'react-feather';
import clsx from 'clsx';
import FormGroup from "@mui/material/FormGroup";
import FormControlLabel from "@mui/material/FormControlLabel";
import Checkbox from "@mui/material/Checkbox";

import { noop } from "../utils/noop";

export default function FilterFormat ({
	activeFilters = [],
	onFilterChange = noop,
}) {
	const [anchorEl, setAnchorEl] = useState(null);
	const [isOpen, setIsOpen] = useState(false);
	const [isViewingAll, setIsViewingAll] = useState(false);

	const handleButtonClick = e => {
		setAnchorEl(e.currentTarget);
		setIsOpen(!isOpen);
	};

	const handleClose = () => {
		setIsOpen(false);
	};

	return (
		<ClickAwayListener onClickAway={handleClose}>
			<Box className={clsx('format__root', isOpen && 'format__active')}>
				<Button
					className="format__toggleButton"
					variant="text"
					endIcon={isOpen ? <ChevronUp/> : <ChevronDown/>}
					onClick={handleButtonClick}
					sx={{
						whiteSpace             : 'nowrap',
						backgroundColor        : isOpen && '#E5E5E5',
						borderBottomLeftRadius : 0,
						borderBottomRightRadius: 0,
					}}
					disableRipple
				>
					Format
				</Button>

				<Popper
					className="format__popper"
					open={isOpen}
					anchorEl={anchorEl}
					keepMounted={true}
					style={{width: isViewingAll ? '100%' : 281, maxWidth: '100vw', boxSizing: 'border-box'}}
					placement={isViewingAll ? 'bottom' : 'bottom-start'}
					transition
				>
					{({TransitionProps}) => (
						<Collapse {...TransitionProps} timeout={150}>
							<Box
								className="format__popperContent"
								backgroundColor="white"
								paddingY={1}
								paddingX={2}
								borderRadius={2}
								sx={{borderTopLeftRadius: isViewingAll ? 8 : 0}}
							>
								<FormGroup>
									<FormControlLabel
										control={
											<Checkbox
												name="format__audio"
												onChange={() => onFilterChange('format__audio')}
												checked={activeFilters && activeFilters.formats && activeFilters.formats.includes(
													'format__audio')}
											/>
										}
										label="Audio"/>
									<FormControlLabel
										control={
											<Checkbox
												name="format__video"
												onChange={() => onFilterChange('format__video')}
												checked={activeFilters && activeFilters.formats && activeFilters.formats.includes(
													'format__video')}
											/>
										}
										label="Video"/>
								</FormGroup>
							</Box>
						</Collapse>
					)}
				</Popper>
			</Box>
		</ClickAwayListener>
	);
}
