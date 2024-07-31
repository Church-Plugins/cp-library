<?php
try {
	$item = new \CP_Library\Controllers\Item( get_the_ID() );
} catch ( \ChurchPlugins\Exception $e ) {
	error_log( $e );
	return;
}

?>
<item>

	<title><?php the_title_rss(); ?></title>
	<itunes:title><?php the_title_rss(); ?></itunes:title>

	<pubDate><?php echo esc_html( mysql2date( 'D, d M Y H:i:s +0000', get_post_time( 'Y-m-d H:i:s', true ), false ) ); ?></pubDate>
	<guid isPermaLink="false"><?php the_guid(); ?></guid>
	<link><?php the_permalink_rss(); ?></link>

	<?php if ( $item->get_podcast_speakers() ) : ?>
		<dc:creator><?php echo $item->get_podcast_speakers(); ?></dc:creator>
		<itunes:author><?php echo $item->get_podcast_speakers(); ?></itunes:author>
		<googleplay:author><?php echo $item->get_podcast_speakers(); ?></googleplay:author>
	<?php endif; ?>

	<?php if ( $item->get_podcast_subtitle() ) : ?>
		<itunes:subtitle><?php echo esc_html( $item->get_podcast_subtitle() ); ?></itunes:subtitle>
	<?php endif; ?>

	<?php if ( $item->get_podcast_description() ) : ?>
		<description><?php echo esc_html( $item->get_podcast_description() ); ?></description>
		<itunes:summary><?php echo esc_html( $item->get_podcast_description() ); ?></itunes:summary>
		<googleplay:description><?php echo esc_html( $item->get_podcast_description() ); ?></googleplay:description>
	<?php endif; ?>

	<?php if ( $item->get_podcast_content() ) : ?>
		<content:encoded><![CDATA[<?php echo $item->get_podcast_content(); ?>]]></content:encoded>
	<?php endif; ?>

	<?php rss_enclosure(); // we run do_enclosure() when a sermon (Item) is saved. ?>

	<?php if ( $item->get_duration() ) : ?>
		<itunes:duration><?php echo esc_html( $item->get_duration() ); ?></itunes:duration>
	<?php endif; ?>

	<?php if ( ( $thumb = $item->get_thumbnail() ) && $image_size = getimagesize( $thumb ) ) {
		printf( "
			<image>
				<url>%s</url>
				<title>%s</title>
				<link>%s</link>
				<width>%s</width>
				<height>%s</height>
			</image>",
			convert_chars( $thumb ),
			get_the_title_rss(),
			get_the_permalink(),
			$image_size[0],
			$image_size[1]
		);
	} ?>

	<?php do_action( 'rss2_item' ); // Core: Fires at the end of each RSS2 feed item. ?>

</item>
