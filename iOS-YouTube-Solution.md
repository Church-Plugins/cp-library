# Statement of Work: YouTube Single-Click Solution for iOS

## Overview
This document outlines the implementation approach for enabling single-click YouTube video playback on iOS devices using pre-initialized hidden iframes.

## Problem Statement
Currently, iOS users must click twice to play YouTube videos: once to initialize the player and again to start playback. This creates an inconsistent user experience across platforms.

## Solution Architecture
Implement a preloaded YouTube iframe solution with z-index manipulation to enable single-click video playback on all platforms, including iOS.

## Technical Approach

### 1. Player Pre-initialization
- Create YouTube iframes during component mount, not on user interaction
- Configure all iframes with required parameters for iOS compatibility:
  ```javascript
  {
    playsinline: 1,
    enablejsapi: 1,
    origin: window.location.origin,
    controls: 0,
    rel: 0,
    modestbranding: 1
  }
  ```
- Initially set to muted state to comply with iOS autoplay restrictions

### 2. Visual Handling
- Use CSS to hide preloaded iframes:
  ```css
  .preloaded-player {
    position: absolute;
    z-index: -1;
    opacity: 0;
    pointer-events: none;
  }
  ```
- Maintain thumbnail display while player is hidden
- Custom play button overlaid on thumbnail

### 3. Interaction Flow
- On play button click:
  1. Change hidden player CSS to reveal:
     ```javascript
     setPlayerStyle({
       zIndex: 10,
       opacity: 1,
       pointerEvents: 'auto'
     });
     ```
  2. Send play command directly to YouTube API:
     ```javascript
     playerRef.current.getInternalPlayer().playVideo();
     ```
  3. Remove/hide thumbnail in the same interaction
  4. Show custom controls overlay

### 4. Player State Management
- Track player state independent of visibility
- Optimize resource usage by limiting number of preloaded players to visible ones
- Handle player lifecycle appropriately when component unmounts

### 5. Performance Optimization
- Implement lazy initialization for players outside viewport
- Use IntersectionObserver to initialize only visible/near-visible players
- Prioritize visible content

## Implementation Details

### Component Modifications
1. Update `Player.jsx`:
   - Modify component to create player on mount instead of on click
   - Add z-index and opacity control states
   - Modify click handlers to reveal player and send direct play command

2. Update `PlayerWrapper.jsx`:
   - Configure YouTube player parameters for iOS compatibility
   - Add additional event handlers for player state changes

### CSS Modifications
```scss
.player-container {
  position: relative;
  overflow: hidden;
}

.hidden-player {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  z-index: -1;
  opacity: 0;
  transition: opacity 0.3s ease;
  pointer-events: none;
}

.visible-player {
  z-index: 10;
  opacity: 1;
  pointer-events: auto;
}

.thumbnail-container {
  position: absolute;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  z-index: 5;
  display: flex;
  align-items: center;
  justify-content: center;
}
```

## Key Code Modifications

```javascript
// In Player.jsx component
const [playerVisible, setPlayerVisible] = useState(false);

// Initialize player on component mount
useEffect(() => {
  if (currentItem?.video?.value) {
    setMode('video');
    setCurrentMedia(currentItem.video.value);
    // Player is created but hidden
  }
}, [currentItem]);

// On play button click
const handlePlay = () => {
  setPlayerVisible(true);
  
  // Need to directly call player API methods to maintain user gesture context
  if (playerInstance.current) {
    // This must happen synchronously in the click handler
    requestAnimationFrame(() => {
      playerInstance.current.getInternalPlayer().playVideo();
    });
  }
  
  // Track analytics
  cplLog(item.id, 'play');
  setHasPlayed(true);
};
```

## Testing Requirements
- Test on multiple iOS devices (iPhone and iPad with different iOS versions)
- Test on Safari, Chrome for iOS, and in-app browsers
- Test on Android and desktop browsers to ensure consistent behavior
- Verify performance impact with multiple videos on page

## Fallbacks
If issues arise with preloaded players:
- Implement progressive fallback to two-click solution
- Provide clear visual indicator of loading state
- Add device-specific detection and handling

## Deliverables
1. Updated Player.jsx component
2. Updated PlayerWrapper.jsx with iOS optimizations
3. New CSS for player visibility control
4. Documentation of implementation details
5. Test results across platforms

## Timeline
- Development: 2-3 days
- Testing: 1-2 days
- Refinement: 1 day
- Total: 4-6 days

## Maintenance Considerations
- Monitor iOS WebKit updates for policy changes
- Add analytics to track interaction success rates
- Plan for periodic testing with new iOS versions