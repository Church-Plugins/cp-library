<?php

try {
	$item = new \CP_Library\Controllers\Item( get_the_ID() );
	$item = $item->get_api_data();
} catch ( \CP_Library\Exception $e ) {
	error_log( $e );

	return;
}
?>

<div class="cpl-list-item">

	<div class="cpl-list-item--thumb" onclick="window.location = jQuery(this).parent().find('a').attr('href');">
		<div class="cpl-list-item--thumb--canvas" style="background: url(<?php echo esc_url( $item['thumb'] ); ?>) 0% 0% / cover;">
			<?php if ( $item['thumb'] ) : ?>
				<img alt="<?php esc_attr( $item['title'] ); ?>" src="<?php echo esc_url( $item['thumb'] ); ?>">
			<?php endif; ?>
		</div>
	</div>

	<div class="cpl-list-item--details">
		<h3 class="cpl-list-item--title"><a href="<?php the_permalink(); ?>"><?php echo $item['title']; ?></a></h3>

		<?php if ( ! empty( $item['types'] ) ) : ?>
			<div class="cpl-list-item--types">
				<?php foreach ( $item['types'] as $type ) : ?>
					<a href="<?php echo esc_url( $type['permalink'] ); ?>"><?php echo esc_html( $type['title'] ); ?></a><span class="cpl-separator">, </span>
				<?php endforeach; ?>
			</div>
		<?php endif; ?>

		<div class="cpl-meta">
			<div class="cpl-meta--date">
				<span class="material-icons-outlined">calendar_today</span>

				<span class="MuiBox-root css-1isemmb"><?php echo $item["date"]["desc"]; ?></span>
			</div>

			<?php if ( ! empty( $item['topics'] ) ) : ?>
				<div class="cpl-meta--topics">
					<span class="material-icons-outlined">sell</span>

					<?php foreach ( $item['topics'] as $topic ) : ?>
						<a href="<?php echo esc_url( $topic['url'] ); ?>"><?php echo esc_html( $topic['name'] ); ?></a>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $item['scripture'] ) ) : ?>
				<div class="cpl-meta--scripture">
					<span class="material-icons-outlined">menu_book</span>

					<?php foreach ( $item['scripture'] as $scripture ) : ?>
						<a href="<?php echo esc_url( $scripture['url'] ); ?>"><?php echo esc_html( $scripture['name'] ); ?></a><span class="cpl-separator">, </span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>
	</div>

	<div class="cpl_item_actions" data-item="<?php echo esc_attr( json_encode( $item ) ); ?>" ></div>

</div>
