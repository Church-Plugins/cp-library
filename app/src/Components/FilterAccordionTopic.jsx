import React, { useState, useEffect } from "react";
import Typography from '@mui/material/Typography';
import FormGroup from '@mui/material/FormGroup';
import FormControlLabel from '@mui/material/FormControlLabel';
import Checkbox from '@mui/material/Checkbox';
import { noop } from "../utils/noop";
import IconButton from '@mui/material/IconButton';
import Box from '@mui/material/Box';
import Grid from '@mui/material/Grid';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import ErrorDisplay from "./ErrorDisplay";
import Divider from '@mui/material/Divider';
import Portal from '@mui/material/Portal';
import LoadingIndicator from "./LoadingIndicator";
import Controllers_WP_REST_Request from '../Controllers/WP_REST_Request';
import Button from '@mui/material/Button';

// TODO: Refactor; There's a lot of repeated code between here and FilterTopic.jsx

export default function FilterAccordionTopic({
	onFilterChange = noop,
	activeFilters = noop,
	open = false,
	onClose = noop
}) {

	const [topicsFullItems, setTopicsFullItems] = useState([]);
	const [topicsFullLoading, setTopicsFullLoading] = useState( false );
	const [topicsFullError, setTopicsFullError] = useState();
	let [topicsFullLoaded, setTopicsFullLoaded] = useState( false );
	const [topicViewIsOpen, setTopicViewIsOpen] = useState( false )
	let refs = [];

	useEffect(() => {
		(async () => {

			try {
				setTopicsFullLoading( true );
				const restRequest = new Controllers_WP_REST_Request();
				const data = await restRequest.get( {endpoint: 'items/dictionary', params: null} );

				let outputItems = [];
				if( data && data.items ) {

					let keys = Object.keys( data.items );
					keys.forEach(
						(letter, index) => {
							if( data.items[letter] && data.items[letter].length > 0 ) {
								outputItems[ letter ] = data.items[letter]
							}
						}
					);

				}

				setTopicsFullItems( outputItems );
			} catch ( error ) {
				setTopicsFullError( error );
			} finally {
				setTopicsFullLoading( false );
				setTopicsFullLoaded( true );
			}

		})();
	}, [activeFilters]);

	const alphabet = [
		'A', 'B', 'C', 'D', 'E', 'F', 'G',
		'H', 'I', 'J', 'K', 'L', 'M', 'N',
		'O', 'P', 'Q', 'R', 'S', 'T', 'U',
		'V', 'W', 'X', 'Y', 'Z'
	];

	/**
	 * Generate usable internal document references
	 * @param String which 			Either 'mobile' or 'desktop'
	 *
	 * @returns void
	 */
	const createViewRefs = () => {
		let saveRefs = [];
		Object.keys( topicsFullItems ).map(
			(letter) => {
				const lower = letter.toLowerCase();
				saveRefs[lower] = React.createRef();
			}
		);
		saveRefs['end'] = React.createRef();
		refs = saveRefs;
	}

	createViewRefs();

	const NavView = () => {

		console.log( "NAV VIEW" );
		return (
			<>
				<Grid item xs={2} className="topic__column_back">
					<Box className="format__less">
						<IconButton onClick={() => setTopicViewIsOpen( false )} aria-label="Back">
							<ArrowBackIcon />
							<Typography className="less__label">BACK</Typography>
						</IconButton>
					</Box>
				</Grid>
				<Grid item xs={10} className="topic__column_back">
					<Box>
						{alphabet.map(
							(letter) => {
								const lower = letter.toLowerCase();
								const upper = letter.toUpperCase();

								return ( <Typography> {upper} |</Typography> )
							}
						)}
					</Box>
				</Grid>
			</>
		)

	}

	const MainView = () => {

		console.log( "MAN VIEW" );
		return (
			<>
				<Typography>CONTENT HERE</Typography>
			</>
		)

	}

	return (
		<>
			<Box className="filterAccordion__topic_container">
				<Grid container spacing={2} className="filterAccordion__topic_grid_header">
					<NavView />
					{topicsFullLoading && !topicsFullLoaded ? (
						<>
							<LoadingIndicator />
							{console.log( "LOADING" )}
						</>
					) : topicsFullError ? (
						<>
						<ErrorDisplay error={topicsFullError} />
						{console.log( "ERROR" )}
						</>
					) : (
						<>
						<Box>BODY</Box>
						{console.log( "MAIN" )}
						</>
					)}
				</Grid>
			</Box>
		</>
	)
}
