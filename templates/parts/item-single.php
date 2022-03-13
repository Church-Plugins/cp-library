<?php
try {
	$item = new \CP_Library\Controllers\Item( get_the_ID() );
	$item = $item->get_api_data();
} catch ( \CP_Library\Exception $e ) {
	error_log( $e );
	return;
}

function cpl_item_back() {
	?>
	<a class="back-link cpl-single-item--back" href="<?php echo get_post_type_archive_link( cp_library()->setup->post_types->item->post_type ); ?>"><?php printf( __('Back to all %s', 'cp-library' ), strtolower( cp_library()->setup->post_types->item->plural_label ) ); ?></a>
	<?php
}
add_action( 'cpl_single_item_before', 'cpl_item_back' );
?>

<?php do_action( 'cpl_single_item_before', $item ); ?>

<div class="cpl-single-item">

	<?php \CP_Library\Templates::get_template_part( 'parts/item-single/content' ); ?>

	<div class="cpl-single-item--media">
		<div class="itemDetail__rightContent cpl_item_player" data-item="<?php echo esc_attr( json_encode( $item ) ); ?>"></div>
	</div>

</div>

<?php do_action( 'cpl_single_item_after', $item ); ?>
