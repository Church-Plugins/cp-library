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

	let refs = [];

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

	const executeScroll = ( event ) => {

		let origin = $( event.target );
		let letter = $( origin ).attr( 'data-goto' );

		let targetString = 'letter__' + letter;
		let target = $( '#' + targetString );

		console.log( "WILL SCROLL TO" );
		console.log( targetString );
		console.log( $( target ) );

		$('html, body').animate({
			scrollTop: $( target ).offset().top
		}, 500 );
	}

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

	const mobileView = () => {

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
											return (
												<Grid item xs={12}>
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
																					onChange={() => onFilterChange( item.slug )} />}
																					label={item.name}
																					checked={activeFilters && activeFilters.topics && activeFilters.topics.includes( item.slug )
																				} />
															}
														)}
														</FormGroup>
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
										return <Box
											className={`toc__alph_select select__${lowerLetter}`}
										>
												<Link
													className="filterDrawer__alph_link"
													underline="none"
													data-goto={lowerLetter}
													onClick={executeScroll}
													href={`#letter_${lowerLetter}`}
												>
													{letter}
												</Link>
										</Box>
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
