<?php
try {
	$item = new \CP_Library\Controllers\Item( get_the_ID() );
	$item = $item->get_api_data();
} catch ( \CP_Library\Exception $e ) {
	error_log( $e );

	return;
}
?>

<article class="cpl-item-card" onclick="window.location = jQuery(this).find('a').attr('href');">
	<div class="cpl-item-card--thumb" style="background-image: url('<?php echo esc_url( $item['thumb'] ); ?>');"></div>

	<h3 class="cpl-item-card--title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>

	<div class="cpl-item-card--meta">
		<div class="cpl-item-card--meta--date">
			<?php echo $item["date"]->format('Y-m-d'); ?>
		</div>

		<div class="cpl-item-card--meta--categories">
			<?php echo implode( ', ', $item['topics'] ); ?>
		</div>
	</div>
</article>
