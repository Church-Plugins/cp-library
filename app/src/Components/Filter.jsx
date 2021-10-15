import React, { useState } from 'react';
import Box from '@mui/material/Box';
import Chip from '@mui/material/Chip';
import Divider from '@mui/material/Divider';

import SearchInput from './SearchInput';
import RoundButton from './RoundButton';
import FilterDrawer from './FilterDrawer';
import useBreakpoints from '../Hooks/useBreakpoints';
import { noop } from '../utils/noop';

import FilterAccordionFormat from './FilterAccordionFormat';
import FilterAccordionPopular from './FilterAccordionPopular';
import FilterBoxFormat from './FilterBoxFormat';
import FilterBoxPopular from './FilterBoxPopular';

export default function Filter ({
  activeFilters = [],
  onRemoveFilter = noop,
  onSearchInputChange = noop,
  onFilterChange = noop,
}) {
  const { isDesktop } = useBreakpoints();
  const [filterDrawerIsOpen, setFilterDrawerIsOpen] = useState(false);

  const RenderAccordionFormats = () => {
	return <FilterAccordionFormat onFilterChange={onFilterChange} activeFilters={activeFilters} />
  }

  const RenderAccordionPopular = () => {
	return <FilterAccordionPopular onFilterChange={onFilterChange} activeFilters={activeFilters} />
  }

  const RenderBoxFormats = () => {
	return <FilterBoxFormat onFilterChange={onFilterChange} activeFilters={activeFilters} />
  }

  const RenderBoxPopular = () => {
	return <FilterBoxPopular onFilterChange={onFilterChange} activeFilters={activeFilters} />
  }

  return (
    <Box className="filter__root">
      <Box display="flex" justifyContent={isDesktop ? "space-between" : "initial"}>
        {isDesktop ? (
          <>
		  	{/*
            <Box className="filter__button" flex={0} display="flex" alignItems="center">
              <RoundButton
                variant="contained"
                onClick={() => setFilterDrawerIsOpen(!filterDrawerIsOpen)}
              >
                Filter
              </RoundButton>
            </Box>
			*/}
			<Box className="filter__accordion filter__format" flex={0}>
				<RenderAccordionFormats />
            </Box>
			<Box className="filter__accordion filter__popular" flex={0}>
            	<RenderAccordionPopular />
            </Box>
            <Box className="filter__search" flex={0} display="flex" alignItems="center">
              <SearchInput onValueChange={onSearchInputChange} />
            </Box>
          </>
        ) : (
          <>
            <Box className="filter__search" flex={1} display="flex" alignItems="center">
              <SearchInput width="full" onValueChange={onSearchInputChange} />
            </Box>
            <Box className="filter__button" flex={0} marginLeft={2} display="flex" alignItems="center">
              <RoundButton
                variant="contained"
                onClick={() => setFilterDrawerIsOpen(!filterDrawerIsOpen)}
              >
                Filter
              </RoundButton>
            </Box>
			<FilterDrawer
				open={filterDrawerIsOpen}
				onClose={() => setFilterDrawerIsOpen(false)}
				onFilterChange={onFilterChange}
				FormatFilter={RenderBoxFormats}
				TopicFilter={RenderBoxPopular}
				activeFilters={activeFilters}
				/>
          </>
        )}
      </Box>

      {isDesktop ? <Divider className="filter__divider" sx={{ marginY: 3 }} /> : null}

      {activeFilters.topics.length > 0 && (
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
          {activeFilters.topics.map(filter => (
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
      )}
    </Box>
  );
}
