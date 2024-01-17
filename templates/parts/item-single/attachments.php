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
		<span class="material-icons-outlined">attach_file</span>
		<?php foreach ( $downloads as $download ) : ?>
			<a href="<?php echo esc_url( $download['file'] ); ?>" target="_blank" rel="noopener noreferrer">
				<?php echo esc_html( empty( $download['name'] ) ? $download['file'] : $download['name'] ); ?>
			</a>
		<?php endforeach; ?>
	</div>
<?php endif; ?>