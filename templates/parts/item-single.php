<?php
use ChurchPlugins\Helpers;

try {
	$item = new \CP_Library\Controllers\Item( get_the_ID() );
	$player_data = $item->get_player_data( true );
	$item = $item->get_api_data( true );
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

			<?php cp_library()->templates->get_template_part( 'parts/item-single/meta' ); ?>

			<div class="cpl-single-item--title">
				<h1><?php the_title(); ?></h1>
			</div>

			<?php cp_library()->templates->get_template_part( 'parts/item-single/info' ); ?>

			<?php cp_library()->templates->get_template_part( 'parts/item-single/attachments' ); ?>

			<div class="cpl-single-item--desc">
				<?php the_content(); ?>
			</div>
		</div>

		<div class="cpl-single-item--media">
			<div class="itemDetail__rightContent cpl_item_player" data-item="<?php echo esc_attr( json_encode( $player_data ) ); ?>"></div>
		</div>
	</div>

	<?php cp_library()->templates->get_template_part( 'parts/item-single/transcript' ); ?>

</div>

<?php do_action( 'cpl_single_item_after', $item ); ?>
