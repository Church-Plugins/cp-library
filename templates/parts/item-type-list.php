<?php

try {
	$item_type = new \CP_Library\Controllers\ItemType( get_the_ID() );
	$item_type = $item_type->get_api_data();
} catch ( \CP_Library\Exception $e ) {
	error_log( $e );

	return;
}
?>

<div class="cpl-list-type">

	<div class="cpl-list-type--thumb" onclick="window.location = jQuery(this).parent().find('a').attr('href');">
		<div class="cpl-list-type--thumb--canvas">
			<?php if ( $item_type['thumb'] ) : ?>
				<img alt="<?php esc_attr( $item_type['title'] ); ?>" src="<?php echo esc_url( $item_type['thumb'] ); ?>">
			<?php endif; ?>
		</div>
	</div>

	<div class="cpl-list-type--details">
		<h3 class="cpl-list-type--title"><a href="<?php the_permalink(); ?>"><?php echo $item_type['title']; ?></a></h3>

		<div class="cpl-meta" style="display:none;">

			<div class="cpl-meta--date">
				<span class="material-icons-outlined">calendar_today</span>

				<span><?php echo $item_type["date"]["desc"]; ?></span>
			</div>

			<?php if ( ! empty( $item_type['items'] ) ) : ?>
				<div class="cpl-meta--item-count">
					<span class="material-icons-outlined">view_list</span>
					<span><?php printf( '%s %s', count( $item_type['items'] ), count( $item_type['items'] ) > 1 ? cp_library()->setup->post_types->item->plural_label : cp_library()->setup->post_types->item->single_label ); ?></span>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $item_type['scripture'] ) ) : ?>
				<div class="cpl-meta--topics">
					<span class="material-icons-outlined">menu_book</span>

					<?php foreach ( $item_type['scripture'] as $scripture ) : ?>
						<a href="<?php echo esc_url( $scripture['url'] ); ?>"><?php echo esc_html( $scripture['name'] ); ?></a><span class="cpl-separator">, </span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		</div>

		<?php if ( $excerpt = get_the_excerpt()  ) : ?>
			<div class="cpl-list-type--desc">
				<?php echo $excerpt; ?>
			</div>
		<?php endif; ?>
	</div>

</div>
