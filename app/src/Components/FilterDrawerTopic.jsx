import React, { useState, useEffect } from "react";
import Box from '@mui/material/Box';
import Typography from '@mui/material/Typography';
import Drawer from '@mui/material/Drawer';
import IconButton from '@mui/material/IconButton';
import Portal from '@mui/material/Portal';
import { XCircle } from 'react-feather';
import { noop } from "../utils/noop";
import Grid from '@mui/material/Grid';
import Link from '@mui/material/Link';
import ArrowBackIcon from '@mui/icons-material/ArrowBack';
import FormGroup from '@mui/material/FormGroup';
import FormControlLabel from '@mui/material/FormControlLabel';
import Checkbox from '@mui/material/Checkbox';
import async from 'async';
import $ from 'jquery';
import Button from '@mui/material/Button';

import LoadingIndicator from "./LoadingIndicator";
import ErrorDisplay from "./ErrorDisplay";
import Controllers_WP_REST_Request from '../Controllers/WP_REST_Request';

export default function FilterDrawerTopic({
  open = false,
  onClose = noop,
  onFilterChange = noop,
  activeFilters = noop,
  whichView = noop
}) {

	const [topicsItems, setTopicsItems] = useState([]);
	const [topicsLoading, setTopicsLoading] = useState( false );
	const [topicsError, setTopicsError] = useState();
	let [topicsLoaded, setTopicsLoaded] = useState( false );

	let mobileRefs = [];

	useEffect(() => {
		(async () => {

			try {
				setTopicsLoading( true );
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

				setTopicsItems( outputItems );
				// setItems( data );
			} catch ( error ) {
				setTopicsError( error );
			} finally {
				setTopicsLoading( false );
				setTopicsLoaded( true );
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
	 * Scroll to a ref in the DOM and perform UX alterations
	 *
	 * @param DomEvent event
	 * @param String letter 			The letter to which we are scrolling
	 * @returns void
	 */
	const executeScroll = ( event, letter ) => {

		let origin = $( event.target );
		$( '.toc__alph_select >button' ).removeClass( 'selected' );

		if( mobileRefs[letter] && mobileRefs[letter].current ) {
			mobileRefs[letter].current.scrollIntoView({behavior: "smooth", block: "start"});
		} else {
			mobileRefs['end'].current.scrollIntoView({behavior: "smooth", block: "start"});
		}

		$( origin ).addClass( 'selected' );
	}

	/**
	 * Provide the desktop view for All Topics
	 *
	 * @returns JSX
	 */
	const desktopView = () => {

		return topicsLoading && !topicsLoaded ? (
			<Portal>
				<LoadingIndicator />
			</Portal>
		) : topicsError ? (
			<Portal>
				<ErrorDisplay error={topicsError} />
			</Portal>
		) : (

			<Portal>

				<Box>
					<Typography>
						This is desktop
					</Typography>
				</Box>

			</Portal>
		);

	}

	/**
	 * Generate usable internal document references
	 * @param String which 			Either 'mobile' or 'desktop'
	 *
	 * @returns void
	 */
	const createViewRefs = ( which ) => {
		let saveRefs = [];
		Object.keys( topicsItems ).map(
			(letter) => {
				const lower = letter.toLowerCase();
				saveRefs[lower] = React.createRef();
			}
		);
		saveRefs['end'] = React.createRef();

		if( 'mobile' === which ) {
			mobileRefs = saveRefs;
		}
	}

	/**
	 * Provide the mobile view for All Topics
	 *
	 * @returns JSX
	 */
	const mobileView = () => {

		createViewRefs( 'mobile' );

		return topicsLoading && !topicsLoaded ? (
			<Portal>
				<LoadingIndicator />
			</Portal>
		) : topicsError ? (
			<Portal>
				<ErrorDisplay error={topicsError} />
			</Portal>
		) : (
			<Portal>
				<Drawer
					className="filterDrawer__popular"
					anchor="right"
					open={open}
					onClose={onClose}
					// So it shows on top of header/nav + another drawer that's beneath this one
					sx={{ zIndex: 6010 }}
					PaperProps={{ sx: { width: "100%" } }}
				>
					<Box display="flex" className="filterDrawer__header">
					<Box flex={1} className="filterDrawer__title">ALL TOPICS</Box>
					</Box>
					<Box className="filterDrawer__topic_container" sx={{ flexGrow: 1 }}>

						<Grid container spacing={2}>
							<Grid item xs={10} className="topic__column_left">
								<Grid container spacing={2}>

									<Grid item xs={12} className="format__less_container">
										<Box className="format__less">
											<IconButton onClick={onClose} aria-label="Back">
												<ArrowBackIcon />
												<Typography className="less__label">BACK</Typography>
											</IconButton>
										</Box>
									</Grid>

									{Object.keys( topicsItems ).map(
										(letter, index) => {
											const lower = letter.toLowerCase();
											const upper = letter.toUpperCase();

											let loopRef = mobileRefs[letter];

											return (
												<Grid className="topic__letter_ref" ref={loopRef} item xs={12}>
													<Box id={`letter__${lower}`} className="topic__letter_header" >
														{upper}
													</Box>
													<Box className="topic__letter_item" >
														<FormGroup>
														{topicsItems[ letter ].map(
															(item, itemIndex) => {

																return <FormControlLabel
																			className="topic__letter_item_label"
																			control={
																				<Checkbox
																					value={item.slug}
																					onChange={() => onFilterChange( item.slug )} />
																			}
																					label={item.name}
																					checked={activeFilters && activeFilters.topics && activeFilters.topics.includes( item.slug )} />
															}
														)}
														</FormGroup>
														<Box ref={mobileRefs['end']} />
													</Box>

												</Grid>
											);
										}
									)}
								</Grid>
							</Grid>
							<Grid item xs={2} className="topic__column_right">
								{alphabet.map(
									(letter, index) => {
										let lowerLetter = letter.toLowerCase();
										return (
											<Box className={`toc__alph_select select__${lowerLetter}`} >
												<Button
													className="toc__alph_select_button"
													onClick={(event) => { executeScroll( event, lowerLetter ); }}
												>
													{letter}
												</Button>
											</Box>
										)
									}
								)}
							</Grid>
						</Grid>
					</Box>
				</Drawer>
			</Portal>
		);

	}

	return ('mobile' === whichView) ? mobileView() : desktopView();

}
