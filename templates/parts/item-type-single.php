<?php
global $post;
$original_post = $post;

try {
	$item_type = new \CP_Library\Controllers\ItemType( get_the_ID() );
	$item_type = $item_type->get_api_data();
	$selected_item = get_query_var( 'type-item' );
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
<div class="MuiBox-root css-1xhj18k">
	<div class="itemDetail__leftContent MuiBox-root css-wy2xt2">
		<h1 class="itemDetail__title"><?php the_title(); ?></h1>
		<div class="itemDetail__itemMeta MuiBox-root css-h5fkc8">
			<div class="itemMeta__relativeReleaseDate MuiBox-root css-1qxmfv1">
				<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none"
					 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
					<line x1="16" y1="2" x2="16" y2="6"></line>
					<line x1="8" y1="2" x2="8" y2="6"></line>
					<line x1="3" y1="10" x2="21" y2="10"></line>
				</svg>
				<span class="MuiBox-root css-1isemmb">2 days ago</span></div>
			<div class="itemMeta__categories MuiBox-root css-18biwo">
				<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none"
					 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
					<line x1="7" y1="7" x2="7.01" y2="7"></line>
				</svg>
				<span class="MuiBox-root css-1isemmb">Doubt,Trusting God</span></div>
		</div>
		<div class="itemDetail__description MuiBox-root css-h5fkc8">
			<?php the_content(); ?>
		</div>
	</div>
	<div class="itemDetail__rightContent MuiBox-root css-1la7bni">
		<div class="itemDetail__featureImage MuiBox-root css-iy0loh">
			<div class="itemPlayer__video MuiBox-root css-122y91a">
				<div class="itemDetail__video" style="width: 100%; height: 100%;"></div>
				<div class="itemDetail__audio MuiBox-root css-1aueuth">
					<img alt="Richard Ellis logo" src="<?php echo $item_type['thumb']; ?>">
				</div>
			</div>
		</div>
		<div class="itemDetail__actions MuiBox-root css-wfa2ev">
			<div class="itemDetail__playAudio MuiBox-root css-1rr4qq7">
				<button
					class="MuiButton-root MuiButton-outlined MuiButton-outlinedPrimary MuiButton-sizeMedium MuiButton-outlinedSizeMedium MuiButton-fullWidth MuiButtonBase-root rectangularButton__root rectangularButton__outlined css-15w14pg"
					tabindex="0" type="button"><span class="MuiButton-startIcon MuiButton-iconSizeMedium css-6xugel"><svg
							xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
							stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon
								points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path
								d="M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg></span>Play Audio<span
						class="MuiTouchRipple-root css-w0pj6f"></span></button>
			</div>
			<div class="itemDetail__share MuiBox-root css-f4ggu8">
				<button
					class="MuiButton-root MuiButton-outlined MuiButton-outlinedPrimary MuiButton-sizeMedium MuiButton-outlinedSizeMedium MuiButtonBase-root rectangularButton__root rectangularButton__outlined css-2ycbmr"
					tabindex="0" type="button">
					<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none"
						 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
						<circle cx="18" cy="5" r="3"></circle>
						<circle cx="6" cy="12" r="3"></circle>
						<circle cx="18" cy="19" r="3"></circle>
						<line x1="8.59" y1="13.51" x2="15.42" y2="17.49"></line>
						<line x1="15.41" y1="6.51" x2="8.59" y2="10.49"></line>
					</svg>
					<span class="MuiTouchRipple-root css-w0pj6f"></span></button>
			</div>
		</div>
	</div>

	<?php foreach( $item_type['items'] as $item ) : $post = get_post( $item['originID'] ); setup_postdata( $post ); ?>
		<?php \CP_Library\Templates::get_template_part( "parts/item-card" ); ?>
	<?php endforeach; $post = $original_post; wp_reset_postdata(); ?>
</div>
