import React, { useState, useEffect, useLayoutEffect, useRef } from 'react';
import Box from '@mui/material/Box';
import Menu from '@mui/material/Menu';
import MenuItem from '@mui/material/MenuItem';
import { cplVar, cplLog, cplMarker, isURL } from '../../utils/helpers';

import { Play, Volume1, Share2 } from "react-feather"
import PlayerWrapper from '../PlayerWrapper';

import useBreakpoints from '../../Hooks/useBreakpoints';
import { usePersistentPlayer } from '../../Contexts/PersistentPlayerContext';

import Rectangular from '../../Elements/Buttons/Rectangular';
import Logo from '../../Elements/Logo';

import { PictureInPicture, Forward30, Replay10, OpenInFull, PlayCircleOutline, Facebook, Twitter, Download, Link as LinkIcon } from "@mui/icons-material"
import Slider from '@mui/material/Slider';
import IconButton from '@mui/material/IconButton';
import ReactDOM from 'react-dom';
import screenfull from 'screenfull';

import formatDuration from '../../utils/formatDuration';
import PlayPause from '../../Elements/Buttons/PlayPause';

import PlayAudio from '../../Elements/Buttons/PlayAudio';
import PlayVideo from '../../Elements/Buttons/PlayVideo';

import throttle from 'lodash.throttle';
import jQuery from 'jquery';
import Controls from './Controls';


export default function Player({
  item,
}) {
  const { isDesktop } = useBreakpoints();
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState();
  const [isPlaying, setIsPlaying] = useState(false);
  const [hasPlayed, setHasPlayed] = useState(false);
  const [playedSeconds, setPlayedSeconds] = useState(0.0);
  const [duration, setDuration] = useState(0.0);
  const [playbackRate, setPlaybackRate] = useState(1 );
	const [displayBg, setDisplayBG]   = useState( {backgroundColor: "#C4C4C4"} );
	const [showFSControls, setShowFSControls]   = useState( false );
	const itemContainer = useRef(false);
  // Keep frequently-updated states (mainly the progress from the media player) as a ref so they
  // don't trigger re-render.
  const mediaState = useRef({});
  const { isActive: persistentPlayerIsActive, passToPersistentPlayer, closePersistentPlayer } = usePersistentPlayer();
  const playerInstance = useRef();
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

  let progressIntervalHandle = null;

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
		
		passToPersistentPlayer({
			item         : mediaState.current.item,
			mode         : mediaState.current.mode,
			isPlaying    : true,
			playedSeconds: mediaState.current.playedSeconds,
		});
	};

	const updateItemState = ({ url, ...data }) => {

		if(persistentPlayerIsActive) {
			if(isURL( url )) {
				passToPersistentPlayer( data )
			}
			else {
				closePersistentPlayer()
				updateMode( data.mode, url )
			}
		} else {
			updateMode( data.mode, url )
		}
	}



	const updateMode = (mode, url = null) => {
		setMode(mode);
		setPlayedSeconds(0);
		setCurrentMedia( url || ('video' === mode ? currentItem.video.value : currentItem.audio) );
		setIsPlaying(false);

		// give the player a chance to initialize before we set it to play
		setTimeout(() => { setIsPlaying(true); }, 50 );
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

  // Sync some states to be possibly passed to the persistent player. These states could be gone by
  // the time the clean up function is done during unmounting.
  useEffect(() => {
    mediaState.current = { ...mediaState.current, item: currentItem, mode };
  }, [item, currentItem, mode])

  // When unmounted, if media is still playing, hand it off to the persistent player.
  useLayoutEffect(() => {
    return () => {
      if (mediaState.current.isPlaying) {
        passToPersistentPlayer({
          item: mediaState.current.item,
          mode: mediaState.current.mode,
          isPlaying: true,
          playedSeconds: mediaState.current.playedSeconds,
        });
      }
    }
  }, [])

  useEffect(() => {
    if ( currentItem?.thumb ) {
    	setDisplayBG( { background: "url(" + item.thumb + ")", backgroundSize: "cover", backgroundPosition: "center center" } );
    }
  }, [currentItem]);

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
		( mode === 'audio' && ! isSoundcloud )
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
									{
										mode && currentItem &&
										<PlayerWrapper
											key={`${mode}-${currentItem.id}`}
											mode={mode}
											item={currentItem}
											ref={playerInstance}
											className="itemDetail__video"
											url={currentMedia}
											width="100%"
											height="100%"
											controls={false}
											playbackRate={playbackRate}
											playing={isPlaying}
											onPlay={() => setIsPlaying(true)}
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
										<Box className="itemPlayer__video__playWrap" onClick={() => setIsPlaying(!isPlaying)}></Box>
									)}

									<Box className="itemPlayer__controlsWrapper cpl-touch-hide" style={{ display: showFSControls ? undefined : 'none' }}>

										<Box className="itemPlayer__controls" display="flex" flexDirection="row"
												justifyContent="space-around" margin="auto">

											<Box display="flex" alignItems="center">
												<PlayPause autoFocus playedSeconds={playedSeconds} flex={0} padding={2} isPlaying={isPlaying} circleIcon={false} onClick={() => setIsPlaying(!isPlaying)}/>
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
													<PlayCircleOutline onClick={() => {
														let defaultMode = ( currentItem.video.value ) ? 'video' : 'audio';

														if (persistentPlayerIsActive) {
															passToPersistentPlayer({
																item         : mediaState.current.item,
																mode         : defaultMode,
																isPlaying    : true,
																playedSeconds: 0.0,
															});
														} else {
															updateMode( defaultMode );
														}
													}} sx={{fontSize: 75}}/>
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
						item.variations.map((variation) => {
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
		         <Box className="itemPlayer__progress" flex={1} display="flex" flexDirection="column" >
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

			         </Box>
			         <Box className="itemPlayer__duration" display="flex" flexDirection="row" justifyContent="space-between">
				         <Box
					         display="flex"
					         justifyContent="flex-start"
				         >
					         {formatDuration(playedSeconds)}
				         </Box>
				         <Box
					         display="flex"
					         justifyContent="flex-end"
				         >
					         -{formatDuration(duration - playedSeconds)}
				         </Box>
			         </Box>
		         </Box>

		         <Box className="itemPlayer__controls" display="flex" flexDirection="row"
		              justifyContent="space-around" margin="auto">

			         <Box className="itemPlayer__controls__rate" display="flex" alignItems="center" onClick={updatePlaybackRate}>
				         <span>{playbackRate}x</span>
			         </Box>

			         <IconButton size="large" onClick={() => playerInstance.current.seekTo(playedSeconds - 10, 'seconds')} aria-label='Back 10 seconds'>
				         <Replay10 fontSize="inherit"/>
			         </IconButton>

			         <Box display="flex" alignItems="center">
				         <PlayPause autoFocus playedSeconds={playedSeconds} isPlaying={isPlaying} onClick={() => setIsPlaying(!isPlaying)}/>
			         </Box>
			         <IconButton size='large' onClick={() => playerInstance.current.seekTo(playedSeconds + 30, 'seconds')} aria-label='Skip 30 seconds'>
				         <Forward30 fontSize="inherit"/>
			         </IconButton>
			         <IconButton size="large" sx={{transform: 'scaley(-1)'}}
			                     onClick={handleClickPersistent}><PictureInPicture fontSize="inherit"/></IconButton>

		         </Box>

	         </Box>

	        )}

        </Box>
    </Box>
  );
}
