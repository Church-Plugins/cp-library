<?php
use ChurchPlugins\Helpers;

global $post;
$original_post = $post;

try {
	$item = new \CP_Library\Controllers\Item( get_the_ID() );
	$player_data = $item->get_player_data();
	$item_data = $item->get_api_data();
} catch ( \CP_Library\Exception $e ) {
	error_log( $e );
	return;
}

$classes = [ 'cpl-list-item' ];

if ( $item->has_variations() ) {
	$classes[] = 'cpl-list-item--has-variations';
}
?>

<div <?php post_class( $classes ); ?>>

	<div class="cpl-list-item--thumb" onclick="window.location = jQuery(this).parent().find('.cpl-list-item--title a').attr('href');">
		<div class="cpl-list-item--thumb--canvas" style="background: url(<?php echo esc_url( $item_data['thumb'] ); ?>) 0% 0% / cover;">
			<?php if ( $item_data['thumb'] ) : ?>
				<img alt="<?php esc_attr( $item_data['title'] ); ?>" src="<?php echo esc_url( $item_data['thumb'] ); ?>">
			<?php endif; ?>
		</div>
	</div>

	<div class="cpl-list-item--main">

		<?php if ( $item->has_variations() ) : ?>
			<div class="cpl-list-item--details">

				<?php if ( ! empty( $item_data['types'] ) ) : // for mobile ?>
					<div class="cpl-info">
						<div class="cpl-item--types">
							<?php echo Helpers::get_icon( 'type' ); ?>
							<?php foreach ( $item_data['types'] as $type ) : ?>
								<a href="<?php echo esc_url( $type['permalink'] ); ?>"><?php echo esc_html( $type['title'] ); ?></a>
								<span class="cpl-separator">,&nbsp;</span>
							<?php endforeach; ?>
						</div>
					</div>
				<?php endif; ?>

				<h3 class="cpl-list-item--title"><a href="<?php the_permalink(); ?>"><?php echo $item_data['title']; ?></a></h3>

				<?php cp_library()->templates->get_template_part( 'parts/item-single/meta' ); ?>

			</div>

			<div class="cpl-list-item--variations">
				<?php
				$variations = $item->get_variations();
				foreach ( $variations as $variation_id ) :
					$post = get_post( $variation_id );
					try {
						$variant = new \CP_Library\Controllers\Item( get_the_ID() );
						$variant_player_data = $variant->get_player_data();
						$variant_data = $variant->get_api_data();
					} catch ( \CP_Library\Exception $e ) {
						error_log( $e );
						continue;
					}

					if (
						empty( $variant_data['audio'] ) &&
						empty( $variant_data['video']['value'] ) &&
						empty( $variant_data['speakers'] )
					) {
						continue;
					}
					?>

					<div class="cpl-list-item--columns">
						<div class="cpl-list-item--details">
							<h6 class="cpl-list-item--variations--title"><?php echo $variant->get_variation_source_label(); ?></h6>
							<?php cp_library()->templates->get_template_part( 'parts/item-single/info', [ 'item' => $variant_data ] ); ?>
						</div>

						<div class="cpl_item_actions cpl-item--actions" data-item="<?php echo esc_attr( json_encode( $variant_player_data ) ); ?>"></div>
					</div>
				<?php endforeach; $post = $original_post; ?>
			</div>

		<?php else : ?>
			<div class="cpl-list-item--columns">

				<div class="cpl-list-item--details">

					<?php if ( 0 && ! empty( $item_data['types'] ) ) : // for mobile ?>
						<div class="cpl-info">
							<div class="cpl-item--types">
								<?php echo Helpers::get_icon( 'type' ); ?>
								<?php foreach ( $item_data['types'] as $type ) : ?>
									<a href="<?php echo esc_url( $type['permalink'] ); ?>"><?php echo esc_html( $type['title'] ); ?></a>
									<span class="cpl-separator">,&nbsp;</span>
								<?php endforeach; ?>
							</div>
						</div>
					<?php endif; ?>

					<h3 class="cpl-list-item--title"><a href="<?php the_permalink(); ?>"><?php echo $item_data['title']; ?></a></h3>

					<?php cp_library()->templates->get_template_part( 'parts/item-single/info' ); ?>

					<?php cp_library()->templates->get_template_part( 'parts/item-single/meta' ); ?>

				</div>

				<div class="cpl_item_actions cpl-item--actions" data-item="<?php echo esc_attr( json_encode( $player_data ) ); ?>" ></div>

			</div>

		<?php endif; ?>

	</div>


</div>
