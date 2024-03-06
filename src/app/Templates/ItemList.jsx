import React, { useState, useEffect } from 'react';
import Box from '@mui/material/Box';
import Pagination from '@mui/material/Pagination';

import Item from './Item';
import LoadingIndicator from '../Elements/LoadingIndicator';
import ErrorDisplay from '../Elements/ErrorDisplay';
import Controllers_WP_REST_Request from '../Controllers/WP_REST_Request';
import useBreakpoints from '../Hooks/useBreakpoints';

function ItemList ({
	activeFilters,
	setActiveFilters
}) {
	const [items, setItems] = useState([]);
	const [loading, setLoading] = useState(false);
	const [error, setError] = useState();
	const {isDesktop} = useBreakpoints();

	useEffect(() => {
		(
			async () => {
				try {

					if (!activeFilters.ready) {
						return;
					}

					setLoading(true);
					const restRequest = new Controllers_WP_REST_Request();
					let inputParams = 't=' +
					                  activeFilters.topics.join(',') + '&' +
					                  'f=' +
					                  activeFilters.formats.join(',') + '&' +
					                  's=' + encodeURIComponent(activeFilters.search).replace(' ', '+');

					if ( undefined !== activeFilters.type && activeFilters.type ) {
						inputParams += 'type=' + activeFilters.type;
					}

					const data = await restRequest.get({endpoint: 'items', params: inputParams});
					data.page = 1;
					setItems(data);
				} catch (error) {
					setError(error);
				} finally {
					setLoading(false);
				}
			}
		)();
	}, [activeFilters]);

	const handlePageChange = async (event, value) => {

		try {
			setLoading(true);
			document.querySelector('.cpl-filter').scrollIntoView({behavior: "smooth"});
			const restRequest = new Controllers_WP_REST_Request();
			let inputParams = 't=' +
			                  activeFilters.topics.join(',') + '&' +
			                  'f=' +
			                  activeFilters.formats.join(',') + '&' +
			                  'p=' + value + '&' +
			                  's=' + activeFilters.search;

			if ( undefined !== activeFilters.type && activeFilters.type ) {
				inputParams += 'type=' + activeFilters.type;
			}

			const data = await restRequest.get({endpoint: 'items', params: inputParams});
			data.page = value;
			setItems(data);
		} catch (error) {
			setError(error);
		} finally {
			setLoading(false);
		}
	};

	return loading ? (
		<LoadingIndicator/>
	) : error ? (
		<ErrorDisplay error={error}/>
	) : !items || !items.items || items.items.length === 0 ? (
		<Box>No items</Box>
	) : (
		<>
			<Box className="cpl-archive--list">
				{items.items.map((item, index) => ( // "isNew" assumes the items are sorted by creation date descendingly.
					<Box className="cpl-archive--list--item" key={index}>
						<Item item={item} isNew={index === 0} setActiveFilters={setActiveFilters}/>
					</Box>
				))}
			</Box>
			{1 < items.pages && (
				<Box className="navigation pagination cpl-pagination">
					<Pagination
						sx={[
							{
								'& .MuiPaginationItem-root:hover': {
									backgroundColor: 'rgba(255, 255, 255, 0.2)'
								},
								'& .Mui-selected': {
									backgroundColor: 'rgba(255, 255, 255, 0.4) !important'
								}
							}
						]}
						size={isDesktop ? 'large' : 'small'}
						count={items.pages}
						defaultPage={items.page}
						boundaryCount={2}
						onChange={handlePageChange}/>
				</Box>
			)}
		</>
	);
}

export default ItemList;
