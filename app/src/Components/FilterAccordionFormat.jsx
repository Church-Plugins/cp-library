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

import Controllers_Filter from "../Controllers/Filter";

export default function FilterAccordionFormat({
  FilterController = new Controllers_Filter()
}) {
  return (
	<Accordion>
		<AccordionSummary
			expandIcon={<ExpandMoreIcon />}
			aria-controls="panel-format-content"
			id="panel-format-header"
		>
			<Typography>FORMAT</Typography>
		</AccordionSummary>
		<AccordionDetails>
			<FormGroup>
				<FormControlLabel
					control={
						<Checkbox
							defaultChecked
							name = "filter__audio"
							onChange={FilterController.handleFormatChange} />
					}
					label="Audio" />
				<FormControlLabel
					control={
						<Checkbox
							defaultChecked
							name = "filter__video"
							onChange={FilterController.handleFormatChange} />
					}
					label="Video" />
				<FormControlLabel
					control={
						<Checkbox
							defaultChecked
							name = "filter__all"
							onChange={FilterController.handleFormatChange} />
					}
					label="All" />
			</FormGroup>
		</AccordionDetails>
	</Accordion>
  );
}
