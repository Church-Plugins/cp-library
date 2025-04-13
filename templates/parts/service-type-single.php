<?php
/**
 * Template part for displaying a single service type and its sermons
 */

use CP_Library\Admin\Settings;
use CP_Library\Controllers\ServiceType;

$service_type_id = get_the_ID();

// Try to get the service type controller
try {
	$service_type = new ServiceType( $service_type_id );
} catch ( Exception $e ) {
	// If we can't get it, show an error
	echo '<p>' . __( 'Service type not found.', 'cp-library' ) . '</p>';
	error_log( $e->getMessage() );

	return;
}

// Get service type data using the controller
$title       = $service_type->get_title();
$description = $service_type->get_content();

// CSS classes
$classes = [ 'cpl-service-type-single' ];
$classes = apply_filters( 'cpl_service_type_single_classes', $classes, $service_type );

// Get sermons for this service type
// We'll use a custom WP_Query since we don't want to modify the main query
$paged          = ( get_query_var( 'paged' ) ) ? get_query_var( 'paged' ) : 1;
$posts_per_page = Settings::get_item( 'per_page', 12 );

// Get all sermon IDs for this service type
$sermon_ids = $service_type->get_items();

// If we have sermons, run the query
if ( ! empty( $sermon_ids ) ) {
	$sermons_args = array(
		'post_type'         => cp_library()->setup->post_types->item->post_type,
		'post__in'          => $sermon_ids,
		'posts_per_page'    => $posts_per_page,
		'paged'             => $paged,
		'orderby'           => 'date',
		'order'             => 'DESC',
		'cpl_service_types' => $service_type_id
	);

	// Create a new query
	$sermons_query = new WP_Query( $sermons_args );
} else {
	// Create an empty query if no sermons
	$sermons_query = new WP_Query();
}
?>

<article id="service-type-<?php echo esc_attr( $service_type_id ); ?>"
		 class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">

	<header class="cpl-service-type-single--header">
		<h1 class="cpl-service-type-single--title"><?php echo esc_html( $title ); ?></h1>
	</header>

	<?php if ( ! empty( $description ) ) : ?>
		<div class="cpl-service-type-single--content">
			<?php echo $description; ?>
		</div>
	<?php endif; ?>

	<div class="cpl-service-type-single--sermons">
		<h2><?php echo esc_html( cp_library()->setup->post_types->item->plural_label ); ?></h2>

		<div class="cpl-archive--container">
			<div class="cpl-archive--container--filter">
				<?php
				// Use the new filter system for service type context
				$disabled_filters   = Settings::get_advanced( 'disable_filters', [] );
				$disabled_filters[] = 'service-type'; // Always disable service-type filter in this context

				echo cp_library()->filters->render_filter_form( [
					'context'          => 'service-type',
					'context_args'     => [ 'service_type_id' => $service_type_id ],
					'disabled_filters' => $disabled_filters,
					'container_class'  => 'cpl-service-type-filter',
				] );
				?>
			</div>

			<div class="cpl-archive--container--list">
				<?php echo cp_library()->filters->render_selected_filters( [
					'context'      => 'service-type',
					'context_args' => [ 'service_type_id' => $service_type_id ],
				] ); ?>

				<div class="cpl-archive--list">
					<?php if ( $sermons_query->have_posts() ) : ?>
						<?php while ( $sermons_query->have_posts() ) : $sermons_query->the_post(); ?>
							<div class="cpl-archive--list--item">
								<?php cp_library()->templates->get_template_part( "parts/item-list" ); ?>
							</div>
						<?php endwhile; ?>
						<?php wp_reset_postdata(); ?>
					<?php else : ?>
						<p>
							<?php
							printf(
								__( 'No %s found for this service type.', 'cp-library' ),
								strtolower( cp_library()->setup->post_types->item->plural_label )
							);
							?>
						</p>
					<?php endif; ?>
				</div>

				<?php
				// Custom pagination for our secondary query
				$big = 999999999;
				echo '<div class="navigation pagination">';
				echo '<div class="nav-links">';
				echo paginate_links( array(
					'base'      => str_replace( $big, '%#%', esc_url( get_pagenum_link( $big ) ) ),
					'format'    => '?paged=%#%',
					'current'   => max( 1, $paged ),
					'total'     => $sermons_query->max_num_pages,
					'prev_text' => '&laquo;',
					'next_text' => '&raquo;'
				) );
				echo '</div>';
				echo '</div>';
				?>
			</div>
		</div>
	</div>
</article>
