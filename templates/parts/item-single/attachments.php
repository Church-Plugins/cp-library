<?php
/**
 * Sermon Attachments template
 *
 * @since 1.4.3
 * @package cp-library
 */
use ChurchPlugins\Helpers;
$downloads = get_post_meta( get_the_ID(), 'downloads', true );
?>

<?php if ( ! empty( $downloads ) ) : ?>
	<div class="cpl-single-item--attachments">
		<?php foreach ( $downloads as $download ) :
			// is the file link on a different domain and not relative?
			$external = str_contains( $download['file'], 'http' ) && ! str_contains( $download['file'], home_url() );
			$icon = $external ? 'launch' : 'file';
			?>
			<a class="cpl-single-item--attachment cp-button is-light <?php echo $external ? 'is-external' : ''; ?>" href="<?php echo esc_url( $download['file'] ); ?>" target="_blank" rel="noopener noreferrer">
				<?php echo Helpers::get_icon( $icon ); ?>
				<?php echo esc_html( empty( $download['name'] ) ? basename( $download['file'] ) : $download['name'] ); ?>
			</a>
		<?php endforeach; ?>
	</div>
<?php endif; ?>
