import React, { useState, useEffect } from 'react';
import Box from '@mui/material/Box';
import { ChevronLeft } from 'react-feather';
import { cplVar } from '../utils/helpers';

import ItemList from './ItemList';
import Filter from './Filter';
import Round from '../Elements/Buttons/Round';

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
		const topics = urlParams.get('topics');
		const search = urlParams.get('s');

		setActiveFilters(
			{
				'ready'  : true,
				'topics' : topics ? [ topics ] : [],
				'formats': format ? [ 'format__' + format ] : [],
				'page'   : 1,
				'search' : search ? search : '',
			}
		);
	}, []);

	return (
		<Box className="cpl-archive cpl-archive--item">
			{!isDesktop && activeFilters.length > 0 ? (
				<Round variant="contained" leftIcon={<ChevronLeft/>} onClick={() => clearFilters()}>
					Back
				</Round>
			) : (
				<h1 className="page-title">{cplVar('labelPlural', 'item')}</h1>
			)}

			<Box className="cpl-archive--container">
				<Filter
					className="cpl-archive--container--filter"
					activeFilters={activeFilters}
					onRemoveFilter={removeFilter}
					onSearchInputChange={handleSearchInputChange}
					onFilterChange={toggleFilter}
					autoFocus={true}
				/>

				<ItemList activeFilters={activeFilters} setActiveFilters={setActiveFilters} />
			</Box>
		</Box>
	);
}
