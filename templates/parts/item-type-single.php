<?php
global $post;
$original_post = $post;

try {
	$item_type = new \CP_Library\Controllers\ItemType( get_the_ID() );
	$item_type = $item_type->get_api_data();
	$selected_item = get_query_var( 'type-item' );

	if ( $selected_item ) {
		$selected_item = get_page_by_path( $selected_item, OBJECT, cp_library()->setup->post_types->item->post_type );
	}

} catch ( \CP_Library\Exception $e ) {
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

<div class="cpl-single-type" onclick="window.location = jQuery(this).find('a').attr('href');">
	<?php if ( $selected_item ) : ?>
		<?php
		$post = $selected_item;
		setup_postdata( $post );

		\CP_Library\Templates::get_template_part( 'parts/item-single' );

		$post = $original_post;
		wp_reset_postdata();
		?>
	<?php else : ?>
		<div class="cpl-single-type--container">
			<div class="cpl-single-type--media">

				<div class="itemDetail__rightContent MuiBox-root css-1la7bni">
					<div class="itemDetail__featureImage MuiBox-root css-iy0loh">
						<div class="itemPlayer__video MuiBox-root css-122y91a">
							<div class="itemDetail__video" style="width: 100%; height: 100%;"></div>
							<div class="itemDetail__audio MuiBox-root css-1aueuth">
								<img alt="Richard Ellis logo" src="<?php echo $item_type['thumb']; ?>">
							</div>
						</div>
					</div>
				</div>

			</div>

			<div class="cpl-single-type--content">
				<div class="cpl-single-type--title">
					<h1><?php the_title(); ?></h1>
				</div>

				<div class="cpl-meta">
					<div class="cpl-meta--date">
						<span class="material-icons-outlined">calendar_today</span>

						<span class="MuiBox-root css-1isemmb"><?php echo $item_type["date"]["desc"]; ?></span>
					</div>

					<?php if ( ! empty( $item_type['topics'] ) ) : ?>
						<div class="cpl-meta--topics">
							<span class="material-icons-outlined">sell</span>

							<?php foreach ( $item_type['topics'] as $topic ) : ?>
								<a href="<?php echo esc_url( $topic['url'] ); ?>"><?php echo esc_html( $topic['name'] ); ?></a>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>
				</div>

				<div class="cpl-single-type--desc">
					<?php the_content(); ?>
				</div>
			</div>


		</div>
	<?php endif; ?>

	<h2 class="cpl-single-type--items-title"><?php printf( __( '%s: %s', 'cp-library' ), cp_library()->setup->post_types->item_type->plural_label, get_the_title() ); ?></h2>

	<section class="cpl-single-type--items">
		<?php foreach ( $item_type['items'] as $item ) : $post = get_post( $item['originID'] );setup_postdata( $post ); ?>
			<?php \CP_Library\Templates::get_template_part( "parts/item-card" ); ?>
		<?php endforeach; $post = $original_post; wp_reset_postdata(); ?>
	</section>

</div>

<?php do_action( 'cpl_single_type_after', $item_type ); ?>
