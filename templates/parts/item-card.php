<?php
try {
	$item = new \CP_Library\Controllers\Item( get_the_ID() );
	$item = $item->get_api_data();
} catch ( \CP_Library\Exception $e ) {
	error_log( $e );

	return;
}
?>

<article class="cpl-item-card">
	<div class="cpl-item-card--thumb" onclick="window.location = jQuery(this).parent().find('.cpl-item-card--title a').attr('href');" style="background-image: url('<?php echo esc_url( $item['thumb'] ); ?>');"></div>

	<h3 class="cpl-item-card--title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>

	<div class="cpl-meta">
		<div class="cpl-meta--date">
			<?php echo $item["date"]["desc"]; ?>
		</div>

		<?php if ( ! empty( $item['topics'] ) ) : ?>
			<div class="cpl-meta--topics">
				<span class="material-icons-outlined">sell</span>

				<?php foreach ( $item['topics'] as $topic ) : ?>
					<a href="<?php echo esc_url( $topic['url'] ); ?>"><?php echo esc_html( $topic['name'] ); ?></a>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>
	</div>
</article>
