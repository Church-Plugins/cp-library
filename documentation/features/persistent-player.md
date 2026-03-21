# Persistent Player

CP Sermon Library includes a persistent media player that allows visitors to continue listening to or watching sermons while browsing your site. It supports both audio and video playback.

## How It Works

The persistent player is always active -- it is automatically included on every page of your site. When a visitor clicks play on a sermon, the persistent player appears as a fixed bar at the bottom of the browser window. As they navigate to other pages, playback continues without interruption.

### Audio vs. Video Behavior

Audio and video are handled differently to provide the best experience for each media type:

- **Audio ("Listen")** -- Clicking "Listen" on any sermon always opens the audio in the persistent player. This ensures uninterrupted playback as visitors browse your site.
- **Video ("Watch")** -- Clicking "Watch" on a sermon detail page plays the video in the inline player on that page. Visitors can then send the video to the persistent player using the picture-in-picture button in the player controls. When "Watch" is clicked from a sermon list (not the detail page), the video opens directly in the persistent player.

When a video is playing in the persistent player, a video panel appears above the control bar at the bottom of the screen. Visitors can click the video area to toggle play/pause.

### Player Features

- **Continuous Playback** -- Audio and video continue playing across page navigation
- **Sermon Information** -- Shows the current sermon title with a link back to the sermon page
- **Playback Controls** -- Play/pause, seek bar, skip forward/back, and playback speed
- **Time Display** -- Shows current position and total duration
- **Site Logo** -- Displays your site logo alongside the player
- **Close Button** -- Dismiss the player and stop playback

## User Experience

### Starting Playback

Visitors start playback by clicking "Listen" or "Watch" on any sermon. Audio always opens in the persistent player bar at the bottom of the page. Video plays inline on the sermon detail page by default, with the option to send it to the persistent player.

### Navigating While Listening

Once playing:

1. The player bar remains at the bottom of every page
2. Visitors can browse your site freely
3. Playback continues without interruption
4. Clicking play on a new sermon switches the player to that sermon

### Player Controls

- **Play/Pause** -- Toggle playback
- **Seek** -- Click or drag the progress bar to jump to a specific point
- **Back 10 / Skip 30** -- Jump backward 10 seconds or forward 30 seconds
- **Playback Speed** -- Tap to cycle through speeds: 1x, 1.25x, 1.5x, 2x, then back to 1x
- **Close** -- Dismiss the player and stop playback

### Inline Player Controls

The inline player on the sermon detail page (used for video) includes additional controls:

- **Fullscreen** -- Expands the video to fill the screen. Available on desktop browsers only; hidden on iOS due to platform restrictions on the fullscreen API.
- **Picture-in-Picture** -- Sends the current video to the persistent player so playback continues while browsing.

### iOS and Mobile Considerations

On iOS devices (iPhone, iPad), browser autoplay restrictions may cause video or audio to start muted. When this happens, the player displays a "Tap to enable sound" overlay. Tapping the overlay unmutes playback. This is a standard iOS browser limitation that applies to all websites, not specific to this plugin.

## Player Color

The persistent player uses the **Primary Color** set in Messages -> Settings -> Main tab. This color applies to the player controls and progress bar.

## Theme Compatibility

The persistent player is designed to work with most WordPress themes. If you experience layout issues:

1. Check that your theme does not have a fixed footer that overlaps the player
2. Add bottom padding to your site's footer if needed
3. Test on mobile devices to ensure the player does not obstruct navigation

## Troubleshooting

### Player Not Appearing

- Ensure the sermon has a valid audio or video file attached
- Check for JavaScript errors in your browser console
- Try with a default WordPress theme to rule out theme conflicts

### Audio Stops on Page Navigation

- Check that your theme loads the plugin's scripts correctly
- Verify no caching plugin is preventing script loading
- Ensure your theme does not force full page reloads on navigation

### Player Overlapping Content

- Add CSS to your theme to account for the player height at the bottom of the page
- Most themes handle this automatically, but custom footers may need adjustment

### Video Plays Without Sound on Mobile

- This is caused by iOS autoplay restrictions. Tap the "Tap to enable sound" overlay that appears on the player to unmute.
- If the overlay does not appear, try tapping directly on the video area or the volume controls.
