import React, { useState, useEffect } from 'react';
import Box from '@mui/material/Box';
import { ChevronLeft } from 'react-feather';
import { cplVar } from '../utils/helpers';

import TypeList from './TypeList';
import Filter from './Filter';
import Round from '../Elements/Buttons/Round';

import useBreakpoints from '../Hooks/useBreakpoints';
import debounce from '@mui/utils/debounce';

export default function Types () {
	const {isDesktop} = useBreakpoints();
	const [activeFilters, setActiveFilters] = useState(
		{
			'topics' : [],
			'formats': [],
			'page'   : 1,
			'search' : ''
		}
	);

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

		let topicInput = (
			'topic' === filterType
		) ? [...activeFilters.topics, label] : [...activeFilters.topics];
		let formatInput = (
			'format' === filterType
		) ? [...activeFilters.formats, label] : [...activeFilters.formats];

		setActiveFilters(
			{
				'topics' : topicInput,
				'formats': formatInput,
				'page'   : 1,
				'search' : activeFilters.search
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
				'topics' : [...activeFilters.topics],
				'formats': [...activeFilters.formats],
				'page'   : 1,
				'search' : value
			});
	}, 1000);

	useEffect(() => {
		const urlParams = new URLSearchParams(window.location.search);
		const format = urlParams.get('format');

		if (format) {
			addFilter('format__' + format, 'format');
		}
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
						<Round variant="contained" leftIcon={<ChevronLeft/>} onClick={() => clearFilters()}>
							Back
						</Round>
					) : (
						<h1 className="talks__title">{cplVar( 'labelPlural', 'item_type' )}</h1>
					)}
				</Box>
			</Box>
			<Box className="talks__itemListContainer" paddingY={1} paddingX={1}>
				<TypeList activeFilters={activeFilters} className="talks__itemList"/>
			</Box>
		</>
	);
}
