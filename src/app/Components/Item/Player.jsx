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
import VolumeOff from "@mui/icons-material/VolumeOff";
import VolumeUp from "@mui/icons-material/VolumeUp";
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
  const [playbackRate, setPlaybackRate] = useState(1);
	const [displayBg, setDisplayBG]   = useState( {backgroundColor: "#C4C4C4"} );
	const [showFSControls, setShowFSControls]   = useState( false );
  // Add new state for player loading/ready state
  const [loadingState, setLoadingState] = useState('initial'); // 'initial', 'loading', 'ready', 'playing'
  const [isMuted, setIsMuted] = useState(false); // Track muted state
  const [showMutedNotice, setShowMutedNotice] = useState(false); // Show notification for iOS users about audio
  const [audioUnlocked, setAudioUnlocked] = useState(false); // Track if audio is unlocked on iOS

  const playbackDetectionTimeout = useRef(null);

  // Check if running on iOS for special audio handling and fullscreen API compatibility
  const isIOS = useRef(/iPad|iPhone|iPod/.test(navigator.userAgent) ||
                 (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1));

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
		// Check if screenfull is properly initialized
		if (!screenfull || typeof screenfull.isFullscreen !== 'boolean') {
			// If screenfull isn't available, always show controls on videos
			if ('video' === mode) {
				setShowFSControls(true);
				setTimeout(() => setShowFSControls(false), 4000);
			}
			return;
		}

		// Otherwise use normal fullscreen detection
		if (!isDesktop && !screenfull.isFullscreen) {
			return;
		}

		setShowFSControls(true);

		if ('video' === mode) {
			setTimeout(() => setShowFSControls(false), 4000);
		}
	};

	useEffect(() => {
		if(mode) {
			showControls()
		}
	}, [mode]);

	const handleClickFullscreen = (e) => {
		if (e) {
			e.preventDefault();
			e.stopPropagation();
		}

		cplLog(item.id, 'fullscreen');

		try {
			// First check if we can detect YouTube specifically
			// YouTube has its own fullscreen mechanism that works more reliably
			if (playerInstance.current) {
				const internalPlayer = playerInstance.current.getInternalPlayer();

				// Check if it's a YouTube player
				const isYouTube = internalPlayer &&
					(typeof internalPlayer.getVideoUrl === 'function' ||
					(internalPlayer.src && internalPlayer.src.toString().includes('youtube')));

				if (isYouTube) {
					// For YouTube, try to call the YouTube-specific API
					if (typeof internalPlayer.getIframe === 'function') {
						const iframe = internalPlayer.getIframe();

						// Send a postMessage to enable fullscreen
						if (iframe && iframe.contentWindow) {
							iframe.contentWindow.postMessage('{"event":"command","func":"requestFullscreen","args":""}', '*');
							return;
						}
					}

					// Alternative YouTube method
					if (typeof internalPlayer.setSize === 'function') {
						// Get document dimensions
						const width = window.innerWidth || document.documentElement.clientWidth || document.body.clientWidth;
						const height = window.innerHeight || document.documentElement.clientHeight || document.body.clientHeight;

						// Set player to full window size
						internalPlayer.setSize(width, height);
						return;
					}
				}
			}

			// If YouTube-specific methods failed or it's not a YouTube player, try screenfull
			if (screenfull && screenfull.isEnabled) {
				// Get the video container element
				const instance = ReactDOM.findDOMNode(playerInstance.current);
				const videoContainer = instance ? instance.closest('.itemDetail__featureImage') || instance.parentElement : null;

				if (videoContainer) {
					// Request fullscreen on the video container
					screenfull.request(videoContainer).catch(err => {
						console.error('Fullscreen request failed:', err);
					});
					return;
				} else if (instance) {
					// Fallback to the instance itself
					screenfull.request(instance).catch(err => {
						console.error('Fullscreen request failed:', err);
					});
					return;
				}
			} else {
				// If screenfull isn't available

				// Try native fullscreen API for HTML5 video
				if (playerInstance.current) {
					const internalPlayer = playerInstance.current.getInternalPlayer();

					// For HTML5 video element
					if (internalPlayer && typeof internalPlayer.requestFullscreen === 'function') {
						internalPlayer.requestFullscreen().catch(err => {
							console.error('Native fullscreen request failed:', err);
						});
						return;
					}

					// iOS Safari specific method
					if (internalPlayer && typeof internalPlayer.webkitEnterFullscreen === 'function') {
						try {
							internalPlayer.webkitEnterFullscreen();
							return;
						} catch (err) {
							console.error('WebKit fullscreen failed:', err);
						}
					}

					// Alternative webkit method
					if (internalPlayer && typeof internalPlayer.webkitRequestFullscreen === 'function') {
						try {
							internalPlayer.webkitRequestFullscreen();
							return;
						} catch (err) {
							console.error('WebKit request fullscreen failed:', err);
						}
					}
				}

				console.warn('Fullscreen is not supported in this browser');
			}
		} catch (error) {
			console.error('Error entering fullscreen:', error);
		}
	};

	const handleClickPersistent = () => {
		// Check if the persistent player is already active with this video playing
		if (api.playerIsActive()) {
			// Get the current player DOM node
			const playerNode = window.top.document.getElementById('cpl_persistent_player');

			// Only toggle play/pause if this is the same video currently playing
			if (playerNode && playerNode.querySelector('.title-text a')?.innerHTML === currentItem.title) {
				// Just toggle play/pause instead of opening a new player
				const event = new CustomEvent('message');
				event.data = {
					action: 'CPL_TOGGLE_PLAY',
					item: currentItem
				};
				window.top.dispatchEvent(event);
				return;
			}
		}

		// Check if this is iOS - using the same method as PersistentPlayer
		const isIOSDevice = /iPad|iPhone|iPod/.test(navigator.userAgent) ||
			(navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

		// Create a user interaction token to maintain iOS audio permissions
		const userInteractionToken = Date.now().toString();

		// Store it globally so it can be accessed when the persistent player initializes
		if (isIOSDevice) {
			// Create a global interaction registry if it doesn't exist
			if (!window._cplUserInteractions) {
				window._cplUserInteractions = {};
			}

			// Store this interaction context
			window._cplUserInteractions[userInteractionToken] = true;

			// For iOS, attempt to unlock audio context first if we have a player
			if (playerInstance.current && typeof playerInstance.current.getInternalPlayer === 'function') {
				const internalPlayer = playerInstance.current.getInternalPlayer();

				// Try to unmute first - necessary for iOS
				if (internalPlayer) {
					// For YouTube
					if (typeof internalPlayer.unMute === 'function') {
						try {
							internalPlayer.unMute();
							if (typeof internalPlayer.setVolume === 'function') {
								internalPlayer.setVolume(100);
							}
						} catch (e) {
							// Silently continue
						}
					}
					// For HTML5 video/audio
					else if (internalPlayer.muted !== undefined) {
						try {
							internalPlayer.muted = false;
							internalPlayer.volume = 1.0;

							// For iOS Safari, sometimes a quick play/pause can help unlock audio permissions
							if (typeof internalPlayer.play === 'function') {
								const playPromise = internalPlayer.play();
								if (playPromise !== undefined) {
									playPromise.then(() => {
										setTimeout(() => {
											if (typeof internalPlayer.pause === 'function') {
												internalPlayer.pause();
											}
										}, 50);
									}).catch(() => {
										// Ignore play errors - we're just trying to unlock audio
									});
								}
							}
						} catch (e) {
							// Silently continue
						}
					}
				}
			}

			// Clean up old interaction tokens after a while
			setTimeout(() => {
				if (window._cplUserInteractions && window._cplUserInteractions[userInteractionToken]) {
					delete window._cplUserInteractions[userInteractionToken];
				}
			}, 5000);
		}

		// Store the current state to be used when handing over to persistent player
		mediaState.current = {
			...mediaState.current,
			item: currentItem,
			mode: mode,
			playedSeconds: playedSeconds,
			url: mode === 'video' ? currentItem.video.value : currentItem.audio,
			audioUnlocked: audioUnlocked || !isMuted // Pass along audio permission state
		};

		// For iOS devices, we need to delay cleaning up the local player
		// until after we pass to the persistent player to maintain the user interaction context
		if (!isIOSDevice) {
			// Clean up local player (non-iOS)
			setIsPlaying(false);
			setMode(false);
			setShowFSControls(false);
		}

		// Pass to persistent player with complete information
		api.passToPersistentPlayer({
			item: mediaState.current.item,
			mode: mediaState.current.mode,
			isPlaying: true,
			playedSeconds: mediaState.current.playedSeconds,
			url: mediaState.current.url,
			isIOS: isIOSDevice,
			userInteractionToken: userInteractionToken,
			audioUnlocked: mediaState.current.audioUnlocked
		});

		// For iOS devices, clean up the local player after a slight delay
		// This helps maintain the user interaction context for autoplay
		if (isIOSDevice) {
			setTimeout(() => {
				setIsPlaying(false);
				setMode(false);
				setShowFSControls(false);
			}, 100); // Slightly longer delay to ensure proper handover
		}
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


			// Only proceed if this is still the current detection cycle
			if (window._playbackDetectionId === detectionId) {
				// If we're still in loading state, switch to ready
				setLoadingState('ready');
				setIsPlaying(false); // Ensure player is paused

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

	// Function to manually unmute on iOS
	const handleUnmuteOnIOS = () => {
		if (!playerInstance.current) return;

		// Get internal player
		const internalPlayer = playerInstance.current.getInternalPlayer();

		if (!internalPlayer) return;

		try {
			// For YouTube players
			if (typeof internalPlayer.unMute === 'function') {
				internalPlayer.unMute();

				if (typeof internalPlayer.setVolume === 'function') {
					internalPlayer.setVolume(100);
				}

				setIsMuted(false);
				setShowMutedNotice(false);
				setAudioUnlocked(true);
			}
			// For HTML5 video/audio players
			else if (internalPlayer.muted !== undefined) {
				internalPlayer.muted = false;
				internalPlayer.volume = 1.0;

				setIsMuted(false);
				setShowMutedNotice(false);
				setAudioUnlocked(true);
			}
		} catch (e) {
			// Silently continue
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

  // Monitor for iOS muted playback
  useEffect(() => {
    // Only check for iOS devices and only when playing video
    if (isIOS.current && isPlaying && mode === 'video' && !showMutedNotice) {
      // Set a timer to check if the video is actually playing with sound
      const checkTimer = setTimeout(() => {
        if (!playerInstance.current) return;

        const internalPlayer = playerInstance.current.getInternalPlayer();

        // Check if audio is muted on YouTube
        if (internalPlayer && typeof internalPlayer.isMuted === 'function') {
          const isMuted = internalPlayer.isMuted();
          if (isMuted) {

            setShowMutedNotice(true);
            setIsMuted(true);
          } else {
            // Audio is working
            setAudioUnlocked(true);
          }
        }
        // For HTML5 video
        else if (internalPlayer && internalPlayer.muted !== undefined) {
          if (internalPlayer.muted) {

            setShowMutedNotice(true);
            setIsMuted(true);
          } else {
            // Audio is working
            setAudioUnlocked(true);
          }
        }
      }, 500); // Check after 500ms

      return () => clearTimeout(checkTimer);
    }
  }, [isPlaying, mode, showMutedNotice]);

  // When unmounted, if media is still playing, hand it off to the persistent player.
  useLayoutEffect(() => {
    return () => {
      if (mediaState.current.isPlaying) {
        api.passToPersistentPlayer({
          item: mediaState.current.item,
          mode: mediaState.current.mode,
          isPlaying: true,
          playedSeconds: mediaState.current.playedSeconds,
          audioUnlocked: audioUnlocked // Pass audio state
        });
      }
    }
  }, [audioUnlocked])

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
							paddingTop={ screenfull.isFullscreen ? 0 : "56.26%" }
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

										{/* Add muted notification for iOS users */}
										{showMutedNotice && (
											<Box
												className="muted-playback-notification"
												onClick={handleUnmuteOnIOS}
												sx={{
													position: 'absolute',
													top: '50%',
													left: '50%',
													transform: 'translate(-50%, -50%)',
													backgroundColor: 'rgba(0,0,0,0.8)',
													color: 'white',
													padding: '1rem',
													borderRadius: '0.5rem',
													textAlign: 'center',
													zIndex: 35,
													cursor: 'pointer',
													maxWidth: '80%',
													display: 'flex',
													flexDirection: 'column',
													alignItems: 'center',
													gap: '0.5rem'
												}}
											>
												<VolumeOff sx={{ fontSize: 32 }} />
												<span>Tap here to enable sound</span>
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

												setIsPlaying(true);
												setLoadingState('playing');
												// Clear any pending detection timeout
												clearTimeout(playbackDetectionTimeout.current);
												// Reset detection ID to invalidate any pending checks
												window._playbackDetectionId = null;
												// Reset muted notification when playing begins
												setShowMutedNotice(false);
											}}
											onPause={() => setIsPlaying(false)}
											onDuration={duration => {
												setDuration(duration);
												playerInstance.current.seekTo(playedSeconds, 'seconds');
												setIsPlaying(true);
											}}
											onProgress={progress => setPlayedSeconds(progress.playedSeconds)}
											onMutedPlayback={(isMuted) => {
												// Show notification for iOS users when video is muted
												setIsMuted(isMuted);
												if (isMuted && isIOS.current) {
													setShowMutedNotice(true);
												}
											}}
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
												{mode === 'video' && !isIOS.current && (
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

				         {mode === 'video' && !isIOS.current && (
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
