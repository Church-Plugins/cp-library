import React, { useState } from 'react';
import Box from '@mui/material/Box';
import { ChevronLeft } from 'react-feather';

import ItemList from "./ItemList";
import Filter from "./Filter";
import RoundButton from "./RoundButton";

import useBreakpoints from '../Hooks/useBreakpoints';
import debounce from '@mui/utils/debounce';

export default function Talks() {
  const { isDesktop } = useBreakpoints();
  const [activeFilters, setActiveFilters] = useState([]);

  const toggleFilter = (label) => {
    // TODO: This data structure assumes the value is a string.
    // TODO: This data structure is not performant for large list. Consider an object/map/dict
    // instead.
    const index = activeFilters.findIndex(activeFilter => activeFilter === label);

    if (index === -1) {
      addFilter(label)
    } else {
      removeFilter(label)
    }
  }

  // TODO: Wire-up
  const addFilter = (label) => {
    setActiveFilters([...activeFilters, label]);
  }

  // TODO: Wire-up
  const removeFilter = (label) => {
    setActiveFilters(activeFilters.filter(f => f !== label));
  }

  // TODO: Wire-up
  const clearFilters = () => {
    setActiveFilters([]);
  };

  // TODO: Wire-up
  const handleSearchInputChange = debounce((value) => {
    console.log(value);
  }, 500);

  return (
    <>
      <Box
        className="talks__stickyContainer"
        position="sticky"
        top={0}
        padding={2}
        // So that itemlist tucks underneath when scrolled up.
        zIndex={1}
        // TODO: To get this right, give the root element a background color that matches the host's
        backgroundColor="inherit"
      >
        <Box className="talks__header" marginY={2}>
          {!isDesktop && activeFilters.length > 0 ? (
            <RoundButton variant="contained" leftIcon={<ChevronLeft />} onClick={() => clearFilters()}>
              Back
            </RoundButton>
          ) : (
            <h1 className="talks__title">Talks</h1>
          )}
        </Box>
        <Filter
          className="talks__filter"
          activeFilters={activeFilters}
          onRemoveFilter={removeFilter}
          onSearchInputChange={handleSearchInputChange}
          onFilterChange={toggleFilter}
        />
      </Box>
      <Box className="talks__itemListContainer" paddingY={1} paddingX={1}>
        <ItemList activeFilters={activeFilters} className="talks__itemList"/>
      </Box>
    </>
  );
}
