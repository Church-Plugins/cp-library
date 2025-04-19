import Controllers_WP_REST_Request from '../Controllers/WP_REST_Request';

export function cplVar( key, index ) {
	if ( ! window.hasOwnProperty( 'cplVars' ) ) {
		return '';
	}

	if ( ! window.cplVars.hasOwnProperty( index ) ) {
		return '';
	}

	if ( ! window.cplVars[ index ].hasOwnProperty( key ) ) {
		return '';
	}

	return window.cplVars[ index ][ key ];
}

export function cplLog( itemID, action, payload = null ) {
	const restRequest = new Controllers_WP_REST_Request();
	return restRequest.post({endpoint: `items/${itemID}/log`, data: {action, payload}});
}

/**
 * Calculate the important information about an item's scrubber marker
 *
 * @param Object item
 * @param String mode
 * @param int duration
 * @returns Object
 */
export function cplMarker( item, mode, duration ) {

	let markPosition = 0;
	let snapDiff = 60;
	const videoMarks = [];
	let markerLabel = "Sermon";

	if( item && item.video && item.video.marker ) {
	  markPosition = item.video.marker;
	}

	if( markPosition > 0 ) {
	  let relativeDistance = (markPosition / duration);

	  if( relativeDistance < 0.05 || relativeDistance >= 0.95 ) {
		  // Do not show marker or label
		  markPosition = 0;
	  } else if ( relativeDistance < 0.2 || relativeDistance >= 0.8 ) {
		  // Do not show label
		  markerLabel = null;
	  }
	}

	if( 'video' === mode && markPosition > 0 ) {
	  videoMarks.push(
		  {
			  value: markPosition,
			  label: markerLabel
		  }
	  );
	}

	return {
		position		: markPosition,
		snapDistance	: snapDiff,
		marks			: videoMarks
	}
}

export function isURL(string) {
	try {
		new URL(string);
		return true;
	}
	catch(e) {
		return false;
	}
}

/**
 * Attempts to unmute a YouTube player with various fallback approaches specifically for iOS
 * 
 * @param {Object} player - The YouTube player instance
 * @param {boolean} [setupInterval=false] - Whether to set up an interval to repeatedly try unmuting
 * @param {string} [source='unknown'] - Source of the unmute call for debugging
 * @returns {number|null} - Interval ID if an interval was set up, null otherwise
 */
