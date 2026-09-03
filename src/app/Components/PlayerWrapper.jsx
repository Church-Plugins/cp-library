import { forwardRef, useEffect, useRef, useState } from "react";
import { cplLog, forceUnmuteVimeoPlayer } from "../utils/helpers";
import VideoPlayer from 'react-player'
import Cookies from 'js-cookie'

/**
 * @typedef {import('react-player').ReactPlayerProps} ReactPlayerProps
 */

/**
 * @typedef {ReactPlayerProps & {
 *  item: object
 *  mode: string
 *  userInteractionToken?: number
 *  forceAudio?: boolean
 *  onMutedPlayback?: (isMuted: boolean) => void
 * }} PlayerWrapperProps
 */

/**
 * Counts the number of truthy values in an array.
 *
 * @param {Uint32Array} arr
 * @returns {number}
 */
const countTruthy = (arr) => {
  let count = 0
  for(let i = 0; i < arr.length; i++) {
    if(arr[i]) count++
  }
  return count
}

/**
 * Wrapper for the VideoPlayer component that handles tracking of video views.
 *
 * @param {PlayerWrapperProps} props
 * @param {object} ref
 * @returns {React.ReactElement}
 */
function PlayerWrapper({ item, mode, userInteractionToken, forceAudio = false, ...props }, ref) {
  // The persistent player keeps one audio instance mounted from page load so
  // Safari can grant it playback permission before the first click, which means
  // this can render with no item. Nothing item-scoped (analytics, watch history)
  // applies until one is selected.
  const itemId     = item?.id ?? null
  const compoundId = `${mode}-${itemId}`
  // Same identity, readable from handlers registered once (beforeunload).
  const latest = useRef({ itemId, mode, compoundId })
  latest.current = { itemId, mode, compoundId }
  const viewedRef = useRef(false)
  const isEngagedRef = useRef(false)
  /** @type {{ current: Uint32Array|null }} */
  const watchData = useRef(null)
  /** @type {{ current: number|null  }} */
  const intervalRef = useRef(null)
  /** @type {{ current: number }} */
  const lastProgressPosition = useRef(0)
  /** @type {{ current: any }} */
  const playerRef = useRef(null)

  const [rand] = useState(Math.random)

  // Track first play
  const firstPlayRef = useRef(true);

  // Check if this is iOS
  const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) ||
               (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

  const handlePlay = () => {
    props.onPlay?.()

    // Get the internal player
    const internalPlayer = playerRef.current?.getInternalPlayer();

    // Only show special handling for iframe-based players on iOS
    // Check if this is a YouTube or Vimeo player (they use iframe and have special methods)
    const isIframePlayer = internalPlayer && (
      typeof internalPlayer.pauseVideo === 'function' || // YouTube
      typeof internalPlayer.getIframe === 'function' ||  // YouTube/Vimeo
      typeof internalPlayer.setMuted === 'function' ||   // Vimeo
      (internalPlayer.nodeName === 'IFRAME') // Generic iframe detection
    );

    // Force pause and show muted notice ONLY on iOS for first video play with iframe players
    if (firstPlayRef.current && mode === 'video' && internalPlayer && isIOS && isIframePlayer) {
      firstPlayRef.current = false;

      // Brief timeout to let the player start, then pause and show notice
      setTimeout(() => {
        // Pause the player
        if (typeof internalPlayer.pauseVideo === 'function') {
          internalPlayer.pauseVideo();
        } else if (typeof internalPlayer.pause === 'function') {
          internalPlayer.pause();
        }

        // Show notice about enabling audio
        if (props.onMutedPlayback) {
          props.onMutedPlayback(true);
        }
      }, 200);

      return; // Skip the rest on first play
    } else if (firstPlayRef.current) {
      // Mark as not first play for all other cases
      firstPlayRef.current = false;
    }

    if (playerRef.current && internalPlayer) {
      // For YouTube
      if (typeof internalPlayer.unMute === 'function') {
        internalPlayer.unMute();
        if (typeof internalPlayer.setVolume === 'function') {
          internalPlayer.setVolume(100);
        }
      }
      // For Vimeo
      else if (typeof internalPlayer.setMuted === 'function') {
        forceUnmuteVimeoPlayer(internalPlayer);
      }
      // For HTML5 video
      else if (internalPlayer.muted !== undefined) {
        internalPlayer.muted = false;
        internalPlayer.volume = 1.0;
      }
    }

    if(viewedRef.current || !mode || !itemId || intervalRef.current) return

    intervalRef.current = setTimeout(() => {
      viewedRef.current = true
      cplLog(itemId, mode + "_view")
    }, 30 * 1000) // TODO: should not be hardcoded
  }

  const handlePause = () => {
    props.onPause?.()

    clearTimeout(intervalRef.current)
    intervalRef.current = null
  }

  /** @param {number} duration */
  const handleDuration = (duration) => {
    props.onDuration?.(duration)

    watchData.current = new Uint32Array(Math.floor(duration))
  }

  /**
   * @param {import("react-player/base").OnProgressProps} data
   */
  const handleProgress = (data) => {
    props.onProgress?.(data)

    if(!watchData.current) return

    const currentSecond = Math.floor(data.playedSeconds)

    if(lastProgressPosition.current !== currentSecond) {
      // increments number of views at current second
      watchData.current[currentSecond]++
      lastProgressPosition.current = currentSecond
    }
  }

  /** @param {number} seconds */
  const handleSeek = (seconds) => {
    props.onSeek?.(seconds)
  }

  /** Handle playback rate changes */
  useEffect(() => {
    // Skip it if no player or playback rate
    if (!playerRef.current || !props.playbackRate) {
      return;
    }

    // Function to mark playback rate as not supported
    const markUnsupported = () => {
      if (props.onPlaybackRateSupported) {
        props.onPlaybackRateSupported(false);
      }
    };

    try {
      // First, determine if we're dealing with YouTube video
      // YouTube is known to have issues with playback rate for some videos
      const internalPlayer = playerRef.current.getInternalPlayer ? playerRef.current.getInternalPlayer() : null;
      const isYouTube = internalPlayer &&
                       (internalPlayer.getVideoUrl || // YouTube API method
                        (internalPlayer.src && internalPlayer.src.includes('youtube')));

      // For YouTube players, we need to check if playback rate is allowed for this specific video
      if (isYouTube) {
        try {
          // We need to wrap this in a Promise to handle YouTube API quirks
          // Use Promise constructor to catch all errors and prevent them from bubbling up
          new Promise((resolve, reject) => {
            try {
              // Try setting the playback rate
              if (internalPlayer.setPlaybackRate) {
                internalPlayer.setPlaybackRate(props.playbackRate);

                // If we got here, it worked
                if (props.onPlaybackRateSupported) {
                  props.onPlaybackRateSupported(true);
                }
                resolve();
              } else {
                markUnsupported();
                resolve(); // Resolve instead of reject to avoid uncaught promises
              }
            } catch (innerError) {
              // YouTube error for restricted videos
              markUnsupported();
              // Don't propagate the error, just handle it
              if (console && console.debug) {
                console.debug("Playback rate not supported for this video:", innerError.message);
              }
              resolve(); // Resolve the promise even though there was an error
            }
          }).catch((err) => {
            // Any error in the promise should mark as unsupported
            markUnsupported();
            // Log error but don't let it bubble up to console as an uncaught error
            if (console && console.debug) {
              console.debug("Error setting playback rate:", err);
            }
          });
        } catch (youtubeError) {
          markUnsupported();
        }
      }
      // For non-YouTube players
      else {
        if (internalPlayer && internalPlayer.setPlaybackRate) {
          try {
            internalPlayer.setPlaybackRate(props.playbackRate);
            if (props.onPlaybackRateSupported) {
              props.onPlaybackRateSupported(true);
            }
          } catch (error) {
            markUnsupported();
          }
        } else if (playerRef.current.setPlaybackRate) {
          try {
            playerRef.current.setPlaybackRate(props.playbackRate);
            if (props.onPlaybackRateSupported) {
              props.onPlaybackRateSupported(true);
            }
          } catch (error) {
            markUnsupported();
          }
        } else {
          markUnsupported();
        }
      }
    } catch (outerError) {
      // Any unexpected error should mark as unsupported
      markUnsupported();
    }
  }, [props.playbackRate, playerRef.current]);

  /**
   * Log how much of an item was watched and remember it in the cookie.
   *
   * Takes the item's identity explicitly rather than reading props: it runs for
   * the item that is going away, and by then the props already describe the next
   * one.
   */
  const flushWatch = ({ itemId, mode, compoundId }) => {
    clearTimeout(intervalRef.current)
    intervalRef.current = null

    if(!itemId || !watchData.current || !mode || !viewedRef.current) return

    const watchedSeconds    = countTruthy(watchData.current)
    const watchedPercentage = watchedSeconds / watchData.current.length

    const record = {
      id: compoundId,
      engaged: isEngagedRef.current
    }

    // TODO: Should not be hardcoded, get based on user preference
    if(watchedPercentage > 0.7) {
      cplLog(itemId, `engaged_${mode}_view`)
      record.engaged = true
    }

    cplLog(itemId, 'view_duration', {
      watchedSeconds,
      maxDuration: watchData.current.length
    })

    updateWatchedVideos(record)
  }

  const getWatchedVideos = () => {
    let watchedVideos = Cookies.get( 'cpl_watched_videos' )

    try {
      return JSON.parse(watchedVideos)
    }
    catch(err) {
      return []
    }
  }

  const updateWatchedVideos = (record) => {
    const watchedVideos = getWatchedVideos()

    const videoIndex = watchedVideos.findIndex(v => v.id === record.id)

    if(videoIndex !== -1) {
      watchedVideos[videoIndex] = record
    }
    else {
      watchedVideos.push(record)
    }

    Cookies.set( 'cpl_watched_videos', JSON.stringify(watchedVideos), {
      expires: 28
    } )
  }

  // Handle user interaction token for direct player control
  useEffect(() => {
    // Only proceed if we have a player reference
    if (!playerRef.current) return;

    // Get internal player instance
    const internalPlayer = playerRef.current.getInternalPlayer();
    if (!internalPlayer) return;

    // Check if this is iOS
    const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) ||
                  (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);

    // Check for token - either directly provided or from global storage
    const hasToken = userInteractionToken || window._activeUserInteractionToken;
    const validToken = hasToken && window._cplUserInteractions &&
                     (userInteractionToken ? window._cplUserInteractions[userInteractionToken] :
                      window._cplUserInteractions[window._activeUserInteractionToken]);

    // If we have a valid user interaction token, use it to handle playback with unmuting
    if (validToken) {

      // For YouTube videos
      if (typeof internalPlayer.unMute === 'function') {
        try {
          // Unified approach for both iOS and non-iOS
          // This simplified sequence works better across platforms

          // 1. Unmute immediately (critical for iOS)
          internalPlayer.unMute();

          // 2. Set volume to max (important step)
          if (typeof internalPlayer.setVolume === 'function') {
            internalPlayer.setVolume(100);
          }

          // 3. Play immediately after unmute
          if (typeof internalPlayer.playVideo === 'function') {
            internalPlayer.playVideo();
          }

          // For iOS, we use requestAnimationFrame for better reliability
          if (isIOS) {
            requestAnimationFrame(() => {
              if (internalPlayer) {
                // Double-check mute state
                if (typeof internalPlayer.isMuted === 'function' && internalPlayer.isMuted()) {
                  internalPlayer.unMute();
                }
                // Make sure it's playing
                if (typeof internalPlayer.playVideo === 'function') {
                  internalPlayer.playVideo();
                }
              }
            });
          }
        } catch (e) {
        }
      }
      // For Vimeo videos
      else if (typeof internalPlayer.setMuted === 'function') {
        forceUnmuteVimeoPlayer(internalPlayer, { play: true, retry: true });
      }
      // For HTML5 video/audio elements
      else if (typeof internalPlayer.play === 'function') {
        // Set volume to max and unmute
        internalPlayer.volume = 1.0;
        internalPlayer.muted = false;

        // Use the Promise-based API for playback
        const playPromise = internalPlayer.play();
        if (playPromise !== undefined) {
          playPromise.catch(error => {
            // Fall back to muted playback
            internalPlayer.muted = true;
            internalPlayer.play();
          });
        }
      }
    }
    // For non-token cases (autoplay/background loading)
    else {
      // For all browsers, try unmuting immediately (no delay)
      try {
        if (typeof internalPlayer.unMute === 'function') {
          // For YouTube
          internalPlayer.unMute();
          if (typeof internalPlayer.setVolume === 'function') {
            internalPlayer.setVolume(100);
          }
        }
        // For Vimeo
        else if (typeof internalPlayer.setMuted === 'function') {
          forceUnmuteVimeoPlayer(internalPlayer);
        }
        // For HTML5 video
        else if (internalPlayer.muted !== undefined) {
          internalPlayer.muted = false;
          internalPlayer.volume = 1.0;
        }
      } catch (e) {
      }
    }
  }, [playerRef.current, userInteractionToken]);

  // We handle iOS-specific behavior directly in the handlePlay function

  // Everything item-scoped lives in refs, and the persistent player keeps this
  // component mounted across sermons (Safari's playback permission is per
  // element), so a change of item has to do what a remount used to: log the
  // outgoing item's watch data, then start the incoming one from scratch.
  useEffect(() => {
    const identity = { itemId, mode, compoundId }

    viewedRef.current            = false
    isEngagedRef.current         = false
    watchData.current            = null
    lastProgressPosition.current = 0
    firstPlayRef.current         = true

    const video = getWatchedVideos().find(v => v.id === compoundId)

    if(video) {
      viewedRef.current = true

      if(video.engaged) {
        isEngagedRef.current = true
      }
    }

    return () => flushWatch(identity)
  }, [compoundId])

  useEffect(() => {
    const onUnload = () => flushWatch(latest.current)

    window.addEventListener('beforeunload', onUnload)

    return () => window.removeEventListener('beforeunload', onUnload)
  }, [])

  // Extract any custom props that React Player doesn't recognize
  const { onMutedPlayback, ...standardProps } = props;

  return (
    <VideoPlayer
      {...standardProps}
      ref={(player) => {
        // Store player in our ref
        playerRef.current = player;
        // Also pass it to the forwarded ref
        if (typeof ref === 'function') {
          ref(player);
        } else if (ref) {
          ref.current = player;
        }
      }}
      onPlay={handlePlay}
      onPause={handlePause}
      onDuration={handleDuration}
      onProgress={handleProgress}
      onSeek={handleSeek}
      progressInterval={100}
      config={{
        youtube: {
          playerVars: {
            // Common configurations for all platforms
            autoplay: 1,       // Must be first
            playsinline: 1,    // Enable inline playback (critical for iOS)
            rel: 0,            // Don't show related videos
            showinfo: 0,       // Hide video title and info
            modestbranding: 1, // Minimal YouTube branding
            iv_load_policy: 3, // Hide annotations
            disablekb: 1,      // Disable keyboard controls
            enablejsapi: 1,    // Enable JavaScript API (REQUIRED for unmuting)
            autohide: 1,       // Hide controls after play begins
            fs: 1,             // Enable fullscreen button
            origin: window.location.origin, // Set origin for improved security

            // Start muted for iOS (required for iframe autoplay)
            // This is required for YouTube videos on iOS specifically
            mute: isIOS ? 1 : 0
          }
        },
        vimeo: {
          playerOptions: {
            playsinline: true,
            controls: false,   // Hide Vimeo controls
            autopause: false,  // Prevent autopause when other videos play
            // Vimeo also needs muted for iOS autoplay
            muted: isIOS
          }
        },
        file: {
          // Render <audio> regardless of the URL. The persistent player's audio
          // instance needs this for its silent blob: placeholder, which has no
          // extension for react-player to recognise — without it the placeholder
          // is a <video>, and the real .mp3 later gets a brand-new <audio> that
          // Safari never granted playback permission to.
          forceAudio,
          attributes: {
            controlsList: "nodownload", // Prevent download option
            // Direct video files don't need to start muted
            // This allows them to play with audio on first tap
            muted: false,
            playsInline: true  // Important for iOS to play inline
          }
        }
      }}
    />
  )
}

export default forwardRef(PlayerWrapper);
