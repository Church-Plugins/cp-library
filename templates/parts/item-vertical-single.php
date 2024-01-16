<?php
use ChurchPlugins\Helpers;

try {
	$item = new \CP_Library\Controllers\Item( get_the_ID() );
	$item = $item->get_api_data( true );
} catch ( \CP_Library\Exception $e ) {
	error_log( $e );
	return;
}

?>

<?php do_action( 'cpl_single_item_before', $item ); ?>

<div class="cpl-single-item">

	<div
		class="cpl-single-item--hero"
		style="background-image: url(<?php echo esc_url( $item['thumb'] ); ?>)"
	>
		<div class="cpl-single-item--hero-overlay"></div>
		<div class="cpl-columns">
			<div class="cpl-single-item--media">
				<div class="cpl_item_player" data-item="<?php echo esc_attr( json_encode( $item ) ); ?>"></div>
			</div>
		</div>
	</div>

	<div class="cpl-single-item--details">
		<?php \CP_Library\Templates::get_template_part( 'parts/item-single/meta' ); ?>

		<h1 class="cpl-single-item--title">
			<?php the_title(); ?>
		</h1>

		<?php \CP_Library\Templates::get_template_part( 'parts/item-single/info' ); ?>

		<?php \CP_Library\Templates::get_template_part( 'parts/item-single/attachments' ); ?>

		<div class="cpl-single-item--desc">
			<?php the_content(); ?>
		</div>

		<hr />

		<a
			class="back-link cpl-single-item--back cpl-single--back"
			href="<?php echo esc_url( get_post_type_archive_link( cp_library()->setup->post_types->item->post_type ) ); ?>"
		>
			<?php printf( __( 'Back to All %s', 'cp-library' ), cp_library()->setup->post_types->item->plural_label ); ?>
		</a>
	</div>
	
</div>

<?php do_action( 'cpl_single_item_after', $item ); ?>

