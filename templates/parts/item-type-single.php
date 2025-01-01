<?php
global $post;
$original_post = $post;

try {
	$item_type          = new \CP_Library\Controllers\ItemType( get_the_ID() );
	$item_type          = $item_type->get_api_data();
	$selected_item_slug = get_query_var( 'type-item' );
	$selected_item      = false;

	if ( is_array( $item_type['items'] ) ) {
		foreach( $item_type['items'] as $item ) {
			if ( $selected_item_slug === $item['slug'] ) {
				$selected_item = get_post( $item['originID'] );
				break;
			}
		}
	}

} catch ( \CP_Library\Exception | \ChurchPlugins\Exception $e ) {
	error_log( $e );
	return;
}

/**
 * Add back button for type template
 *
 * @since  1.0.0
 *
 * @author Tanner Moushey
 */
function cpl_item_type_back() {

	if ( get_query_var( 'type-item' ) ) : ?>
		<a class="back-link cpl-single-type--back" href="<?php echo get_the_permalink(); ?>"><?php printf( __('Back to %s overview', 'cp-library' ), strtolower( cp_library()->setup->post_types->item_type->single_label ) ); ?></a>
	<?php else : ?>
		<a class="back-link cpl-single-type--back" href="<?php echo get_post_type_archive_link( cp_library()->setup->post_types->item_type->post_type ); ?>"><?php printf( __('Back to all %s', 'cp-library' ), strtolower( cp_library()->setup->post_types->item_type->plural_label ) ); ?></a>
	<?php
	endif;
}
add_action( 'cpl_single_type_before', 'cpl_item_type_back' );

/**
 * Remove the before link on the item-single template
 */
add_action( 'cpl_single_item_before', function() {
	remove_action( 'cpl_single_item_before', 'cpl_item_back' );
}, 5 );

/**
 * Customize item link to contain item type link
 */
function cpl_item_type_item_link ( $link, $post ) {
	if ( get_post_type( $post ) != cp_library()->setup->post_types->item->post_type ) {
		return $link;
	}

	$item_type = get_queried_object();
	return trailingslashit( get_permalink( $item_type ) . $post->post_name );
}
add_filter( 'post_type_link', 'cpl_item_type_item_link', 10, 2 );
?>

<?php do_action( 'cpl_single_type_before', $item_type ); ?>

<div class="cpl-single-type">
	<?php if ( $selected_item ) : ?>
		<?php
		$post = $selected_item;
		setup_postdata( $post );

		$template = 'item' . \CP_Library\Admin\Settings::get_item( 'single_template', '' ) . '-single';
		\CP_Library\Templates::get_template_part( "parts/$template" );

		$post = $original_post;
		wp_reset_postdata();
		?>
	<?php else : ?>
		<div class="cpl-single-type--container">
			<div class="cpl-single-type--media">
				<img alt="site logo" src="<?php echo $item_type['thumb']; ?>">
			</div>

			<div class="cpl-single-type--content">
				<div class="cpl-meta">
					<div class="cpl-meta--date">
						<span class="material-icons-outlined">calendar_today</span>

						<span class="MuiBox-root css-1isemmb"><?php echo ( $item_type["date"]["first"] == $item_type["date"]["last"] ) ? $item_type["date"]["first"] : sprintf( "%s - %s", $item_type["date"]["first"], $item_type["date"]["last"] ); ?></span>
					</div>

					<?php if ( ! empty( $item_type['topics'] ) ) : ?>
						<div class="cpl-meta--topics">
							<span class="material-icons-outlined">sell</span>

							<?php foreach ( $item_type['topics'] as $topic ) : ?>
								<a href="<?php echo esc_url( $topic['url'] ); ?>"><?php echo esc_html( $topic['name'] ); ?></a>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<?php if ( ! empty( $item_type['scripture'] ) ) : ?>
						<div class="cpl-meta--topics">
							<span class="material-icons-outlined">menu_book</span>

							<?php foreach ( $item_type['scripture'] as $scripture ) : ?>
								<a href="<?php echo esc_url( $scripture['url'] ); ?>"><?php echo esc_html( $scripture['name'] ); ?></a><span class="cpl-separator">,&nbsp;</span>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>

				<div class="cpl-single-type--title">
					<h1><?php the_title(); ?></h1>
				</div>

				<div class="cpl-single-type--desc">
					<?php the_content(); ?>
				</div>
			</div>

		</div>
	<?php endif; ?>

	<p class="cpl-single-type--items-title" id="cpl-single-type--items-title"><?php printf( '%s: %s', cp_library()->setup->post_types->item->plural_label, count( $item_type['items'] ) ); ?></p>

	<?php if ( ! empty( $item_type['items'] ) ) : ?>
		<section class="cpl-single-type--items" id="cpl-single-type--items">
			<?php
			$ids  = wp_list_pluck( $item_type['items'], 'originID' );
			$page = get_query_var( 'cpl_page' ) ? get_query_var( 'cpl_page' ) : 1;
			$item_query = new WP_Query( array(
				'post_type' => cp_library()->setup->post_types->item->post_type,
				'post__in' => $ids,
				'orderby' => 'post__in',
				'posts_per_page' => \CP_Library\Admin\Settings::get_item_type( 'items_per_page', 10 ),
				'paged' => $page
			) );
			?>

			<?php while( $item_query->have_posts() ) : $item_query->the_post() ?>
				<?php \CP_Library\Templates::get_template_part( "parts/item-list" ); ?>
			<?php endwhile; ?>

			<?php wp_reset_postdata(); ?>

			<nav class="cpl-single-type--items--pagination et_smooth_scroll_disabled navigation pagination" role="navigation" aria-label="Posts pagination">
				<?php
				echo paginate_links( array(
					'base' => get_permalink() . '?cpl_page=%#%#cpl-single-type--items-title',
					'format' => '?cpl_page=%#%',
					'current' => max( 1, get_query_var( 'cpl_page' ) ),
					'total' => $item_query->max_num_pages
				) );
				?>
			</nav>
		</section>
	<?php endif; ?>
</div>

<?php do_action( 'cpl_single_type_after', $item_type ); ?>
