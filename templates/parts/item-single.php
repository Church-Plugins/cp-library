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

		<div class="itemDetail__rightContent MuiBox-root css-1la7bni">
			<div class="itemDetail__featureImage MuiBox-root css-iy0loh">
				<div class="itemPlayer__video MuiBox-root css-122y91a">
					<div class="itemDetail__video" style="width: 100%; height: 100%;"></div>
					<div class="itemDetail__audio MuiBox-root css-1aueuth">
						<img alt="Richard Ellis logo" src="<?php echo $item['thumb']; ?>">
					</div>
				</div>
			</div>
			<div class="itemDetail__actions MuiBox-root css-wfa2ev">
				<div class="itemDetail__playAudio MuiBox-root css-1rr4qq7">
					<button
						class="MuiButton-root MuiButton-outlined MuiButton-outlinedPrimary MuiButton-sizeMedium MuiButton-outlinedSizeMedium MuiButton-fullWidth MuiButtonBase-root rectangularButton__root rectangularButton__outlined css-15w14pg"
						tabindex="0" type="button"><span
							class="MuiButton-startIcon MuiButton-iconSizeMedium css-6xugel"><svg
								xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
								fill="none"
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

	</div>

</div>
