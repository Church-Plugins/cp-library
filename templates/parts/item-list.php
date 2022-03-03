<?php

try {
	$item = new \CP_Library\Controllers\Item( get_the_ID() );
	$item = $item->get_api_data();
} catch ( \CP_Library\Exception $e ) {
	error_log( $e );

	return;
}
?>

<?php if ( $item['thumb'] ) : ?>
	<div class="cplItem__thumb MuiBox-root css-1vvtltt">
		<div class="MuiBox-root css-8drbq8">
			<img height="50%" alt="Richard Ellis logo" src="<?php echo esc_url( $item['thumb'] ); ?>">
		</div>
	</div>
<?php endif; ?>


<div class="cplItem__details MuiBox-root css-glsxm8">
	<h3 class="cplItem__title"><a href="<?php the_permalink(); ?>"><?php echo $item['title']; ?></a></h3>

	<div class="cplItem__itemMeta MuiBox-root css-164r41r">
		<div class="itemMeta__relativeReleaseDate MuiBox-root css-1qxmfv1">
			<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none"
				 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
				<rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
				<line x1="16" y1="2" x2="16" y2="6"></line>
				<line x1="8" y1="2" x2="8" y2="6"></line>
				<line x1="3" y1="10" x2="21" y2="10"></line>
			</svg>
			<span class="MuiBox-root css-1isemmb"><?php echo $item['date']->format( 'Y-m-d' ); ?></span>
		</div>
		<div class="itemMeta__categories MuiBox-root css-18biwo">
			<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none"
				 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
				<path
					d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
				<line x1="7" y1="7" x2="7.01" y2="7"></line>
			</svg>
			<span class="MuiBox-root css-1isemmb"><?php echo implode( ', ', $item['category'] ); ?></span>
		</div>
	</div>
</div>

<div class="cplItem__actions MuiBox-root css-9mul5i">
	<div class="MuiBox-root css-d0uhtl">
		<?php if ( $item['audio'] ) : ?>
			<button
				class="MuiButton-root MuiButton-outlined MuiButton-outlinedPrimary MuiButton-sizeMedium MuiButton-outlinedSizeMedium MuiButtonBase-root rectangularButton__root rectangularButton__outlined css-196jlcu"
				tabindex="0" type="button"><span
					class="MuiButton-startIcon MuiButton-iconSizeMedium css-6xugel"><svg
						xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
						fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
						stroke-linejoin="round"><polygon
							points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path
							d="M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg></span>Play Audio<span
					class="MuiTouchRipple-root css-w0pj6f"></span></button>
		<?php endif; ?>
	</div>
</div>

