<?php
use ChurchPlugins\Helpers;

try {
	$item = new \CP_Library\Controllers\Item( get_the_ID() );
	$item = $item->get_api_data();
} catch ( \CP_Library\Exception $e ) {
	error_log( $e );
	return;
}

if ( ! function_exists( 'cpl_item_back' ) ) {
	function cpl_item_back() {
		?>
		<a class="back-link cpl-single-item--back cpl-single--back"
		   href="<?php echo get_post_type_archive_link( cp_library()->setup->post_types->item->post_type ); ?>"><?php printf( __( 'Back to All %s', 'cp-library' ), cp_library()->setup->post_types->item->plural_label ); ?></a>
		<?php
	}
}
add_action( 'cpl_single_item_before', 'cpl_item_back' );
?>

<?php do_action( 'cpl_single_item_before', $item ); ?>

<div class="cpl-single-item">

	<div class="cpl-columns">

		<div class="cpl-single-item--details">

			<?php \CP_Library\Templates::get_template_part( 'parts/item-single/meta' ); ?>

			<div class="cpl-single-item--title">
				<h1><?php the_title(); ?></h1>
			</div>

			<div class="cpl-single-item--info cpl-info">
				<?php if ( ! empty( $item['speakers'] ) ) : ?>
					<div class="cpl-single-item--speakers">
						<?php echo Helpers::get_icon( 'speaker' ); ?>
						<?php echo implode( ', ', wp_list_pluck( $item['speakers'], 'title' ) ); ?>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $item['types'] ) ) : ?>
					<div class="cpl-single-item--types">
						<?php echo Helpers::get_icon( 'type' ); ?>
						<?php foreach ( $item['types'] as $type ) : ?>
							<a href="<?php echo esc_url( $type['permalink'] ); ?>"><?php echo esc_html( $type['title'] ); ?></a>
							<span class="cpl-separator">, </span>
						<?php endforeach; ?>
					</div>
				<?php endif; ?>
			</div>

			<div class="cpl-single-item--desc">
				<?php the_content(); ?>
			</div>
		</div>

		<div class="cpl-single-item--media">
			<div class="itemDetail__rightContent cpl_item_player" data-item="<?php echo esc_attr( json_encode( $item ) ); ?>"></div>
		</div>
	</div>

</div>

<?php do_action( 'cpl_single_item_after', $item ); ?>
