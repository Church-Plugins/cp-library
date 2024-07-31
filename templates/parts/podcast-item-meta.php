<?php
try {
	$item = new \CP_Library\Controllers\Item( get_the_ID() );
} catch ( \ChurchPlugins\Exception $e ) {
	error_log( $e );
	return;
}

?>
<itunes:title><?php the_title_rss(); ?></itunes:title>

<?php if ( $item->get_podcast_speakers() ) : ?>
	<dc:creator><?php echo esc_html( $item->get_podcast_speakers() ); ?></dc:creator>
	<itunes:author><?php echo esc_html( $item->get_podcast_speakers() ); ?></itunes:author>
	<googleplay:author><?php echo esc_html( $item->get_podcast_speakers() ); ?></googleplay:author>
<?php endif; ?>

<?php if ( $item->get_podcast_subtitle() ) : ?>
	<itunes:subtitle><?php echo esc_html( $item->get_podcast_subtitle() ); ?></itunes:subtitle>
<?php endif; ?>

<?php if ( $item->get_podcast_description() ) : ?>
	<itunes:summary><?php echo esc_html( $item->get_podcast_description() ); ?></itunes:summary>
	<googleplay:description><?php echo esc_html( $item->get_podcast_description() ); ?></googleplay:description>
<?php endif; ?>

<?php rss_enclosure(); // we run do_enclosure() when a sermon (Item) is saved. ?>

<?php if ( $item->get_duration() ) : ?>
	<itunes:duration><?php echo esc_html( $item->get_duration() ); ?></itunes:duration>
<?php endif; ?>
