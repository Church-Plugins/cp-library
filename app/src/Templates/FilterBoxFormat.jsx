import React from "react";
import Box from '@mui/material/Box';
import FormGroup from '@mui/material/FormGroup';
import FormControlLabel from '@mui/material/FormControlLabel';
import Checkbox from '@mui/material/Checkbox';
import { noop } from "../utils/noop";

export default function FilterBoxFormat({
  onFilterChange = noop,
  activeFilters = noop
}) {

  return (
	<Box>
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
	</Box>
  );
}
