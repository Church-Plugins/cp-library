<?php
try {
	$item = new \CP_Library\Controllers\Item( get_the_ID() );
} catch ( \ChurchPlugins\Exception $e ) {
	error_log( $e );
	return;
}

$image = $item->get_thumbnail();

?>
<item>

	<title><?php the_title_rss(); ?></title>
	<itunes:title><?php the_title_rss(); ?></itunes:title>

	<pubDate><?php echo esc_html( mysql2date( 'D, d M Y H:i:s +0000', get_post_time( 'Y-m-d H:i:s', true ), false ) ); ?></pubDate>
	<guid isPermaLink="false"><?php the_guid(); ?></guid>
	<link><?php the_permalink_rss(); ?></link>

	<?php if ( ! empty( $image ) ) : ?>
		<itunes:image href="<?php echo esc_url( $item->get_thumbnail() ); ?>"></itunes:image>
		<googleplay:image href="<?php echo esc_url( $item->get_thumbnail() ); ?>"></googleplay:image>

		<?php $filetype = wp_check_filetype( $image ); ?>
		<media:content 
			url="<?php echo esc_url( $item->get_thumbnail( 'full' ) ); ?>"
			medium="image"
			type="<?php echo esc_attr( $filetype['type'] ); ?>"
		/>
	<?php endif; ?>

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

	<?php do_action( 'rss2_item' ); // Core: Fires at the end of each RSS2 feed item. ?>

</item>
