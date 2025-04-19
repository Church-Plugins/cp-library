import React, { useState, useEffect, useLayoutEffect, useRef } from 'react';
import Box from '@mui/material/Box';
import { cplVar, cplLog, cplMarker, isURL } from '../../utils/helpers';
import PlayerWrapper from '../PlayerWrapper';
import useBreakpoints from '../../Hooks/useBreakpoints';
import { usePersistentPlayer } from '../../Contexts/PersistentPlayerContext';
import Logo from '../../Elements/Logo';
import PictureInPicture from "@mui/icons-material/PictureInPicture";
import Forward30 from "@mui/icons-material/Forward30";
import Replay10 from "@mui/icons-material/Replay10";
import OpenInFull from "@mui/icons-material/OpenInFull";
import PlayCircleOutline from "@mui/icons-material/PlayCircleOutline";
import Slider from '@mui/material/Slider';
import IconButton from '@mui/material/IconButton';
import CircularProgress from '@mui/material/CircularProgress';
import ReactDOM from 'react-dom';
import screenfull from 'screenfull';
import formatDuration from '../../utils/formatDuration';
import PlayPause from '../../Elements/Buttons/PlayPause';
import throttle from 'lodash.throttle';
import Controls from './Controls';
import api from '../../api';
import useListenerRef from '../../Hooks/useListenerRef';

