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
import $ from 'jquery';

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

		/**
	 * Scroll to a ref in the DOM and perform UX alterations
	 *
	 * @todo When the target is not present, we're scrolling to the end. This is OK for RET
	 *         since all lettrs with no contnt are conincidentally at the end of the alphabet
	 * @param DomEvent event
	 * @param String letter 			The letter to which we are scrolling
	 * @returns void
	 */
	const executeScroll = ( event, letter ) => {

		event.preventDefault();
		event.stopPropagation();

		let origin = $( event.target );
		$( '.toc__alph_select_button' ).removeClass( 'selected' );

		if( refs[letter] && refs[letter].current ) {
			refs[letter].current.scrollIntoView({behavior: "smooth", block: "start"});
		} else {
			refs['end'].current.scrollIntoView({behavior: "smooth", block: "start"});
		}

		$( origin ).addClass( 'selected' );
	}

	const NavView = () => {

		// onClick={(event) => { executeScroll( event, lowerLetter ); }}

		return (
			<>
				<Grid item xs={2} className="topic__column_back">
					<Box className="format__less">
						<IconButton onClick={onClose} aria-label="Back">
							<ArrowBackIcon />
							<Typography className="less__label">BACK</Typography>
						</IconButton>
					</Box>
				</Grid>
				<Grid item xs={10} className="topic__column_nav">
					<Grid container spacing={2} className="topic__column_nav_container">
						{alphabet.map(
							(letter) => {
								const lower = letter.toLowerCase();
								const upper = letter.toUpperCase();

								return (
									<>
										<Button
											style={{maxWidth: '18px', minWidth: '18px', width: '18px'}}
											className="toc__alph_select_button"
											onClick={(event) => { executeScroll( event, lower ); }}
										>
											{upper}
										</Button>
										<Divider className="toc__alph_select_divider" orientation="vertical" variant="middle" flexItem />
									</>
								)
							}
						)}
					</Grid>
				</Grid>
			</>
		)

	}

	const ItemListView = ( {letter = null} ) => {

		const lower = letter.toLowerCase();
		const upper = letter.toUpperCase();

		const items = topicsFullItems[ lower ];
		if( !items || items.length < 1 ) {
			return ( <></> )
		}

		return (
			<>
				{items.map(
					(item, itemIndex) => {

						return <Grid
									className="topic__letter_item"
									item xs={3}
									id={`letter__item_container_${lower}`}
								>
									<FormControlLabel
										className="topic__letter_item_label"
										control={
											<Checkbox
												value={item.slug}
												onChange={() => onFilterChange( item.slug )}
											/>
										}
										label={item.name}
										checked={activeFilters && activeFilters.topics && activeFilters.topics.includes( item.slug )}
									/>
								</Grid>
					}
				)}
			</>
		)
	}

	const MainView = () => {

		return (
			<>
				<Grid container spacing={2} className="topic__letter_container">
					{Object.keys( topicsFullItems ).map(
						(letter, index) => {
							const lower = letter.toLowerCase();
							const upper = letter.toUpperCase();

							let loopRef = refs[lower];

							return (
								<>
									<Grid
										ref={loopRef}
										item xs={12}
										id={`letter__${lower}`}
										className="topic__letter_header topic__letter_scroll"
									>
										{upper}
									</Grid>
									<Grid
										item xs={12}
										id={`letter__container_${lower}`}
										className="topic__letter_item_list"
									>
										<Grid container spacing={2}>
											<ItemListView letter={lower}/>
										</Grid>
									</Grid>
								</>
							)
						} // {Object.keys( topicsFullItems ).map
					)}
				</Grid>
			</>
		)

	}

	return (
		<>
			<Box className="filterAccordion__topic_container" open={open}>
				<Grid container spacing={2} className="filterAccordion__topic_grid_header">
					<NavView />
					{topicsFullLoading && !topicsFullLoaded ? (
						<>
							<LoadingIndicator />
						</>
					) : topicsFullError ? (
						<>
							<ErrorDisplay error={topicsFullError} />
						</>
					) : (
						<>
							<MainView />
						</>
					)}
				</Grid>
			</Box>
		</>
	)
}
