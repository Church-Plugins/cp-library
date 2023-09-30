<?php
use ChurchPlugins\Helpers;
use CP_Library\Templates;

try {
	$speaker = \CP_Library\Models\Speaker::get_instance_from_origin( get_the_ID() );
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

<?php do_action( 'cpl_single_speaker_before', $speaker ); ?>

<div class="cpl-single-item">

	<div class="cpl-single-item--details">
		<div class="cpl-single-item--title">
			<h1><?php the_title(); ?></h1>
		</div>

		<hr>

		<?php the_post_thumbnail( [ 350, 350 ], array( 'style' => 'float: right; margin-left: var(--cp-gap--sm); margin-bottom: var(--cp-gap--sm);' ) ) ?>	
		
		<div class="cpl-single-item--desc">
			<?php the_content(); ?>
		</div>
	</div>

	<hr>

	<h3><?php echo sprintf( esc_html__( '%s by %s', 'cp-library' ), cp_library()->setup->post_types->item->plural_label, get_the_title() ) ?></h3>

	<?php foreach( $speaker->get_all_items() as $item ): ?>
		<?php global $post; ?>
		<?php $post = get_post( $item ); ?>
		<?php setup_postdata( $post ); ?>
		<?php Templates::get_template_part( 'parts/item-list' ); ?>
	<?php endforeach; ?>
	<?php wp_reset_postdata(); ?>
</div>

<?php do_action( 'cpl_single_speaker_after', $speaker ); ?>
