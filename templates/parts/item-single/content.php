<?php
try {
	$item = new \CP_Library\Controllers\Item( get_the_ID() );
	$item = $item->get_api_data();
} catch ( \CP_Library\Exception $e ) {
	error_log( $e );

	return;
}
?>

<div class="cpl-single-item--content">
	<div class="cpl-single-item--title">
		<h1><?php the_title(); ?></h1>
	</div>

	<?php if ( ! empty( $item['types'] ) ) : ?>
		<div class="cpl-single-item--types">
			<?php foreach( $item['types'] as $type ) : ?>
				<a href="<?php echo esc_url( $type['permalink'] ); ?>"><?php echo esc_html( $type['title'] ); ?></a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<div class="cpl-single-item--meta">
		<div class="cpl-single-item--meta--date">
			<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none"
				 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
				<rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
				<line x1="16" y1="2" x2="16" y2="6"></line>
				<line x1="8" y1="2" x2="8" y2="6"></line>
				<line x1="3" y1="10" x2="21" y2="10"></line>
			</svg>
			<span class="MuiBox-root css-1isemmb"><?php echo $item["date"]->format('Y-m-d'); ?></span></div>
		<div class="cpl-single-item--meta--categories">
			<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none"
				 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
				<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
				<line x1="7" y1="7" x2="7.01" y2="7"></line>
			</svg>
			<span class="MuiBox-root css-1isemmb"><?php echo implode( ', ', $item['topics'] ); ?></span>
		</div>
	</div>

	<div class="cpl-single-item--desc">
		<?php the_content(); ?>
	</div>
</div>
