# Persistent Player

CP Sermon Library includes a persistent media player that allows visitors to continue listening to or watching sermons while browsing your site. It supports both audio and video playback.

## How It Works

The persistent player is always active -- it is automatically included on every page of your site. When a visitor clicks play on a sermon, the persistent player appears as a fixed bar at the bottom of the browser window. As they navigate to other pages, playback continues without interruption.

### Audio vs. Video Behavior

Audio and video are handled differently to provide the best experience for each media type:

- **Audio ("Listen")** -- A direct audio file or URL opens in the persistent player. Embed HTML (not a URL) plays inline on the sermon page. From a list, that embed links to the sermon instead of opening the bar.
- **Video ("Watch")** -- On the sermon page, a video URL plays in the inline player. Use **Open in persistent player** in the player controls to move it to the bar. From a list, a video URL opens in the persistent player. Embed HTML links to the sermon.

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

Visitors start playback by clicking **Listen** or **Watch**. A direct audio URL opens in the bar. A video URL plays inline on the sermon page, and **Open in persistent player** sends it to the bar. From a list, a video URL opens in the bar.

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

- **Open in fullscreen** -- Expands the video to fill the screen. Shown on desktop browsers that are not iOS.
- **Open in persistent player** -- Sends the current video to the persistent player so playback continues while browsing.

### iOS and Mobile Considerations

On iOS devices (iPhone, iPad), browser autoplay restrictions may cause video or audio to start muted. When this happens, audio in the persistent player displays **Tap to enable sound**. Video displays **Tap here to enable sound**. Tapping the overlay unmutes playback. This is a standard iOS browser limitation that applies to all websites, not specific to this plugin.

## Player Color

The persistent player uses the **Primary Color** set in Series → Settings → Main. This color applies to the progress bar.

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

- This is caused by iOS autoplay restrictions. Tap **Tap to enable sound** (audio) or **Tap here to enable sound** (video).
- If the overlay does not appear, try tapping directly on the video area or the volume controls.
