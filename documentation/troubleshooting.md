# Troubleshooting & FAQs

## Installation & Setup Issues

### Plugin Won't Activate

**Issue**: The plugin fails to activate or causes errors upon activation.

**Solutions**:
1. Verify your WordPress version is 6.0 or higher
2. Check that your PHP version is 7.4 or higher
3. Temporarily deactivate all other plugins to check for conflicts
4. Switch to a default WordPress theme to rule out theme compatibility issues

### Missing Plugin Pages

**Issue**: The Library menu or sermon pages don't appear after activation.

**Solutions**:
1. Go to Settings → Permalinks and click "Save Changes" to flush rewrite rules
2. Check if another plugin is conflicting with the menu registration
3. Verify user permissions to ensure you have access to view these pages

## Media Playback Problems

### Audio/Video Won't Play

**Issue**: Sermon media files don't play in the browser.

**Solutions**:
1. Check that the media file URL is accessible
2. Verify the file format is supported by browsers (MP3 for audio, MP4 for video)
3. Check for JavaScript errors in your browser console
4. Test with a different browser to rule out browser-specific issues

### Embedded Videos Not Displaying

**Issue**: Embedded videos from YouTube or Vimeo don't appear.

**Solutions**:
1. Verify the video URL is correct and the video is public
2. Check if your theme or another plugin is conflicting with the oEmbed functionality
3. Try using the direct embed code instead of the URL

## Podcast Feed Errors

### Feed Not Generating

**Issue**: Podcast feed URLs return 404 errors.

**Solutions**:
1. Go to Settings → Permalinks and click "Save Changes"
2. Verify your permalink structure is compatible
3. Check server rewrite rules if using Nginx

### Feed Missing Episodes

**Issue**: Some sermons don't appear in the podcast feed.

**Solutions**:
1. Check that the missing sermons have audio files attached
2. Verify the sermons are published (not draft or scheduled)
3. Check if the feed has a category filter that excludes these sermons
4. Ensure the sermons aren't marked as "Exclude from podcast"

### Feed Validation Errors

**Issue**: Podcast directories reject your feed due to validation errors.

**Solutions**:
1. Use a feed validator service to identify specific issues
2. Check that your podcast image meets size requirements (1400×1400px minimum)
3. Verify all required fields are completed in the podcast settings
4. Ensure episode titles don't contain special characters that break XML

## Analytics & Engagement Tracking Issues

### Analytics Not Recording

**Issue**: Sermon plays and engagement aren't being tracked.

**Solutions**:
1. Check if analytics tracking is enabled in settings
2. Verify that your site doesn't have caching that prevents tracking scripts from loading
3. Check for JavaScript errors or ad blockers that might prevent tracking
4. Ensure your server has sufficient permissions to write to the database

### Incorrect Analytics Data

**Issue**: Analytics numbers seem incorrect or unrealistic.

**Solutions**:
1. Check for bot traffic that might be inflating numbers
2. Verify tracking code is only loading once per page
3. Reset analytics data if necessary

## Customization & Display Problems

### Template Changes Not Showing

**Issue**: Customized templates don't appear on the frontend.

**Solutions**:
1. Verify template files are in the correct location
2. Check theme template hierarchy to ensure your templates are being loaded
3. Clear cache plugins that might be serving older versions

### Styling Issues

**Issue**: Sermon displays don't match your theme styling.

**Solutions**:
1. Check for CSS conflicts with your theme
2. Add custom CSS to match your theme's styling
3. Verify the sermon blocks are configured correctly

### Shortcode Not Working

**Issue**: Sermon shortcodes don't display anything.

**Solutions**:
1. Verify shortcode syntax is correct
2. Check if the shortcode parameters match existing content
3. Try a basic shortcode without parameters to isolate the issue

## Performance & Optimization Tips

### Slow Loading Sermons

**Issue**: Sermon pages load slowly.

**Solutions**:
1. Optimize media file sizes
2. Use a caching plugin
3. Consider using a CDN for media files
4. Limit the number of sermons loaded on archive pages

### Database Optimization

For large sermon libraries:

1. Regularly clean up draft and trashed sermons
2. Use the "Rebuild Data" tool in Library → Tools to optimize internal tables
3. Consider limiting the number of revisions stored for sermon posts

### Server Resource Usage

If your sermon library is causing server resource issues:

1. Optimize image sizes used for sermon thumbnails
2. Use external hosting for large media files
3. Implement lazy loading for sermon lists
4. Consider upgrading your hosting plan for large libraries