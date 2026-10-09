<?php

use CP_Library\Admin\Settings;
use CP_Library\Controllers\Item;

if ( ! Settings::get_item( 'show_transcript', false ) ) {
	return;
}

$transcript = Item::transcript_for_output( get_the_ID() );

if ( ! $transcript ) {
	return;
}

?>
<div class="cpl-item--transcript cpl-transcript">
	<h4 class="cpl-transcript--heading"><?php _e( 'Transcript', 'cp-library' ); ?></h4>

	<div class="cpl-transcript--content">
		<?php echo apply_filters( 'the_content', wp_kses_post( $transcript ) ); ?>
	</div>

	<button class="cpl-transcript--toggle cp-button"><?php _e( 'Show Transcript', 'cp-library' ); ?></button>
</div>
