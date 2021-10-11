import React, { useState } from 'react';
import Box from '@mui/material/Box';
import Button from '@mui/material/Button';
import Chip from '@mui/material/Chip';
import Divider from '@mui/material/Divider';

import FilterDrawer from './FilterDrawer';
import SearchInput from './SearchInput';
import useBreakpoints from '../Hooks/useBreakpoints';
import { noop } from '../utils/noop';

import FilterAccordionFormat from './FilterAccordionFormat';

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
              <Button
                variant="contained"
                onClick={() => setFilterDrawerIsOpen(!filterDrawerIsOpen)}
              >
                Filter
              </Button>
            </Box>
			<Box className="filter__accordion filter__format" flex={0}>
            	<FilterAccordionFormat />
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
              <Button
                variant="contained"
                onClick={() => setFilterDrawerIsOpen(!filterDrawerIsOpen)}
              >
                Filter
              </Button>
            </Box>
            </>
        )}
      </Box>

      {isDesktop ? <Divider className="filter__divider" sx={{ marginY: 3 }} /> : null}

      <Box
        component="ul"
        className="filter__activeFilterList"
        display="flex"
        flexWrap="wrap"
        padding={0}
        marginBottom={0}
        // Offset for the most-left and most-right chips
        marginX={-0.5}
      >
        {activeFilters.map(filter => (
          <Chip
            component="li"
            className="filter__activeFilter"
            // TODO: Needs a unique key
            key={filter}
            label={filter}
            variant="outlined"
            onDelete={() => onRemoveFilter(filter)}
            sx={{ margin: 0.5 }}
          />
        ))}
      </Box>

      <FilterDrawer open={filterDrawerIsOpen} onClose={() => setFilterDrawerIsOpen(false)} />
    </Box>
  );
}
