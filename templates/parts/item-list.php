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

		<div class="cpl-list-item--thumb">
			<div class="cpl-list-item--thumb--canvas">
				<?php if ( $item['thumb'] ) : ?>
					<img height="50%" alt="Richard Ellis logo" src="<?php echo esc_url( $item['thumb'] ); ?>">
				<?php endif; ?>
			</div>
		</div>


	<div class="cpl-list-item--details">
		<h3 class="cpl-list-item--title"><a href="<?php the_permalink(); ?>"><?php echo $item['title']; ?></a></h3>

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
		</div>
	</div>

	<div class="cpl_item_actions" data-item="<?php echo esc_attr( json_encode( $item ) ); ?>" ></div>

</div>
