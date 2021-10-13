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
  const [activeFilters, setActiveFilters] = useState(
	  	{
	  		'topics': [],
			'formats': [],
			'page': 1
		}
	);

  const toggleFilter = (label) => {

	// TODO: This data structure assumes the value is a string.
    // TODO: This data structure is not performant for large list. Consider an object/map/dict
    // instead.

	console.log( "TOGGLE FILTER" );
	console.log( label );

	let index = 0;
	let filterType = '';
	if( label.startsWith( 'format__' ) ) {
		index = activeFilters.formats.findIndex(activeFilter => activeFilter === label);
		filterType = 'format';
	} else {
		index = activeFilters.topics.findIndex(activeFilter => activeFilter === label);
		filterType = 'topic';
	}

	if (index === -1) {
		addFilter( label, filterType )
	} else {
		removeFilter( label, filterType )
	}
  }

  // TODO: Wire-up
  const addFilter = (label, filterType) => {

	console.log( "ADD " + label );

	let topicInput 	= ('topic' === filterType) ? [...activeFilters.topics, label] : [...activeFilters.topics];
	let formatInput	= ('format' === filterType) ? [...activeFilters.formats, label] : [...activeFilters.formats];

	setActiveFilters(
		{
			'topics': topicInput,
			'formats': formatInput,
			'page': 1
		}
	);
  }

  // TODO: Wire-up
  const removeFilter = (label, filterType) => {

	console.log( "REMOVE " + label );

	let topicInput 	= ('topic' === filterType) ? activeFilters.topics.filter( f => f !== label ) : [...activeFilters.topics];
	let formatInput	= ('format' === filterType) ? activeFilters.formats.filter( f => f !== label ) : [...activeFilters.formats];

    setActiveFilters(
		{
			'topics': topicInput,
			'formats': formatInput,
			'page': 1
	 	});
  }

  // TODO: Wire-up
  const clearFilters = (filterType) => {

	console.log( "CLEAR " + filterType );

	let topicInput 	= ('topic' === filterType) ? [] : [...activeFilters.topics];
	let formatInput	= ('format' === filterType) ? [] : [...activeFilters.formats];

    setActiveFilters({
		'topics': topicInput,
		'formats': formatInput,
		'page': 1
  	});
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
		  autoFocus={true}
        />
      </Box>
      <Box className="talks__itemListContainer" paddingY={1} paddingX={1}>
        <ItemList activeFilters={activeFilters} className="talks__itemList"/>
      </Box>
    </>
  );
}
