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

add_filter( 'post_type_link', function( $link, $post ) {
	if ( get_post_type( $post ) != cp_library()->setup->post_types->item->post_type ) {
		return $link;
	}

	$item_type = get_queried_object();
	return trailingslashit( get_permalink( $item_type ) . $post->post_name );
}, 10, 2 );

?>

<a class="back-link cpl-single-type--back" href="<?php echo get_post_type_archive_link( cp_library()->setup->post_types->item_type->post_type ); ?>"><?php printf( __('Back to %s', 'cp-library' ), strtolower( cp_library()->setup->post_types->item_type->plural_label ) ); ?></a>

<div class="cpl-single-type">
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

				<div class="cpl-single-type--meta">
					<div class="cpl-single-type--meta--date">
						<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none"
							 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
							<line x1="16" y1="2" x2="16" y2="6"></line>
							<line x1="8" y1="2" x2="8" y2="6"></line>
							<line x1="3" y1="10" x2="21" y2="10"></line>
						</svg>
						<span class="MuiBox-root css-1isemmb"><?php echo $item_type["date"]->format('Y-m-d'); ?></span></div>
					<div class="cpl-single-type--meta--categories">
						<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none"
							 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
							<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
							<line x1="7" y1="7" x2="7.01" y2="7"></line>
						</svg>
						<span class="MuiBox-root css-1isemmb"><?php echo implode( ', ', $item_type['scripture'] ); ?></span>
					</div>
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
