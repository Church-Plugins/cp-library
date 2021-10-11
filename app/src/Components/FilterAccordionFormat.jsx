import React from "react";
import Accordion from '@mui/material/Accordion';
import AccordionSummary from '@mui/material/AccordionSummary';
import AccordionDetails from '@mui/material/AccordionDetails';
import Typography from '@mui/material/Typography';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
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
			<Typography>
				<p>Lorem ipsum dolor sit amet, consectetur adipiscing elit. Curabitur massa est, porta non vulputate eu, mattis in nulla.</p>
				<p>Vestibulum ante ipsum primis in faucibus orci luctus et ultrices posuere cubilia curae; In auctor ut ante id bibendum. Ut tempus laoreet dolor, ut blandit lorem ullamcorper eu.</p>
				<p>Pellentesque pharetra, nisi sit amet placerat dignissim, lectus magna eleifend dolor, id faucibus ante neque vitae arcu.</p>
				<p>Etiam volutpat et purus et luctus. Nullam gravida lacus velit, quis facilisis diam condimentum sit amet. Aenean odio ex, ullamcorper id fermentum at, blandit vel odio.</p>
			</Typography>
		</AccordionDetails>
	</Accordion>
  );
}
