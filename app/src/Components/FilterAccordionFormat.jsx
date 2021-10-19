import React from "react";
import Accordion from '@mui/material/Accordion';
import AccordionSummary from '@mui/material/AccordionSummary';
import AccordionDetails from '@mui/material/AccordionDetails';
import Typography from '@mui/material/Typography';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import FormGroup from '@mui/material/FormGroup';
import FormControlLabel from '@mui/material/FormControlLabel';
import Checkbox from '@mui/material/Checkbox';
import { noop } from "../utils/noop";

import Controllers_Filter from '../Controllers/Filter';

export default function FilterAccordionFormat({
  onFilterChange = noop,
  activeFilters = noop
}) {

  return (
	<Accordion className="format__browse_desktop">
		<AccordionSummary
			expandIcon={<ExpandMoreIcon />}
			aria-controls="panel-format-content"
			id="panel-format-header"
		>
			<Typography>FORMAT</Typography>
		</AccordionSummary>
		<AccordionDetails className="format__browse_details">
			<FormGroup>
				<FormControlLabel
					control={
						<Checkbox
							name = "format__audio"
							onChange={() => onFilterChange("format__audio")}
							checked={activeFilters && activeFilters.formats && activeFilters.formats.includes( "format__audio" )}
							/>
					}
					label="Audio" />
				<FormControlLabel
					control={
						<Checkbox
							name = "format__video"
							onChange={() => onFilterChange("format__video")}
							checked={activeFilters && activeFilters.formats && activeFilters.formats.includes( "format__video" )}
							/>
					}
					label="Video" />
			</FormGroup>
		</AccordionDetails>
	</Accordion>
  );
}
