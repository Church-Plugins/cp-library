<?php
/**
 * Service Type Single Template
 *
 * @since 1.4.3
 * @package CP_Library
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CP_Library\Templates;

$page = get_query_var( 'cpl_page' ) ? get_query_var( 'cpl_page' ) : 1;

$query_args = array(
	'cpl_is_facetable'  => true,
	'cpl_service_types' => get_the_ID(),
	'post_type'         => cp_library()->setup->post_types->item->post_type,
	'posts_per_page'    => 20,
	'paged'             => $page,
	's'                 => isset( $_GET['search'] ) ? sanitize_text_field( $_GET['search'] ) : '',
);

$query_args = array_merge( $query_args, $_GET );

$item_query  = new WP_Query( $query_args );
$description = get_the_archive_description();
$type        = get_post_type_object( cp_library()->setup->post_types->item->post_type );
?>

<div class="cpl-archive cpl-archive--item">

	<?php do_action( 'cpl_before_archive' ); ?>
	<?php do_action( 'cpl_before_archive_item' ); ?>

	<div class="cpl-archive--header">
		<h1 class="page-title"><?php echo esc_html( get_the_title() ); ?></h1>
	</div>

	<div class="cpl-archive--container">

		<div class="cpl-archive--container--filter">
			<?php
			Templates::get_template_part(
				'parts/filter',
				[
					'query_vars'      => $item_query->query_vars,
					'exclude_filters' => [ 'service_type' ],
				]
			);
			?>
		</div>

		<div class="cpl-archive--container--list">
			<?php Templates::get_template_part( 'parts/filter-selected' ); ?>

			<div class="cpl-archive--list">
				<?php if ( $item_query->have_posts() ) { ?>
					<?php while ( $item_query->have_posts() ) : $item_query->the_post(); ?>
						<div class="cpl-archive--list--item">
							<?php Templates::get_template_part( 'parts/' . Templates::get_type() . '-list' ); ?>
						</div>
					<?php endwhile; ?>
				<?php } elseif ( ! empty( $type ) && is_object( $type ) && ! empty( $type->plural_label ) ) { ?>
						<p><?php echo esc_html( sprintf( __( 'No %s found.', 'cp-library' ), $type->plural_label ) ); ?></p>
				<?php }; ?>
				<?php wp_reset_postdata(); ?>
			</div>
		</div>

		<div class="pagination nav-links">
			<?php
			echo paginate_links( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
				array(
					'base'      => get_permalink() . '?cpl_page=%#%',
					'format'    => '?cpl_page=%#%',
					'current'   => max( 1, get_query_var( 'cpl_page' ) ),
					'total'     => $item_query->max_num_pages,
					'prev_text' => __( 'Previous', 'cp-library' ),
					'next_text' => __( 'Next', 'cp-library' ),
				)
			);
			?>
		</div>
	</div>

	<?php do_action( 'cpl_after_archive' ); ?>
	<?php do_action( 'cpl_after_archive_item' ); ?>
</div>
