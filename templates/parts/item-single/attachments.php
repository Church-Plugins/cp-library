<?php
/**
 * Sermon Attachments template
 *
 * @since 1.4.3
 * @package cp-library
 */

$notes     = get_post_meta( get_the_ID(), 'notes', true );
$bulletins = get_post_meta( get_the_ID(), 'bulletins', true );
$display   = ! empty( $notes ) || ! empty( $bulletins );

if ( ! empty( $notes ) ) {
	$notes = array_map(
		function ( $note ) {
			return sprintf( '<a href="%s">%s</a>', esc_url( $note['notes_file'] ), esc_html__( 'Download Sermon Notes' ) );
		},
		$notes
	);
}

if ( ! empty( $bulletins ) ) {
	$bulletins = array_map(
		function ( $bulletin ) {
			return sprintf( '<a href="%s">%s</a>', esc_url( $bulletin['bulletin_url'] ), esc_html__( 'Download Bulletin' ) );
		},
		$bulletins
	);
}

if ( ! $display ) {
	return;
}

echo '<div class="cpl-single-item--attachments">';

if ( ! empty( $notes ) ) {
	?>
	<div class="cpl-single-item--notes">
		<span class="material-icons-outlined">note</span>
		<?php echo implode( ', ', $notes ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
	<?php
}


if ( ! empty( $bulletins ) ) {
	?>
	<div class="cpl-single-item--bulletin">
		<span class="material-icons-outlined">link</span>
		<?php echo implode( ', ', $bulletins ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
	</div>
	<?php
}

echo '</div>';