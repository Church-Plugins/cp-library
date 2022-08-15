<?php
use ChurchPlugins\Helpers;
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

		<?php if ( ! empty( $item['types'] ) ) : // for mobile ?>
			<div class="cpl-info">
				<div class="cpl-item--types">
					<?php echo Helpers::get_icon( 'type' ); ?>
					<?php foreach ( $item['types'] as $type ) : ?>
						<a href="<?php echo esc_url( $type['permalink'] ); ?>"><?php echo esc_html( $type['title'] ); ?></a>
						<span class="cpl-separator">,&nbsp;</span>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>

		<h3 class="cpl-list-item--title"><a href="<?php the_permalink(); ?>"><?php echo $item['title']; ?></a></h3>

		<?php \CP_Library\Templates::get_template_part( 'parts/item-single/info' ); ?>

		<?php \CP_Library\Templates::get_template_part( 'parts/item-single/meta' ); ?>
	</div>

	<div class="cpl_item_actions" data-item="<?php echo esc_attr( json_encode( $item ) ); ?>" ></div>

</div>
