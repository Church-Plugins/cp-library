import React, { useState, useEffect } from 'react';
import Box from '@mui/material/Box';
import List from '@mui/material/List';
import { makeStyles } from '@mui/styles';
import Pagination from '@mui/material/Pagination';
import async from 'async';

import Item from './Item';
import LoadingIndicator from '../Elements/LoadingIndicator';
import ErrorDisplay from '../Elements/ErrorDisplay';
import Controllers_WP_REST_Request from '../Controllers/WP_REST_Request';
import useBreakpoints from '../Hooks/useBreakpoints';

function ItemList ({
	activeFilters,
}) {
	const [items, setItems] = useState([]);
	const [loading, setLoading] = useState(false);
	const [error, setError] = useState();
	const {isDesktop} = useBreakpoints();

	const useStyles = makeStyles(() => (
		{
			ul: {
				'& .MuiPaginationItem-root'      : {
					color: '#fff'
				},
				'& .MuiPaginationItem-root:hover': {
					backgroundColor: 'rgba(255, 255, 255, 0.2)'
				},
				'& .Mui-selected'                : {
					backgroundColor: 'rgba(255, 255, 255, 0.4) !important'
				}
			}
		}
	));
	const classes = useStyles();

	useEffect(() => {
		(
			async () => {
				try {
					setLoading(true);
					const restRequest = new Controllers_WP_REST_Request();
					let inputParams = 't=' +
					                  activeFilters.topics.join(',') + '&' +
					                  'f=' +
					                  activeFilters.formats.join(',') + '&' +
					                  'type=' +
					                  activeFilters.type + '&' +
					                  's=' + encodeURIComponent(activeFilters.search).replace(' ', '+');
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
			const restRequest = new Controllers_WP_REST_Request();
			let inputParams = 't=' +
			                  activeFilters.topics.join(',') + '&' +
			                  'f=' +
			                  activeFilters.formats.join(',') + '&' +
			                  'p=' + value + '&' +
			                  'type=' +
			                  activeFilters.type + '&' +
			                  's=' + activeFilters.search;
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
			<List className="itemList__root">
				{items.items.map((item, index) => (
					// "isNew" assumes the items are sorted by creation date descendingly.
					<Item key={item.title} item={item} isNew={index === 0}/>
				))}
			</List>
			{1 < items.pages && (
				<Box className="talks__paginationContainer" paddingY={1}>
					<Pagination
						classes={{ul: classes.ul}}
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
