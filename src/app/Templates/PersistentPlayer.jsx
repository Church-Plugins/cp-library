import { useState, useEffect, useRef, useMemo, useCallback } from '@wordpress/element';
import Box from '@mui/material/Box';
import PlayerWrapper from '../Components/PlayerWrapper';
import Slider from '@mui/material/Slider';
import IconButton from '@mui/material/IconButton';
import screenfull from 'screenfull';

import Cancel from '@mui/icons-material/Cancel';
import Forward30 from '@mui/icons-material/Forward30';
import Replay10 from '@mui/icons-material/Replay10';
import OpenInFull from '@mui/icons-material/OpenInFull';
import VolumeOff from '@mui/icons-material/VolumeOff';
import VolumeUp from '@mui/icons-material/VolumeUp';
import useBreakpoints from '../Hooks/useBreakpoints';
import formatDuration from '../utils/formatDuration';
import { cplLog, cplMarker } from '../utils/helpers';

import ErrorDisplay from '../Elements/ErrorDisplay';
import PlayPause from '../Elements/Buttons/PlayPause';
import Logo from '../Elements/Logo';
import throttle from 'lodash.throttle';
import api from '../api';
import useListenerRef from '../Hooks/useListenerRef';

export default function PersistentPlayer (props) {
	const {isDesktop} = useBreakpoints();
	const [item, setItem] = useState(props.item);
	const [loading, setLoading] = useState(true);
	const [isPlaying, setIsPlaying] = useState(false);
	const [playedSeconds, setPlayedSeconds] = useState(0.0);
	const [playbackRate, setPlaybackRate] = useState(1);
	const [error, setError] = useState();
	const [duration, setDuration] = useState(0.0);
	const [showFSControls, setShowFSControls] = useState(false);
	// Video or audio
	const [mode, setMode] = useState();
	const [supportsPlaybackRate, setSupportsPlaybackRate] = useState(true);
	const [playerInstance, setPlayerInstance] = useListenerRef(null, (value) => value && setLoading(false));
	const [userInteractionToken, setUserInteractionToken] = useState(null);
	const [isMutedPlayback, setIsMutedPlayback] = useState(false);
	const [showMutedNotice, setShowMutedNotice] = useState(false);
	const [audioUnlocked, setAudioUnlocked] = useState(false);

	// Check if this is iOS
	const isIOS = useRef(/iPad|iPhone|iPod/.test(navigator.userAgent) ||
	                   (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1));

	const onMouseMove = (e) => {
		if (showFSControls) {
			return;
		}
		showControls();
	};

	const showControls = () => {
		if (!screenfull.isFullscreen) {
			return;
		}

		setShowFSControls(true);
		setTimeout(() => setShowFSControls(false), 4000);
	};

	const updatePlaybackRate = () => {
		switch (playbackRate) {
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
				
				setIsMutedPlayback(false);
				setShowMutedNotice(false);
				setAudioUnlocked(true);
			}
			// For HTML5 video/audio players
			else if (internalPlayer.muted !== undefined) {
				internalPlayer.muted = false;
				internalPlayer.volume = 1.0;
				
				setIsMutedPlayback(false);
				setShowMutedNotice(false);
				setAudioUnlocked(true);
			}
		} catch (e) {
			// Silently continue
		}
	};

	const handleClickFullscreen = () => {
		cplLog(item.id, 'fullscreen');
		api.openInFullscreen();
		return false;
	};

	const desktopClass = isDesktop ? ' is_desktop' : '';

	useEffect(() => {
		api.triggerEvent('CPL_PERSISTENT_PLAYER_MOUNTED', {item});

		// Handle toggle play message from Actions component
		const handleTogglePlay = (e) => {
			if (e.data && e.data.action === 'CPL_TOGGLE_PLAY') {
				setIsPlaying(!isPlaying);
			}
		};
		
		// Add listener for toggle play event
		window.top.addEventListener('message', handleTogglePlay);

		// For iOS devices, set up a direct document-level touch handler
		// This helps maintain audio permissions throughout the session
		if (isIOS.current) {
			// Function to handle unmuting when user interacts with the page
			const handleIOSInteraction = (e) => {
				// Only handle once we have a player instance
				if (playerInstance?.current) {
					// Try to unmute the player
					const internalPlayer = playerInstance.current.getInternalPlayer();
					if (internalPlayer) {
						if (typeof internalPlayer.unMute === 'function') {
							internalPlayer.unMute();

							if (typeof internalPlayer.setVolume === 'function') {
								internalPlayer.setVolume(100);
							}
						}
					}
				}
			};

			// Add event listeners for various user interactions
			document.addEventListener('touchstart', handleIOSInteraction, {once: true});
			document.addEventListener('click', handleIOSInteraction, {once: true});

			// Clean up event listeners on unmount
			return () => {
				document.removeEventListener('touchstart', handleIOSInteraction);
				document.removeEventListener('click', handleIOSInteraction);
				window.top.removeEventListener('message', handleTogglePlay);
				api.triggerEvent('CPL_PERSISTENT_PLAYER_UNMOUNTED');
			};
		}

		return () => {
			window.top.removeEventListener('message', handleTogglePlay);
			api.triggerEvent('CPL_PERSISTENT_PLAYER_UNMOUNTED');
		};
	}, [isPlaying]);

	// Seek to the correct position when the player is ready
	useEffect(() => {
		if (!loading && playerInstance.current) {
			playerInstance.current.seekTo(playedSeconds);
		}
	}, [loading]);
	
	// Monitor for iOS muted playback
	useEffect(() => {
		// Only check for iOS devices and only when playing
		if (isIOS.current && isPlaying && !showMutedNotice) {
			// Set a timer to check if the audio/video is actually playing with sound
			const checkTimer = setTimeout(() => {
				if (!playerInstance.current) return;
				
				const internalPlayer = playerInstance.current.getInternalPlayer();
				
				// Check if audio is muted on YouTube
				if (internalPlayer && typeof internalPlayer.isMuted === 'function') {
					const isMuted = internalPlayer.isMuted();
					if (isMuted) {
						
						setShowMutedNotice(true);
						setIsMutedPlayback(true);
					} else {
						// Audio is working
						setAudioUnlocked(true);
					}
				}
				// For HTML5 video/audio
				else if (internalPlayer && internalPlayer.muted !== undefined) {
					if (internalPlayer.muted) {
						
						setShowMutedNotice(true);
						setIsMutedPlayback(true);
					} else {
						// Audio is working
						setAudioUnlocked(true);
					}
				}
			}, 500); // Check after 500ms
			
			return () => clearTimeout(checkTimer);
		}
	}, [isPlaying, showMutedNotice]);

	// Store received video/audio URL for use in PlayerWrapper
	const [mediaUrl, setMediaUrl] = useState(null);
	
	useEffect(() => {
		function handleMessage (data) {
				// Received handover message

			// Capture token immediately before any state updates
			let token = data.userInteractionToken;

			// Check for previously stored token (set in API)
			if (!token && window._activeUserInteractionToken) {
				token = window._activeUserInteractionToken;
			}
			
			// Store URL if provided
			if (data.url) {
				setMediaUrl(data.url);
			}
			
			// Special handling for iOS devices
			if (data.isIOS) {
				// For iOS, we need to ensure the interaction context is preserved
				// and that the player elements are fully initialized
				setTimeout(() => {
					// Force a refresh of the loading state to ensure DOM is ready
					setLoading(true);
				}, 0);
			}

			// Process essential data immediately
			setItem(data.item);
			setMode(data.mode);
			setPlayedSeconds(data.playedSeconds);
			setIsPlaying(data.playedSeconds > 0 ? false : data.isPlaying);

			// Store the user interaction token
			if (token) {
				setUserInteractionToken(token);
			}

			// We do all state updates together to avoid multiple renders
			// The useEffect triggered by these state changes will handle player initialization
		}

		api.listen('CPL_HANDOVER_TO_PERSISTENT', handleMessage);

		return () => {
			api.removeListener('CPL_HANDOVER_TO_PERSISTENT', handleMessage);
		};
	}, []);

	useEffect(() => {
		if (!loading && playerInstance?.current) {
			// Use the user interaction token if available
			if (userInteractionToken && window._cplUserInteractions && window._cplUserInteractions[userInteractionToken]) {
				// This is crucial - execute this code synchronously within the same user interaction flow
				const internalPlayer = playerInstance.current.getInternalPlayer();

				// Handle YouTube videos specifically
				if (internalPlayer && typeof internalPlayer.playVideo === 'function') {
					// Simple sequence for both iOS and non-iOS (but especially critical for iOS):
					// 1. Unmute - must happen first and synchronously

					if (typeof internalPlayer.unMute === 'function') {
						internalPlayer.unMute();
					}

					// 2. Set volume to max
					if (typeof internalPlayer.setVolume === 'function') {

						internalPlayer.setVolume(100);
					}

					// 3. Play the video immediately after unmuting

					internalPlayer.playVideo();

					// For iOS only: Follow up with requestAnimationFrame
					if (isIOS.current) {
						requestAnimationFrame(() => {
							if (internalPlayer) {
								// Double check mute state
								if (typeof internalPlayer.isMuted === 'function' && internalPlayer.isMuted()) {
									internalPlayer.unMute();
								}
								// Make sure it's playing
								internalPlayer.playVideo();
							}
						});
					}
				}
				// Handle HTML5 video elements
				else if (internalPlayer && typeof internalPlayer.play === 'function') {
					internalPlayer.muted = false;
					internalPlayer.volume = 1.0;
					// Use the Promise-based API for HTML5 video
					const playPromise = internalPlayer.play();
					if (playPromise !== undefined) {
						playPromise.catch(error => {
							// Auto-play with sound was prevented - fallback to muted;
							// Fall back to muted autoplay which is more widely supported
							internalPlayer.muted = true;
							internalPlayer.play();
						});
					}
				} else {
					// Default behavior for other player types
					setIsPlaying(true);
				}
			} else {
				// Default behavior without user interaction token
				const internalPlayer = playerInstance.current.getInternalPlayer();

				// For non-iOS browsers, ensure sound is unmuted
				if (!isIOS.current) {
					// Handle YouTube
					if (internalPlayer && typeof internalPlayer.unMute === 'function') {
						internalPlayer.unMute();
					}
					// Handle HTML5 video
					else if (internalPlayer && typeof internalPlayer.play === 'function') {
						internalPlayer.muted = false;
					}
				}

				// Continue with playback
				if (typeof playerInstance?.current.getInternalPlayer?.()?.play === 'function') {
					playerInstance.current.getInternalPlayer().play();
				} else {
					setIsPlaying(true);
				}
			}
		}
	}, [loading, userInteractionToken]);

	useEffect(() => {
		// Since item and mode are different pieces of state that are set separately, we wait until both
		// are set.
		if (!item || !mode) {
			return;
		}

		if (!window.top.document.body.classList.contains('cpl-persistent-player')) {
			window.top.document.body.classList.add('cpl-persistent-player');
		}

		api.triggerEvent('CPL_PERSISTENT_RECEIVED_ITEM', {item, mode});
	}, [item, mode]);

	let marker = cplMarker(item, mode, duration);
	let markPosition = marker.position;
	let snapDiff = marker.snapDistance;
	let videoMarks = marker.marks;

	const doScroll = (scrollValue) => {
		if (markPosition > 0 && Math.abs((
			scrollValue - markPosition
		)) < snapDiff) {
			setPlayedSeconds(markPosition);
		} else {
			setPlayedSeconds(scrollValue);
		}
	};

	const throttleScroll = throttle(doScroll, 10);

	return error ? (
		<ErrorDisplay error={error}/>
	) : item ? (
		<Box className={'persistentPlayer__root persistentPlayer__mode__' + mode + desktopClass}>

			{mode === 'video' &&
			 <Box className="persistentPlayer__video">
				 <Box className="itemPlayer__video"
				      position="absolute"
				      top={0}
				      left={0}
				      width="100%"
				      height="100%"
				      onMouseMove={onMouseMove}
				 >
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
					 
					 <PlayerWrapper
						 key={`${mode}-${item.id}`}
						 mode={mode}
						 item={item}
						 ref={setPlayerInstance}
						 className="itemDetail__video"
						 url={mediaUrl || (item.video && item.video.value)}
						 width="100%"
						 height="100%"
						 controls={false}
						 playing={!loading && isPlaying}
						 playbackRate={playbackRate}
						 userInteractionToken={userInteractionToken}
						 onPlay={() => { 
							 setIsPlaying(true); 
							 setIsMutedPlayback(false);
							 setShowMutedNotice(false);
						 }}
						 onPause={() => setIsPlaying(false)}
						 onMutedPlayback={(isMuted) => { 
							 setIsMutedPlayback(isMuted);
							 // Show notification for iOS users when video is muted
							 if (isMuted && isIOS.current) {
								 setShowMutedNotice(true);
							 }
							 setIsPlaying(false); 
						 }}
						 onDuration={duration => {
							 setDuration(duration);
							 playerInstance.current.seekTo(playedSeconds, 'seconds');
							 setIsPlaying(true);
						 }}
						 onProgress={progress => setPlayedSeconds(progress.playedSeconds)}
						 progressInterval={100}
					 />

					 <Box className="persistentPlayer__video__controls">
						 {!screenfull.isFullscreen && (
							 <Box
								className="video-clickable-overlay"
								onClick={() => setIsPlaying(!isPlaying)}
							 />
						 )}
					 </Box>

					 {screenfull.isFullscreen && (
						 <Box className="itemPlayer__video__playWrap" onClick={() => setIsPlaying(!isPlaying)}></Box>
					 )}

					 {!screenfull.isFullscreen ? null : (
						 <Box className="itemPlayer__controlsWrapper cpl-touch-hide"
						      style={{display: showFSControls ? undefined : 'none'}}>

							 <Box className="itemPlayer__controls" display="flex" flexDirection="row"
							      justifyContent="space-around" margin="auto">

								 <Box display="flex" alignItems="center">
									 <PlayPause autoFocus isLoading={isPlaying && playedSeconds == 0} flex={0} padding={2}
									            isPlaying={isPlaying} circleIcon={false}
									            onClick={() => setIsPlaying(!isPlaying)}/>
								 </Box>

								 <IconButton
									 onClick={() => playerInstance.current.seekTo(playedSeconds - 10, 'seconds')}
									 aria-label="Back 10 seconds">
									 <Replay10 fontSize="inherit"/>
								 </IconButton>

								 <IconButton
									 onClick={() => playerInstance.current.seekTo(playedSeconds + 30, 'seconds')}
									 aria-label="Skip 30 seconds">
									 <Forward30 fontSize="inherit"/>
								 </IconButton>

								 {supportsPlaybackRate && (
									<Box className="itemPlayer__controls__rate" display="flex" alignItems="center"
										onClick={updatePlaybackRate}>
										<span>{playbackRate}x</span>
									</Box>
								 )}

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
										 aria-label="Seek to position in playback"
										 onChange={(_, value) => {
											 setIsPlaying(false);
											 setPlayedSeconds(value);
										 }}
										 onChangeCommitted={(_, value) => {
											 setIsPlaying(true);
											 playerInstance.current.seekTo(value);
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
									 {mode === 'video' && isDesktop && (
										 <IconButton onClick={handleClickFullscreen}><OpenInFull/></IconButton>
									 )}
								 </Box>
							 )}

						 </Box>
					 )}

				 </Box>
			 </Box>
			}

			<Box className="persistentPlayer__controls">
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
							<a href={item.permalink} dangerouslySetInnerHTML={{__html: item.title}}></a>
						</Box>
					</Box>

					<Box className="time-display">
						<span className="time-current">{formatDuration(playedSeconds)}</span>
						<span className="time-separator"> / </span>
						<span className="time-total">{formatDuration(duration)}</span>
					</Box>

					<Box className="action-buttons">
						{false && mode === 'video' && ( // disable fullscreen for persistent player
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
							className="close-button"
							onClick={api.closePersistentPlayer}
							aria-label="Close persistent player"
							size="small"
						>
							<Cancel fontSize="small"/>
						</IconButton>
					</Box>

				</Box>

				{/* Container for title/close and controls */}
				<Box className="controls-content">
					{/* Title row with integrated player controls */}
					<Box className="title-row">

						{/* Player controls - will be positioned based on screen size */}
						<Box className="player-controls">
								{supportsPlaybackRate && (
									<Box
										className="speed-control"
										onClick={updatePlaybackRate}
									>
										{playbackRate}×
									</Box>
								)}

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
									onClick={() => setIsPlaying(!isPlaying)}
								/>

						</Box>

					</Box>
				</Box>

				{mode === 'audio' &&
				 <Box>
					 {showMutedNotice && (
						 <Box
							 className="muted-playback-notification"
							 onClick={handleUnmuteOnIOS}
							 sx={{
								 position: 'fixed',
								 bottom: '5rem',
								 left: '50%',
								 transform: 'translateX(-50%)',
								 backgroundColor: 'rgba(0,0,0,0.8)',
								 color: 'white',
								 padding: '0.75rem',
								 borderRadius: '0.5rem',
								 textAlign: 'center',
								 zIndex: 35,
								 cursor: 'pointer',
								 display: 'flex',
								 alignItems: 'center',
								 gap: '0.5rem'
							 }}
						 >
							 <VolumeOff fontSize="small" />
							 <span>Tap to enable sound</span>
						 </Box>
					 )}
					 
					 <PlayerWrapper
						 key={`${mode}-${item.id}`}
						 mode={mode}
						 item={item}
						 ref={setPlayerInstance}
						 controls={false}
						 url={mediaUrl || item.audio}
						 width="0"
						 height="0"
						 playing={!loading && isPlaying}
						 playbackRate={playbackRate}
						 userInteractionToken={userInteractionToken}
						 onPlay={() => { 
							 setIsPlaying(true); 
							 setIsMutedPlayback(false);
							 setShowMutedNotice(false);
						 }}
						 onPause={() => setIsPlaying(false)}
						 onMutedPlayback={(isMuted) => { 
							 setIsMutedPlayback(isMuted);
							 // Show notification for iOS users when audio is muted
							 if (isMuted && isIOS.current) {
								 setShowMutedNotice(true);
							 }
							 setIsPlaying(false); 
						 }}
						 onDuration={duration => {
							 setDuration(duration);
							 if (playedSeconds > 0) {
								 playerInstance.current.seekTo(playedSeconds, 'seconds');
								 setIsPlaying(true);
							 }
						 }}
						 onProgress={progress => setPlayedSeconds(progress.playedSeconds)}
						 progressInterval={100}
					 />
				 </Box>
				}
			</Box>
		</Box>
	) : null;
}