export function forceUnmuteYouTubePlayer(player, setupInterval = false, source = 'unknown') {
  if (!player) {
    console.log(`[DEBUG:${source}] forceUnmuteYouTubePlayer called with null player`);
    return null;
  }
  
  // Detect platform for logging
  const isIOS = /iPad|iPhone|iPod/.test(navigator.userAgent) || 
               (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
  
  console.log(`[DEBUG:${source}] forceUnmuteYouTubePlayer START on ${isIOS ? 'iOS' : 'non-iOS'} device`);
  console.log(`[DEBUG:${source}] Player methods available:`, {
    unMute: typeof player.unMute === 'function',
    setVolume: typeof player.setVolume === 'function',
    getIframe: typeof player.getIframe === 'function',
    hasH: !!player.h,
    hasHMuted: player.h && player.h.muted !== undefined
  });
  
  try {
    // Special workaround for iOS YouTube embeds
    if (isIOS) {
      // Create a user-triggered audio element to keep iOS audio permissions active
      if (!window._iosAudioUnlocker) {
        try {
          console.log(`[DEBUG:${source}] Creating iOS audio unlocker`);
          const audioEl = document.createElement('audio');
          audioEl.src = 'data:audio/mpeg;base64,/+MYxAAAAANIAAAAAExBTUUzLjk4LjIAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAA';
          audioEl.volume = 0.1;
          document.body.appendChild(audioEl);
          audioEl.play().catch(() => {});
          window._iosAudioUnlocker = audioEl;
        } catch (e) {
          console.log(`[DEBUG:${source}] Error creating iOS audio unlocker:`, e);
        }
      }
      
      // For iOS, we need to use both the player API and direct iframe access
      console.log(`[DEBUG:${source}] Using iOS-specific unmuting approaches`);
    }
    
    // Try standard unmute
    if (typeof player.unMute === 'function') {
      console.log(`[DEBUG:${source}] Calling player.unMute()`);
      // Try multiple times for iOS (some devices need this)
      player.unMute();
      
      // For iOS, make multiple unmute calls with slight delay between
      if (isIOS) {
        setTimeout(() => player.unMute(), 10);
        setTimeout(() => player.unMute(), 100);
      }
      
      // Try to check muted state if available
      if (typeof player.isMuted === 'function') {
        const muted = player.isMuted();
        console.log(`[DEBUG:${source}] After unMute, player.isMuted() = ${muted}`);
        
        // If still muted, try more direct approach with the iframe
        if (muted && typeof player.getIframe === 'function') {
          try {
            const iframe = player.getIframe();
            console.log(`[DEBUG:${source}] Iframe found:`, !!iframe);
            
            if (iframe && iframe.contentWindow) {
              console.log(`[DEBUG:${source}] Direct iframe access approach`);
              
              // Try directly posting messages to the iframe in different formats
              // Format 1: JSON string
              try {
                iframe.contentWindow.postMessage(JSON.stringify({
                  event: 'command',
                  func: 'unMute',
                  args: []
                }), '*');
                
                iframe.contentWindow.postMessage(JSON.stringify({
                  event: 'command',
                  func: 'setVolume',
                  args: [100]
                }), '*');
              } catch (err) {
                console.log(`[DEBUG:${source}] Error posting JSON message to iframe:`, err);
              }
              
              // Format 2: Direct string 
              try {
                iframe.contentWindow.postMessage('{"event":"command","func":"unMute","args":""}', '*');
                iframe.contentWindow.postMessage('{"event":"command","func":"setVolume","args":[100]}', '*');
              } catch (err) {
                console.log(`[DEBUG:${source}] Error posting string message to iframe:`, err);
              }
              
              // Format 3: YouTube API specific format
              try {
                const ytEvent = {
                  event: 'listening',
                  id: iframe.id,
                  channel: 'widget'
                };
                iframe.contentWindow.postMessage(JSON.stringify(ytEvent), '*');
                
                // Follow with mute commands
                setTimeout(() => {
                  const unmuteCmd = {
                    event: 'command',
                    func: 'unMute',
                    args: [],
                    id: iframe.id,
                    channel: 'widget'
                  };
                  iframe.contentWindow.postMessage(JSON.stringify(unmuteCmd), '*');
                }, 100);
              } catch (err) {
                console.log(`[DEBUG:${source}] Error with YouTube API specific format:`, err);
              }
            }
          } catch (iframeErr) {
            console.log(`[DEBUG:${source}] Error accessing iframe:`, iframeErr);
          }
        }
      }
    }
    
    // Set volume to max
    if (typeof player.setVolume === 'function') {
      console.log(`[DEBUG:${source}] Calling player.setVolume(100)`);
      player.setVolume(100);
      
      // Try to check volume if available
      if (typeof player.getVolume === 'function') {
        const volume = player.getVolume();
        console.log(`[DEBUG:${source}] After setVolume, player.getVolume() = ${volume}`);
      }
    }
    
    // Try to access internal properties (YouTube iframe API hack)
    if (player.h && player.h.muted !== undefined) {
      const oldValue = player.h.muted;
      console.log(`[DEBUG:${source}] Setting player.h.muted = false (was ${oldValue})`);
      player.h.muted = false;
    }
    
    // Access underlying iframe document if possible
    try {
      const frame = player.getIframe();
      if (frame && frame.contentWindow) {
        console.log(`[DEBUG:${source}] Sending postMessage to iframe`);
        frame.contentWindow.postMessage('{"event":"command","func":"unMute","args":""}', '*');
      }
    } catch (e) {
      console.log(`[DEBUG:${source}] Error accessing iframe:`, e);
    }
    
    // Set up repeated unmuting for iOS if requested
    // This is needed because iOS is particularly stubborn
    if (setupInterval) {
      if (isIOS) {
        console.log(`[DEBUG:${source}] Setting up unmuting RAF and interval for iOS`);
        
        // Try again after a short delay with requestAnimationFrame
        requestAnimationFrame(() => {
          console.log(`[DEBUG:${source}] RAF callback executing`);
          if (player) {
            if (typeof player.unMute === 'function') {
              player.unMute();
            }
            if (typeof player.setVolume === 'function') {
              player.setVolume(100);
            }
          }
        });
        
        // Set up an interval to keep trying to unmute
        const unmutingInterval = setInterval(() => {
          console.log(`[DEBUG:${source}] Interval unmute attempt`);
          if (player) {
            if (typeof player.unMute === 'function') {
              player.unMute();
              // Try to check muted state if available
              if (typeof player.isMuted === 'function') {
                const muted = player.isMuted();
                console.log(`[DEBUG:${source}] In interval, player.isMuted() = ${muted}`);
              }
            }
            if (typeof player.setVolume === 'function') {
              player.setVolume(100);
            }
          } else {
            console.log(`[DEBUG:${source}] Clearing interval - player no longer available`);
            clearInterval(unmutingInterval);
          }
        }, 300);
        
        // Clear interval after 3 seconds to avoid memory leaks
        setTimeout(() => {
          console.log(`[DEBUG:${source}] Cleaning up interval after timeout`);
          clearInterval(unmutingInterval);
        }, 3000);
        
        return unmutingInterval;
      } else {
        console.log(`[DEBUG:${source}] Not setting up interval - not an iOS device`);
      }
    }
  } catch (e) {
    console.error(`[DEBUG:${source}] Error in forceUnmuteYouTubePlayer:`, e);
  }
  
  console.log(`[DEBUG:${source}] forceUnmuteYouTubePlayer END`);
  return null;
}