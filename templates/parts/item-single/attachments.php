<?php
/**
 * Sermon Attachments template
 *
 * @since 1.4.3
 * @package cp-library
 */

$downloads = get_post_meta( get_the_ID(), 'downloads', true );
?>

<?php if ( ! empty( $downloads ) ) : ?>
	<div class="cpl-single-item--attachments">
		<?php foreach ( $downloads as $download ) : ?>
			<a class="cpl-single-item--attachment cp-button is-light" href="<?php echo esc_url( $download['file'] ); ?>" target="_blank" rel="noopener noreferrer">
				<span class="material-icons-outlined">attach_file</span>
				<?php echo esc_html( empty( $download['name'] ) ? $download['file'] : $download['name'] ); ?>
			</a>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
