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

	const handleClickFullscreen = () => {
		cplLog(item.id, 'fullscreen');
		api.openInFullscreen();
		return false;
	};

	const desktopClass = isDesktop ? ' is_desktop' : '';

	useEffect(() => {
		api.triggerEvent('CPL_PERSISTENT_PLAYER_MOUNTED', {item});

		return () => {
			api.triggerEvent('CPL_PERSISTENT_PLAYER_UNMOUNTED');
		};
	}, []);

	// Seek to the correct position when the player is ready
	useEffect(() => {
		if (!loading && playerInstance.current) {
			playerInstance.current.seekTo(playedSeconds);
		}
	}, [loading]);

	useEffect(() => {
		function handleMessage (data) {
			setItem(data.item);
			setMode(data.mode);
			setPlayedSeconds(data.playedSeconds);
			setIsPlaying(data.playedSeconds > 0 ? false : data.isPlaying);

			if (!playerInstance.current) {
				setLoading(true);
			}
		}

		api.listen('CPL_HANDOVER_TO_PERSISTENT', handleMessage);

		return () => {
			api.removeListener('CPL_HANDOVER_TO_PERSISTENT', handleMessage);
		};
	}, []);

	useEffect(() => {
		if (!loading) {
			if (typeof playerInstance?.current.getInternalPlayer?.()?.play === 'function') {
				playerInstance.current.getInternalPlayer().play();
			} else {
				setIsPlaying(true);
			}
		}
	}, [loading]);

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
					 <PlayerWrapper
						 key={`${mode}-${item.id}`}
						 mode={mode}
						 item={item}
						 ref={setPlayerInstance}
						 className="itemDetail__video"
						 url={item.video.value}
						 width="100%"
						 height="100%"
						 controls={false}
						 playing={!loading && isPlaying}
						 playbackRate={playbackRate}
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
											 playerInstance.current.seekTo(playedSeconds);
											 setPlayedSeconds(value);
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
								setIsPlaying(false);
								setTimeout(
									() => {
										setPlayedSeconds(value);
										playerInstance.current.seekTo(playedSeconds);
										setIsPlaying(true);
									}, 5
								);
							}}
						/>
					</Box>
				</Box>

				{/* Time and controls row */}
				<Box className="time-controls-row">
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
						<Box className="title-container">
							<Box className="logo-container"><Logo/></Box>
							<Box className="title-text">
								<a href={item.permalink} dangerouslySetInnerHTML={{__html: item.title}}></a>
							</Box>
						</Box>

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
					 <PlayerWrapper
						 key={`${mode}-${item.id}`}
						 mode={mode}
						 item={item}
						 ref={setPlayerInstance}
						 controls={false}
						 url={item.audio}
						 width="0"
						 height="0"
						 playing={!loading && isPlaying}
						 playbackRate={playbackRate}
						 onPlay={() => setIsPlaying(true)}
						 onPause={() => setIsPlaying(false)}
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
