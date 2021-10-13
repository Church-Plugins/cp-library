import React from "react";
import Box from '@mui/material/Box';
import FormGroup from '@mui/material/FormGroup';
import FormControlLabel from '@mui/material/FormControlLabel';
import Checkbox from '@mui/material/Checkbox';
import { noop } from "../utils/noop";

export default function FilterBoxPopular({
	onFilterChange = noop,
	activeFilters={activeFilters}
}) {
  return (
	<Box>
		<FormGroup>
			<FormControlLabel
				control={<Checkbox
				value="worry"
				onChange={() => onFilterChange("worry")} />}
				label="Worry"
				checked={activeFilters && activeFilters.topics && activeFilters.topics.includes( "worry" )} />
			<FormControlLabel
				control={<Checkbox
				value="doubt"
				onChange={() => onFilterChange("doubt")}/>}
				label="Doubt"
				checked={activeFilters && activeFilters.topics && activeFilters.topics.includes( "doubt" )} />
			<FormControlLabel
				control={<Checkbox
				value="fear"
				onChange={() => onFilterChange("fear")}/>}
				label="Fear"
				checked={activeFilters && activeFilters.topics && activeFilters.topics.includes( "fear" )} />
			<FormControlLabel
				control={<Checkbox
				value="anxiety"
				onChange={() => onFilterChange("anxiety")}/>}
				label="Anxiety"
				checked={activeFilters && activeFilters.topics && activeFilters.topics.includes( "anxiety" )} />
			<FormControlLabel
				control={<Checkbox
				value="depression"
				onChange={() => onFilterChange("depression")}/>}
				label="Depression"
				checked={activeFilters && activeFilters.topics && activeFilters.topics.includes( "depression" )} />
			<FormControlLabel
				control={<Checkbox
				value="encouragement"
				onChange={() => onFilterChange("encouragement")}/>}
				label="Encouragement"
				checked={activeFilters && activeFilters.topics && activeFilters.topics.includes( "encouragement" )} />
		</FormGroup>
	</Box>
  );
}
