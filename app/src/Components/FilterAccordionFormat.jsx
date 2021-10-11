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

export default function FilterAccordionFormat({
  open = false,
  onClose = noop,
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
				<FormControlLabel control={<Checkbox defaultChecked />} label="Audio" />
				<FormControlLabel control={<Checkbox defaultChecked />} label="Video" />
				<FormControlLabel control={<Checkbox defaultChecked />} label="All" />
			</FormGroup>
		</AccordionDetails>
	</Accordion>
  );
}
