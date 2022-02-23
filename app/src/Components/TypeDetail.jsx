import React, { useState, useEffect, useRef } from 'react';
import Box from '@mui/material/Box';
import Divider from '@mui/material/Divider';
import ItemList from "./ItemList";
import Menu from '@mui/material/Menu';
import MenuItem from '@mui/material/MenuItem';
import { cplVar } from '../utils/helpers';

import { Play, Volume1, Share2 } from "react-feather"
import VideoPlayer from "react-player";
import { Link } from 'react-router-dom';

import useBreakpoints from '../Hooks/useBreakpoints';
import Controllers_WP_REST_Request from '../Controllers/WP_REST_Request';
import { usePersistentPlayer } from '../Contexts/PersistentPlayerContext';

import LoadingIndicator from '../Elements/LoadingIndicator';
import ErrorDisplay from '../Elements/ErrorDisplay';
import TypeMeta from './TypeMeta';
import SearchInput from '../Elements/SearchInput';
import RectangularButton from '../Elements/RectangularButton';
import Logo from '../Elements/Logo';

import { PictureInPicture, Forward30, Replay10, Fullscreen, PlayCircleOutline, Facebook, Twitter, Download, Link as LinkIcon } from "@mui/icons-material"
import Slider from '@mui/material/Slider';
import IconButton from '@mui/material/IconButton';
import ReactDOM from 'react-dom';
import screenfull from 'screenfull';

import formatDuration from '../utils/formatDuration';
import ButtonPlay from '../Elements/ButtonPlay';


