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

export default function FilterAccordionPopular({
	FilterController = new Controllers_Filter()
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
				<FormControlLabel control={<Checkbox value="worry" onChange={FilterController.handleTopicSelection} />} label="Worry" />
				<FormControlLabel control={<Checkbox value="doubt" onChange={FilterController.handleTopicSelection}/>} label="Doubt" />
				<FormControlLabel control={<Checkbox value="fear" onChange={FilterController.handleTopicSelection}/>} label="Fear" />
				<FormControlLabel control={<Checkbox value="anxiety" onChange={FilterController.handleTopicSelection}/>} label="Anxiety" />
				<FormControlLabel control={<Checkbox value="depression" onChange={FilterController.handleTopicSelection}/>} label="Depression" />
				<FormControlLabel control={<Checkbox value="encouragement" onChange={FilterController.handleTopicSelection}/>} label="Encouragement" />
			</FormGroup>
		</AccordionDetails>
	</Accordion>
  );
}
