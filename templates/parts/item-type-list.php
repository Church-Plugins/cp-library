<?php

try {
	$item = new \CP_Library\Controllers\ItemType( get_the_ID() );
	$item = $item->get_api_data();
} catch ( \CP_Library\Exception $e ) {
	error_log( $e );

	return;
}
?>

<div class="cpl-list-type">

		<div class="cpl-list-type--thumb">
			<div class="cpl-list-type--thumb--canvas">
				<?php if ( $item['thumb'] ) : ?>
					<img height="50%" alt="Richard Ellis logo" src="<?php echo esc_url( $item['thumb'] ); ?>">
				<?php endif; ?>
			</div>
		</div>


	<div class="cpl-list-type--details">
		<h3 class="cpl-list-type--title"><a href="<?php the_permalink(); ?>"><?php echo $item['title']; ?></a></h3>

		<div class="cpl-meta">
			<div class="cpl-meta--date">
				<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none"
					 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
					<line x1="16" y1="2" x2="16" y2="6"></line>
					<line x1="8" y1="2" x2="8" y2="6"></line>
					<line x1="3" y1="10" x2="21" y2="10"></line>
				</svg>
				<span class="MuiBox-root css-1isemmb"><?php echo $item["date"]["desc"]->format( 'Y-m-d' ); ?></span>
			</div>
			<div class="cpl-meta--topics">
				<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none"
					 stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
					<path
						d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"></path>
					<line x1="7" y1="7" x2="7.01" y2="7"></line>
				</svg>
				<?php echo implode( ', ', $item['topics'] ); ?>
			</div>
		</div>
	</div>

	<div class="cpl-list-type--actions">
		<?php if ( $item['audio'] ) : ?>
			<button  tabindex="0" type="button">
				<span>
					<svg
						xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"
						fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
						stroke-linejoin="round"><polygon
							points="11 5 6 9 2 9 2 15 6 15 11 19 11 5"></polygon><path
							d="M15.54 8.46a5 5 0 0 1 0 7.07"></path></svg></span>Play Audio<span
					class="MuiTouchRipple-root css-w0pj6f">
				</span>
			</button>
		<?php endif; ?>
	</div>

</div>