export default function Player({ item }) {
  const { isDesktop } = useBreakpoints();
  const [isPlaying, setIsPlaying] = useState(false);
  const [hasPlayed, setHasPlayed] = useState(false);
	const [loading, setLoading] = useState(true);
  const [playedSeconds, setPlayedSeconds] = useState(0.0);
  const [duration, setDuration] = useState(0.0);
  const [playbackRate, setPlaybackRate] = useState(1 );
	const [displayBg, setDisplayBG]   = useState( {backgroundColor: "#C4C4C4"} );
	const [showFSControls, setShowFSControls]   = useState( false );
  // Add new state for player loading/ready state
  const [loadingState, setLoadingState] = useState('initial'); // 'initial', 'loading', 'ready', 'playing'
  const playbackDetectionTimeout = useRef(null);

  // Add cleanup for timeouts
  useEffect(() => {
    return () => {
      if (playbackDetectionTimeout.current) {
        clearTimeout(playbackDetectionTimeout.current);
      }
    };
  }, []);
	const itemContainer = useRef(false);
  // Keep frequently-updated states (mainly the progress from the media player) as a ref so they
  // don't trigger re-render.
  const mediaState = useRef({});
  const { isActive: persistentPlayerIsActive } = usePersistentPlayer();
  const [playerInstance, setPlayerInstance] = useListenerRef(null, (value) => value && setLoading(false));
	const playingClass   = isPlaying ? ' is_playing' : '';
	const hasVariations = Boolean(item.variations?.length)
	const [currentItem, setCurrentItem] = useState(hasVariations ? item.variations[0] : item)
	const [currentMedia, setCurrentMedia] = useState(() => {
		const media = currentItem.video?.value || currentItem.audio;
		if( isURL( media ) ) {
			return ''
		}
		return media || ''
	});
	// Video, audio or embed
	const [mode, setMode] = useState(currentMedia && (isURL(currentMedia) ? false : 'embed'));

	const isSoundcloud = isURL( currentMedia ) && currentMedia.indexOf('https://soundcloud.com') === 0;

	const onMouseMove = (e) => {
		if (showFSControls || ! mode) return;
		showControls();
	};

	const showControls = () => {
		if ( ! isDesktop && ! screenfull.isFullscreen ) {
			return;
		}

		setShowFSControls( true );

		if ( 'video' === mode ) {
			setTimeout(() => setShowFSControls( false ), 4000 );
		}
	};

	useEffect(() => {
		if(mode) {
			showControls()
		}
	}, [mode]);

	const handleClickFullscreen = () => {
		cplLog(item.id, 'fullscreen');
		const instance = ReactDOM.findDOMNode(playerInstance.current);
		screenfull.request( instance.parentElement );
		return false;
	};

	const handleClickPersistent = () => {
		mediaState.current = { ...mediaState.current, item: currentItem };
		mediaState.current = { ...mediaState.current, mode: mode };
		mediaState.current = { ...mediaState.current, playedSeconds: playedSeconds };

		setIsPlaying( false );
		setMode( false );
		setShowFSControls( false );

		api.passToPersistentPlayer({
			item         : mediaState.current.item,
			mode         : mediaState.current.mode,
			isPlaying    : true,
			playedSeconds: mediaState.current.playedSeconds,
		});
	};

	const updateItemState = ({ url, ...data }) => {
		// If it's audio, always use persistent player
		if (data.mode === 'audio') {
			api.passToPersistentPlayer({
				...data,
				url: url
			});
			return;
		}

		if(persistentPlayerIsActive) {
			if(isURL( url )) {
				api.passToPersistentPlayer( data )
			}
			else {
				api.closePersistentPlayer()
				updateMode( data.mode, url )
			}
		} else {
			updateMode( data.mode, url )
		}
	}

	const updateMode = (mode, url = null) => {
		// If it's audio, send to persistent player instead of local player
		if (mode === 'audio') {
			api.passToPersistentPlayer({
				item: currentItem,
				mode: 'audio',
				url: url || currentItem.audio,
				isPlaying: true,
				playedSeconds: 0.0,
			});
			return;
		}

		// Set loading state first
		setLoadingState('loading');

		// Execute existing initialization
		setMode(mode);
		setPlayedSeconds(0);
		// Only use video media for local player
		setCurrentMedia( url || currentItem.video.value );
		setIsPlaying(false);

		// Instead of automatically setting isPlaying=true, start detection
		clearTimeout(playbackDetectionTimeout.current);

		// Use a tracking variable outside the closure to prevent stale state
		const detectionId = Math.random(); // Unique ID for this detection cycle
		window._playbackDetectionId = detectionId;

		// Set a timeout to check if playback started
		playbackDetectionTimeout.current = setTimeout(() => {
			console.log("Playback detection timeout fired, checking if playing");

			// Only proceed if this is still the current detection cycle
			if (window._playbackDetectionId === detectionId) {
				// If we're still in loading state, switch to ready
				setLoadingState('ready');
				setIsPlaying(false); // Ensure player is paused
				console.log("Switching to ready state, player initialized but not playing");
			}
		}, 2500); // 2.5 seconds should be sufficient

		// Use requestAnimationFrame for more reliable timing
		// For non-iOS, automatically attempt to play
		if (!navigator.userAgent.match(/iPad|iPhone|iPod/)) {
			requestAnimationFrame(() => {
				setIsPlaying(true);
			});
		}
	};

	const updatePlaybackRate = () => {
		switch( playbackRate ) {
			case 1:
				setPlaybackRate(1.25);
				break;
			case 1.25:
				setPlaybackRate(1.5);
				break;
			case 1.5:
				setPlaybackRate(2);
				break;
			default:
				setPlaybackRate(1);
				break;
		}
	};

	// log initial play
	useEffect(() => {
		if ( hasPlayed || ! isPlaying ) {
			return;
		}

		cplLog(item.id, 'play');
		setHasPlayed(true);
	}, [isPlaying])

	useEffect(() => {
		if(!loading) {
			if(typeof playerInstance?.current.getInternalPlayer?.()?.play === 'function') {
				playerInstance.current.getInternalPlayer().play();
			} else {
				setIsPlaying(true);
			}
		}
	}, [loading])

  // Sync some states to be possibly passed to the persistent player. These states could be gone by
  // the time the clean up function is done during unmounting.
  useEffect(() => {
    mediaState.current = { ...mediaState.current, item: currentItem, mode };
  }, [item, currentItem, mode])

  // When unmounted, if media is still playing, hand it off to the persistent player.
  useLayoutEffect(() => {
    return () => {
      if (mediaState.current.isPlaying) {
        api.passToPersistentPlayer({
          item: mediaState.current.item,
          mode: mediaState.current.mode,
          isPlaying: true,
          playedSeconds: mediaState.current.playedSeconds,
        });
      }
    }
  }, [])

  useEffect(() => {
    if ( item?.thumb ) {
    	setDisplayBG( { background: "url(" + item.thumb + ")", backgroundSize: "cover", backgroundPosition: "center center" } );
    }
  }, [item]);

	let marker = cplMarker( item, mode, duration );
	let markPosition	= marker.position;
	let snapDiff		= marker.snapDistance;
	let videoMarks	= marker.marks;


	let doScroll = ( scrollValue ) => {
		if( markPosition > 0 && Math.abs( (scrollValue - markPosition) ) < snapDiff ) {
			setPlayedSeconds( markPosition );
		} else {
			setPlayedSeconds( scrollValue );
		}
	}

	let throttleScroll = throttle(
		(scrollValue) => {
			doScroll( scrollValue );
		}, 10
	);

	const shouldDisplayThumbnail = (
		( mode === false ) ||
		( !currentMedia )  ||
		( mode === 'audio' && ! isSoundcloud ) ||
		// Show thumbnail for pre-initialized players that aren't yet playing
		(loadingState === 'ready')
	)

  return (
    // Margin bottom is to account for audio player. Making sure all content is still visible with
    // the player is up.
    <Box ref={itemContainer} className={"itemDetail__root" + playingClass}>
        <Box className="itemDetail__rightContent">
          {/* TODO: Componentize as <FeatureImage />. These could be the same thing as the ones in the item list */}

          <div className="cpl-touch-hide" dangerouslySetInnerHTML={{__html: cplVar( 'mobileTop', 'components' ) }} />

						<Box
							className="itemDetail__featureImage"
							position="relative"
							paddingTop="56.26%"
							backgroundColor={mode === "audio" ? "#C4C4C4" : "transparent"}
						>
							{
								isURL(currentMedia) ?
								<>
								<Box className="itemPlayer__video"
										position="absolute"
										top={0}
										left={0}
										width="100%"
										height="100%"
										onMouseMove={onMouseMove}
								>
									{/* Add loading indicator when in loading state */}
										{loadingState === 'loading' && (
											<Box
												sx={{
													position: 'absolute',
													top: 0,
													left: 0,
													width: '100%',
													height: '100%',
													display: 'flex',
													alignItems: 'center',
													justifyContent: 'center',
													backgroundColor: 'rgba(0,0,0,0.5)',
													zIndex: 30
												}}
											>
												<CircularProgress sx={{ color: 'white' }} />
											</Box>
										)}

										{mode && currentItem &&
										<PlayerWrapper
											key={`${mode}-${currentItem.id}`}
											mode={mode}
											item={currentItem}
											ref={setPlayerInstance}
											className="itemDetail__video"
											url={currentMedia}
											width="100%"
											height="100%"
											controls={false}
											playbackRate={playbackRate}
											playing={isPlaying}
											onPlay={() => {
												console.log("Player started playing");
												setIsPlaying(true);
												setLoadingState('playing');
												// Clear any pending detection timeout
												clearTimeout(playbackDetectionTimeout.current);
												// Reset detection ID to invalidate any pending checks
												window._playbackDetectionId = null;
											}}
											onPause={() => setIsPlaying(false)}
											onDuration={duration => {
												setDuration(duration);
												playerInstance.current.seekTo(playedSeconds, 'seconds');
												setIsPlaying(true);
											}}
											onProgress={progress => setPlayedSeconds(progress.playedSeconds)}
											progressInterval={100}
										/>
									}

									{!mode ? null : (
										<Box
											className="itemPlayer__video__playWrap"
											onClick={() => {
												if (loadingState === 'ready') {
													// If player is initialized but not playing, just set playing state
													setIsPlaying(true);
													setLoadingState('playing');
												} else {
													// Otherwise toggle play state
													setIsPlaying(!isPlaying);
												}
											}}
										></Box>
									)}

									<Box className="itemPlayer__controlsWrapper cpl-touch-hide" style={{ display: showFSControls ? undefined : 'none' }}>

										<Box className="itemPlayer__controls" display="flex" flexDirection="row"
												justifyContent="space-around" margin="auto">

											<Box display="flex" alignItems="center">
												<PlayPause
													autoFocus
													isLoading={isPlaying && playedSeconds == 0}
													flex={0}
													padding={2}
													isPlaying={isPlaying}
													circleIcon={false}
													onClick={() => {
														if (loadingState === 'ready') {
															// If player is initialized but not playing, just set playing state
															setIsPlaying(true);
															setLoadingState('playing');
														} else {
															// Otherwise toggle play state
															setIsPlaying(!isPlaying);
														}
													}}
												/>
											</Box>

											<IconButton
												onClick={() => playerInstance.current.seekTo(playedSeconds - 10, 'seconds')}
												aria-label='Back 10 seconds'
											>
												<Replay10 fontSize="inherit"/>
											</IconButton>

											<IconButton
												onClick={() => playerInstance.current.seekTo(playedSeconds + 30, 'seconds')}
												aria-label='Skip 30 seconds'
											>
												<Forward30 fontSize="inherit"/>
											</IconButton>

											<Box className="itemPlayer__controls__rate" display="flex" alignItems="center"
													onClick={updatePlaybackRate}>
												<span>{playbackRate}x</span>
											</Box>

										</Box>

										<Box className="itemPlayer__progress" flex={1} display="flex" flexDirection="column">
											<Box display="flex" flexDirection="row" alignItems="center">

												<Slider
													min={0}
													defaultValue={0}
													max={duration}
													step={.01}
													size="medium"
													value={playedSeconds}
													sx={{padding: '10px 0 !important'}}
													marks={videoMarks}
													onChange={(_, value) => {
														setIsPlaying(false);

														if (_ && _.type && 'mousedown' === _.type) {
															setPlayedSeconds(value);
															playerInstance.current.seekTo(playedSeconds);
														} else {
															throttleScroll(value);
														}

													}}
													onChangeCommitted={(_, value) => {
														setIsPlaying(false);
														setTimeout(
															() => {
																setPlayedSeconds(value);
																playerInstance.current.seekTo(playedSeconds);
																setIsPlaying(true);
															}
														);
													}}
												/>

												<Box
													display="flex"
													className="itemPlayer__remaining"
												>
													{formatDuration(duration - playedSeconds)}
												</Box>
											</Box>

										</Box>

										{!screenfull.isFullscreen && (
											<Box className="itemPlayer__controls" display="flex" flexDirection="row"
												justifyContent="space-around" margin="auto">
												{mode === 'video' && (
														<IconButton onClick={handleClickFullscreen} aria-label="Open in fullscreen"><OpenInFull/></IconButton>
												)}

												<IconButton sx={{ transform: 'scaley(-1)'}} onClick={handleClickPersistent} aria-label="Open in persistent player"><PictureInPicture fontSize="inherit"/></IconButton>
											</Box>
										)}

									</Box>
								</Box>
								</> :
								<Box
									className='itemDetail__audio'
									display='flex'
									alignItems='center'
									justifyContent='center'
									height='100%'
									width='100%'
									position='absolute'
									top={0}
									left={0}
									dangerouslySetInnerHTML={{ __html: currentMedia.replace(/\\\"/g, '"') }} />
							}
							{/* Display thumbnail when playing audio except for soundcloud links */}
							{shouldDisplayThumbnail && (
									<Box
										className="itemDetail__audio"
										sx={displayBg}
										display="flex"
										alignItems="center"
										justifyContent="center"
										height="100%"
										width="100%"
										position="absolute"
										top={0}
										left={0}
									>
										{item.thumb ? (
											<>
												{isPlaying ? (
													<></>
												) : (
													<>
														{currentItem.video?.value ? (
															<PlayCircleOutline onClick={(e) => {
																// Only allow video playback with this control
																if (persistentPlayerIsActive) {
																	api.passToPersistentPlayer({
																		item         : mediaState.current.item,
																		mode         : 'video',
																		isPlaying    : true,
																		playedSeconds: 0.0,
																	});
																} else if (loadingState === 'ready') {
																	// If player is initialized but not playing, just set playing state
																	setIsPlaying(true);
																	setLoadingState('playing');
																} else {
																	// First click - initialize player
																	updateMode('video');
																}
															}} sx={{fontSize: 75}}/>
														) : null}
													</>
												)}
											</>
										) : (
											<Logo/>
										)}
									</Box>
								)}
						</Box>
					{
						hasVariations ?
						item.variations
						.filter(variation => (
							!!variation.audio || !!variation.video?.value || variation.speakers?.length
						))
						.map((variation) => {
							return (
								<Controls
									item={ variation }
									key={ variation.id }
									isVariation={true}
									handleSelect={ (data) => {
										setCurrentItem( variation )
										updateItemState( data )
									} }
								/>
							)
						})
						:
						<Controls item={item} handleSelect={(data) => {
							updateItemState(data)
						}} />
					}

	        {mode && (
	         <Box className="itemPlayer__controlsWrapper cpl-touch-only">
		         {/* Timeline at the top with no gap */}
		         <Box className="timeline-container">
			         <Box className="timeline-slider">
				         <Slider
					         min={0}
					         defaultValue={0}
					         max={duration}
					         step={.01}
					         size="small"
					         value={playedSeconds}
					         marks={videoMarks}
					         aria-label="Seek to position in playback"
					         onChange={(_, value) => {
						         setIsPlaying(false);

						         // Slider clicked
						         if (_ && _.type && 'mousedown' === _.type) {
							         setPlayedSeconds(value);
							         playerInstance.current.seekTo(playedSeconds);
						         } else { // Slider dragged/otherwise moved
							         throttleScroll(value);
						         }
					         }}
					         onChangeCommitted={(_, value) => {
						         // Execute synchronously to maintain iOS gesture chain
						         setPlayedSeconds(value);
						         playerInstance.current.seekTo(value); // Use 'value' directly
						         setIsPlaying(true);
					         }}
				         />
			         </Box>
		         </Box>

		         {/* Time and controls row */}
		         <Box className="time-controls-row">
			         <Box className="title-container">
				         <Box className="logo-container"><Logo/></Box>
				         <Box className="title-text">
					         <a href={currentItem.permalink} dangerouslySetInnerHTML={{__html: currentItem.title}}></a>
				         </Box>
			         </Box>

			         <Box className="time-display">
				         <span className="time-current">{formatDuration(playedSeconds)}</span>
				         <span className="time-separator"> / </span>
				         <span className="time-total">{formatDuration(duration)}</span>
			         </Box>

		         </Box>

		         {/* Container for controls */}
		         <Box className="controls-content">
			         {/* Player controls */}
			         <Box className="player-controls">
				         <Box
					         className="speed-control"
					         onClick={updatePlaybackRate}
				         >
					         {playbackRate}×
				         </Box>

				         <IconButton
					         onClick={() => playerInstance.current.seekTo(playedSeconds - 10, 'seconds')}
					         aria-label="Back 10 seconds"
					         size="small"
				         >
					         <Replay10 fontSize="small"/>
				         </IconButton>

				         <IconButton
					         onClick={() => playerInstance.current.seekTo(playedSeconds + 30, 'seconds')}
					         aria-label="Skip 30 seconds"
					         size="small"
				         >
					         <Forward30 fontSize="small"/>
				         </IconButton>

				         <PlayPause
					         autoFocus
					         isLoading={isPlaying && playedSeconds == 0}
					         flex={0}
					         padding={2}
					         isPlaying={isPlaying}
					         onClick={() => {
						         if (loadingState === 'ready') {
							         // If player is initialized but not playing, just set playing state
							         setIsPlaying(true);
							         setLoadingState('playing');
						         } else {
							         // Otherwise toggle play state
							         setIsPlaying(!isPlaying);
						         }
					         }}
				         />

				         {mode === 'video' && (
					         <IconButton
						         className="fullscreen-button"
						         onClick={handleClickFullscreen}
						         aria-label="Open in fullscreen"
						         size="small"
					         >
						         <OpenInFull fontSize="small"/>
					         </IconButton>
				         )}
				         <IconButton
					         className="persistent-player-button"
					         onClick={handleClickPersistent}
					         aria-label="Open in persistent player"
					         size="small"
					         sx={{transform: 'scaley(-1)'}}
				         >
					         <PictureInPicture fontSize="small"/>
				         </IconButton>
			         </Box>
		         </Box>
	         </Box>

	        )}

        </Box>
    </Box>
  );
}
