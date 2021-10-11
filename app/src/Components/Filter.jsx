import React, { useState } from 'react';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';

import SearchInput from './SearchInput';
import useBreakpoints from '../Hooks/useBreakpoints';
import { noop } from '../utils/noop';

import FilterAccordionFormat from './FilterAccordionFormat';
import FilterAccordionPopular from './FilterAccordionPopular';

export default function Filter ({
  activeFilters = [],
  onRemoveFilter = noop,
  onSearchInputChange = noop,
}) {
  const { isDesktop } = useBreakpoints();
  const [filterDrawerIsOpen, setFilterDrawerIsOpen] = useState(false);

  return (
    <Box className="filter__root">
      <Box display="flex" justifyContent={isDesktop ? "space-between" : "initial"}>
        {isDesktop ? (
          <>
            <Box className="filter__button" flex={0}>
              <Button variant="contained">
                Filter
              </Button>
            </Box>
			<Box className="filter__accordion filter__format" flex={0}>
            	<FilterAccordionFormat />
            </Box>
			<Box className="filter__accordion filter__popular" flex={0}>
            	<FilterAccordionPopular />
            </Box>
            <Box className="filter__search" flex={0}>
              <SearchInput onValueChange={onSearchInputChange} />
            </Box>
          </>
        ) : (
          <>
            <Box className="filter__search" flex={1}>
              <SearchInput width="full" onValueChange={onSearchInputChange} />
            </Box>
            <Box className="filter__button" flex={0} marginLeft={2}>
              <Button variant="contained">
                Filter
              </Button>
            </Box>
            </>
        )}
      </Box>

    </Box>
  );
}
