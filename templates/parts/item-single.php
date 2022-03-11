<?php
try {
	$item = new \CP_Library\Controllers\Item( get_the_ID() );
	$item = $item->get_api_data();
} catch ( \CP_Library\Exception $e ) {
	error_log( $e );

	return;
}
?>

<a class="back-link cpl-single-item--back" href="<?php echo get_post_type_archive_link( cp_library()->setup->post_types->item->post_type ); ?>"><?php printf( __('Back to %s', 'cp-library' ), strtolower( cp_library()->setup->post_types->item->plural_label ) ); ?></a>

<div class="cpl-single-item">

	<?php \CP_Library\Templates::get_template_part( 'parts/item-single/content' ); ?>

	<div class="cpl-single-item--media">
		<div class="itemDetail__rightContent cpl_item_player" data-item="<?php echo esc_attr( json_encode( $item ) ); ?>">
		</div>
	</div>

</div>