export default function TypeDetail({
  typeId,
}) {
  const { isDesktop } = useBreakpoints();
  const [item, setItem] = useState();
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState();
  // Video or audio
  const [mode, setMode] = useState(false);
  const [isPlaying, setIsPlaying] = useState(false);
  const [playedSeconds, setPlayedSeconds] = useState(0.0);
  const [duration, setDuration] = useState(0.0);
  const [playingURL, setPlayingURL] = useState('');
  const [playbackRate, setPlaybackRate] = useState(1 );
	const [displayBg, setDisplayBG]   = useState( {backgroundColor: "#C4C4C4"} );
	const [showFSControls, setShowFSControls]   = useState( false );

  // Keep frequently-updated states (mainly the progress from the media player) as a ref so they
  // don't trigger re-render.
  const mediaState = useRef({});
  const { isActive: persistentPlayerIsActive, passToPersistentPlayer } = usePersistentPlayer();
  const playerInstance = useRef();
	const playingClass   = isPlaying ? ' is_playing' : '';
  const [anchorEl, setAnchorEl] = useState(null);
  const open = Boolean(anchorEl);
	const copyLinkRef = useRef(null);

	const onMouseMove = (e) => {
		if (showFSControls || ! screenfull.isFullscreen ) return;

		setShowFSControls( true );
		setTimeout(() => setShowFSControls( false ), 3500 );
	};

  const handleClick = (event) => {
    setAnchorEl(event.currentTarget);
  };

  const handleClose = () => {
    setAnchorEl(null);
  };

	const handleClickFullscreen = () => {
		const instance = ReactDOM.findDOMNode(playerInstance.current);
		screenfull.request( instance.parentElement );
		return false;
	};

	const handleClickPersistent = () => {
		mediaState.current = { ...mediaState.current, item: item };
		mediaState.current = { ...mediaState.current, mode: mode };
		mediaState.current = { ...mediaState.current, playedSeconds: playedSeconds };

		setIsPlaying( false );
		setMode( null );
		passToPersistentPlayer({
			item         : mediaState.current.item,
			mode         : mediaState.current.mode,
			isPlaying    : true,
			playedSeconds: mediaState.current.playedSeconds,
		});
	};

	const handleFBShare = () => {
		window.open('http://www.facebook.com/sharer.php?u='+encodeURIComponent( item.permalink )+'&t='+encodeURIComponent( item.title ),'sharer','toolbar=0,status=0,width=626,height=436');
    setAnchorEl(null);
	};

	const handleTwitterShare = () => {
		window.open( "http://twitter.com/intent/tweet?text=" + encodeURIComponent( item.title + ' ' + item.permalink ),'sharer','toolbar=0,status=0,width=626,height=436');
    setAnchorEl(null);
	};

	const handleFileDownload = () => {
		const link = document.createElement('a');
    link.href = item.audio;
    link.setAttribute(
      'download',
      item.title.replace(/[^a-z0-9]/gi, '_').toLowerCase() + '.mp3',
    );

		link.setAttribute(
			'target',
			'_blank',
		);

    // Append to html link element page
    document.body.appendChild(link);

    // Start download
    link.click();

    // Clean up and remove the link
    link.parentNode.removeChild(link);
    setAnchorEl(null);
	};

	const handleCopyLink = (e) => {
		copyLinkRef.current.select();
		document.execCommand('copy');
		e.target.focus();
    setAnchorEl(null);
	};

	const updateMode = (mode) => {
		setMode(mode);
		setPlayedSeconds(0);
		setPlayingURL( 'video' === mode ? item.video.value : item.audio );
		setIsPlaying(false)
		setIsPlaying(true);
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

  // Fetch the individual item when mounted.
  useEffect(() => {
    (async () => {
      try {
        setLoading(true);
        const restRequest = new Controllers_WP_REST_Request();
        const data = await restRequest.get( {endpoint: `types/${typeId}`} );
        setItem(data);
      } catch (error) {
        setError(error);
      } finally {
        setLoading(false);
      }
    })();
  }, []);

  // Sync some states to be possibly passed to the persistent player. These states could be gone by
  // the time the clean up function is done during unmounting.
  useEffect(() => {
    mediaState.current = { ...mediaState.current, item, mode };
  }, [item, mode])

  // When unmounted, if media is still playing, hand it off to the persistent player.
  useEffect(() => {
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

  // If item has both video and audio, prefer video.
  useEffect(() => {
    if (!item) return;

    if ( item.thumb ) {
    	setDisplayBG( { background: "url(" + item.thumb + ")", backgroundSize: "cover" } );
    }

  }, [item]);

  return loading ? (
    <LoadingIndicator />
  ) : error ? (
    <ErrorDisplay error={error} />
  ) : (
    // Margin bottom is to account for audio player. Making sure all content is still visible with
    // the player is up.
    <Box className={"typeDetail__root" + playingClass}>
      <Link to={"/" + cplVar( 'slug', 'type' )}>{"<"} Back to {cplVar( 'labelPlural', 'type' )}</Link>
      {false && isDesktop && (
        <>
          <Box display="flex" justifyContent="space-between">
            <h1 className="typeDetail__header">{cplVar( 'labelPlural', 'item' )}</h1>
            {/* TODO: Think about who's responsible for search, e.g. here or a global search provider */}
            <Box className="typeDetail__search" marginLeft={1} display="flex" alignItems="center">
              <SearchInput onValueChange={console.log} />
            </Box>
          </Box>
          <Divider className="typeDetail__divider" sx={{ marginY: 2 }} />
        </>
      )}

      <h1 className="typeDetail__title" dangerouslySetInnerHTML={{ __html: item.title }} />
      {isDesktop ? (
        <>
          <Box className="typeDetail__itemMeta" marginTop={4}>
            <TypeMeta date={item.date.date} items={item.items} />
          </Box>

          <Box className="typeDetail__description" marginTop={4} dangerouslySetInnerHTML={{ __html: item.desc }} />
        </>
      ) : (
        <Divider
          className="typeDetail__divider typeDetail__shortDivider"
          sx={{ width: 58, height: 6, marginY: 2 }}
        />
      )}

      {isDesktop ? null : (
        <Box className="typeDetail__description" marginTop={2} dangerouslySetInnerHTML={{ __html: item.desc }} />
      )}

      <ItemList activeFilters={{type:item.id, topics: [], formats: [], page: 1, search: ''}} />

    </Box>
  );
}
