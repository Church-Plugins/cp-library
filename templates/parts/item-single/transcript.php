<?php

use CP_Library\Admin\Settings;

if ( ! Settings::get_item( 'show_transcript', false ) ) {
	return;
}

if ( ! $transcript = get_post_meta( get_the_ID(), 'transcript', true ) ) {
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
