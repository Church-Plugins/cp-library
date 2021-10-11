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
  open = false,
  onClose = noop,
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
				<FormControlLabel control={<Checkbox />} label="Worry" />
				<FormControlLabel control={<Checkbox />} label="Doubt" />
				<FormControlLabel control={<Checkbox />} label="Fear" />
				<FormControlLabel control={<Checkbox />} label="Anxiety" />
				<FormControlLabel control={<Checkbox />} label="Depression" />
				<FormControlLabel control={<Checkbox />} label="Encouragement" />
			</FormGroup>
		</AccordionDetails>
	</Accordion>
  );
}
