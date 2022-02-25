import React, { useState, useEffect } from 'react';
import Box from '@mui/material/Box';
import { ChevronLeft } from 'react-feather';
import { cplVar } from '../utils/helpers';

import ItemList from './ItemList';
import Filter from './Filter';
import RoundButton from '../Elements/RoundButton';

import useBreakpoints from '../Hooks/useBreakpoints';
import debounce from '@mui/utils/debounce';

export default function Items () {
	const {isDesktop} = useBreakpoints();
	const [activeFilters, setActiveFilters] = useState( {
		'ready'  : false,
		'topics' : [],
		'formats': [],
		'page'   : 1,
		'search' : '',
	} );

	const toggleFilter = (label) => {

		// TODO: This data structure assumes the value is a string.
		// TODO: This data structure is not performant for large list. Consider an object/map/dict
		// instead.

		let index = 0;
		let filterType = '';
		if (label.startsWith('format__')) {
			index = activeFilters.formats.findIndex(activeFilter => activeFilter === label);
			filterType = 'format';
		} else {
			index = activeFilters.topics.findIndex(activeFilter => activeFilter === label);
			filterType = 'topic';
		}

		if (index === -1) {
			addFilter(label, filterType);
		} else {
			removeFilter(label, filterType);
		}
	};

	// TODO: Wire-up
	const addFilter = (label, filterType) => {
		let topicInput = ('topic' === filterType) ? [...activeFilters.topics, label] : [...activeFilters.topics];
		let formatInput = ('format' === filterType) ? [...activeFilters.formats, label] : [...activeFilters.formats];
		let search = ('search' === filterType) ? label : activeFilters.search;
		let ready = ('ready' === filterType) ? label : activeFilters.ready;

		setActiveFilters(
			{
				'ready'  : ready,
				'topics' : topicInput,
				'formats': formatInput,
				'page'   : 1,
				'search' : search
			}
		);
	};

	// TODO: Wire-up
	const removeFilter = (label, filterType) => {
		if (!filterType) {
			filterType = 'topic';
		}

		let topicInput = (
			'topic' === filterType
		) ? activeFilters.topics.filter(f => f !== label) : [...activeFilters.topics];
		let formatInput = (
			'format' === filterType
		) ? activeFilters.formats.filter(f => f !== label) : [...activeFilters.formats];

		setActiveFilters(
			{
				'ready'  : activeFilters.ready,
				'topics' : topicInput,
				'formats': formatInput,
				'page'   : 1,
				'search' : activeFilters.search
			});
	};

	// TODO: Wire-up
	const clearFilters = (filterType) => {

		let topicInput = (
			'topic' === filterType
		) ? [] : [...activeFilters.topics];
		let formatInput = (
			'format' === filterType
		) ? [] : [...activeFilters.formats];

		setActiveFilters({
			'ready'  : activeFilters.ready,
			'topics' : topicInput,
			'formats': formatInput,
			'page'   : 1,
			'search' : ''
		});
	};

	// TODO: Wire-up
	const handleSearchInputChange = debounce((value) => {
		setActiveFilters(
			{
				'ready'  : activeFilters.ready,
				'topics' : [...activeFilters.topics],
				'formats': [...activeFilters.formats],
				'page'   : 1,
				'search' : value
			});
	}, 1000);

	useEffect(() => {
		const urlParams = new URLSearchParams(window.location.search);
		const format = urlParams.get('format');
		const search = urlParams.get('s');

		setActiveFilters(
			{
				'ready'  : true,
				'topics' : [],
				'formats': format ? [ 'format__' + format ] : [],
				'page'   : 1,
				'search' : search ? search : '',
			}
		);
	}, []);

	return (
		<>
			<Box
				className="talks__headerContainer"
				top={0}
				// So that itemlist tucks underneath when scrolled up.
				zIndex={1}
				// TODO: To get this right, give the root element a background color that matches the host's
				backgroundColor="inherit"
			>
				<Box className="talks__header" marginY={2}>
					{!isDesktop && activeFilters.length > 0 ? (
						<RoundButton variant="contained" leftIcon={<ChevronLeft/>} onClick={() => clearFilters()}>
							Back
						</RoundButton>
					) : (
						<h1 className="talks__title">{cplVar('labelPlural', 'item')}</h1>
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
