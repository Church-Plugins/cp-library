<?php
use ChurchPlugins\Helpers;
use CP_Library\Templates;

try {
	$speaker = \CP_Library\Models\Speaker::get_instance_from_origin( get_the_ID() );
} catch ( \CP_Library\Exception $e ) {
	error_log( $e );
	return;
}

$item_ids = $speaker->get_all_items();

$page       = get_query_var( 'cpl_page' ) ? get_query_var( 'cpl_page' ) : 1;
$item_query = new WP_Query( array(
	'post_type'      => cp_library()->setup->post_types->item->post_type,
	'post__in'       => empty( $item_ids ) ? [ 0 ] : $item_ids,
	'orderby'        => 'post__in',
	'posts_per_page' => \CP_Library\Templates::posts_per_page( cp_library()->setup->post_types->item->post_type ),
	'paged'          => $page
) );

if ( ! function_exists( 'cpl_item_back' ) ) {
	function cpl_item_back() {
		?>
		<a class="back-link cpl-single-item--back cpl-single--back"
		   href="<?php echo get_post_type_archive_link( cp_library()->setup->post_types->item->post_type ); ?>"><?php printf( __( 'Back to All %s', 'cp-library' ), cp_library()->setup->post_types->item->plural_label ); ?></a>
		<?php
	}
}
add_action( 'cpl_single_speaker_before', 'cpl_item_back' );
?>

<?php do_action( 'cpl_single_speaker_before', $speaker ); ?>

<div class="cpl-single-speaker">

	<div class="cpl-single-speaker--details">
		<div class="cpl-single-speaker--image">
			<?php if ( has_post_thumbnail() ) : ?>
				<?php the_post_thumbnail(  'large' ) ?>
			<?php else : ?>
				<img src="<?php echo CP_LIBRARY_PLUGIN_URL . '/assets/images/no-speaker.png'; ?>" />
			<?php endif; ?>
		</div>

		<div class="cpl-single-speaker--info">
			<h1><?php the_title(); ?></h1>
			<?php the_content(); ?>
		</div>
	</div>

	<hr>

	<?php $item_post_type = cp_library()->setup->post_types->item; ?>
	<?php $label = $item_query->found_posts === 1 ? $item_post_type->single_label : $item_post_type->plural_label; ?>
	<h3><?php echo sprintf( esc_html__( '%d %s by %s', 'cp-library' ), $item_query->found_posts, $label, get_the_title() ) ?></h3>

	<?php while ( $item_query->have_posts() ) : $item_query->the_post() ?>
		<?php \CP_Library\Templates::get_template_part( "parts/item-list" ); ?>
	<?php endwhile; ?>

	<?php wp_reset_postdata(); ?>

	<div class="cpl-single-type--items--pagination et_smooth_scroll_disabled">
		<?php
		echo paginate_links( array(
			'base'    => get_permalink() . '?cpl_page=%#%',
			'format'  => '?cpl_page=%#%',
			'current' => max( 1, get_query_var( 'cpl_page' ) ),
			'total'   => $item_query->max_num_pages
		) );
		?>
	</div>
</div>

<?php do_action( 'cpl_single_speaker_after', $speaker ); ?>
