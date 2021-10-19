import React, {useState} from "react";
import Accordion from '@mui/material/Accordion';
import AccordionSummary from '@mui/material/AccordionSummary';
import AccordionDetails from '@mui/material/AccordionDetails';
import Typography from '@mui/material/Typography';
import ExpandMoreIcon from '@mui/icons-material/ExpandMore';
import FormGroup from '@mui/material/FormGroup';
import FormControlLabel from '@mui/material/FormControlLabel';
import Checkbox from '@mui/material/Checkbox';
import { noop } from "../utils/noop";
import ArrowForwardIcon from '@mui/icons-material/ArrowForward';
import IconButton from '@mui/material/IconButton';
import Box from '@mui/material/Box';
import Grid from '@mui/material/Grid';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import $ from 'jquery';

import FilterAccordionTopic from './FilterAccordionTopic';

export default function FilterAccordionPopular({
	onFilterChange = noop,
	activeFilters = noop,
	open = false,
	onClose = noop
}) {

  const [topicViewIsOpen, setTopicViewIsOpen] = useState( false );

  const expandTopicView = () => {
	$( '.format__browse_desktop' ).addClass( 'topic__view' );
  }

	const closeTopicView = () => {
		$( '.format__browse_desktop' ).removeClass( 'topic__view' );
	}

  return (
	<Accordion className="format__browse_desktop">
		<AccordionSummary
			expandIcon={<ExpandMoreIcon />}
			aria-controls="panel-format-content"
			id="panel-popular-header"
		>
			<Typography>POPULAR TOPICS</Typography>
		</AccordionSummary>
		<AccordionDetails className="format__browse_details">
			<Box>
				{topicViewIsOpen ? (
					<>
						<FilterAccordionTopic
							className="format__filter_topic"
							open={true}
							onClose={() => {setTopicViewIsOpen( !topicViewIsOpen ); closeTopicView();} }
							onFilterChange={onFilterChange}
							activeFilters={activeFilters}
						/>
					</>
				) : (
					<>
						<IconButton
							onClick={() => {setTopicViewIsOpen( !topicViewIsOpen ); expandTopicView();} }
							className="format__browse_all"
							aria-label="View All">
							<Box className="format__browse_content">
								<Typography className="more__label">VIEW ALL</Typography>
							</Box>
							<Box className="format__browse_control">
								<ArrowForwardIcon />
							</Box>
						</IconButton>

						<FormGroup className="format__item_list">
							<FormControlLabel
								className="format__item"
								control={<Checkbox
								value="worry"
								onChange={() => onFilterChange("worry")} />}
								label="Worry"
								checked={activeFilters && activeFilters.topics && activeFilters.topics.includes( "worry" )} />
							<FormControlLabel
								className="format__item"
								control={<Checkbox
								value="doubt"
								onChange={() => onFilterChange("doubt")}/>}
								label="Doubt"
								checked={activeFilters && activeFilters.topics && activeFilters.topics.includes( "doubt" )} />
							<FormControlLabel
								className="format__item"
								control={<Checkbox
								value="fear"
								onChange={() => onFilterChange("fear")}/>}
								label="Fear"
								checked={activeFilters && activeFilters.topics && activeFilters.topics.includes( "fear" )} />
							<FormControlLabel
								className="format__item"
								control={<Checkbox
								value="encouragement"
								onChange={() => onFilterChange("encouragement")}/>}
								label="Encouragement"
								checked={activeFilters && activeFilters.topics && activeFilters.topics.includes( "encouragement" )} />
						</FormGroup>
					</>
				)}
			</Box>
		</AccordionDetails>
	</Accordion>
  );
}
