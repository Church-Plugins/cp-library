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

export default function FilterAccordionPopular({
	onFilterChange = noop,
}) {
  return (
	<Accordion>
		<AccordionSummary
			expandIcon={<ExpandMoreIcon />}
			aria-controls="panel-format-content"
			id="panel-popular-header"
		>
			<Typography>POPULAR TOPICS</Typography>
		</AccordionSummary>
		<AccordionDetails>
			<FormGroup>
				<FormControlLabel control={<Checkbox value="worry" onChange={() => onFilterChange("worry")} />} label="Worry" />
				<FormControlLabel control={<Checkbox value="doubt" onChange={() => onFilterChange("doubt")}/>} label="Doubt" />
				<FormControlLabel control={<Checkbox value="fear" onChange={() => onFilterChange("fear")}/>} label="Fear" />
				<FormControlLabel control={<Checkbox value="anxiety" onChange={() => onFilterChange("anxiety")}/>} label="Anxiety" />
				<FormControlLabel control={<Checkbox value="depression" onChange={() => onFilterChange("depression")}/>} label="Depression" />
				<FormControlLabel control={<Checkbox value="encouragement" onChange={() => onFilterChange("encouragement")}/>} label="Encouragement" />
			</FormGroup>
		</AccordionDetails>
	</Accordion>
  );
}
