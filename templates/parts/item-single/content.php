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

	<div class="cpl-meta">
			<div class="cpl-meta--date">
				<span class="material-icons-outlined">calendar_today</span>

				<span><?php echo $item["date"]["desc"]; ?></span>
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

	<div class="cpl-single-item--desc">
		<?php the_content(); ?>
	</div>
</div>
