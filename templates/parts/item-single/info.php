<?php
use ChurchPlugins\Helpers;

if ( empty( $args['item'] ) ) {
	try {
		$item = new \CP_Library\Controllers\Item( get_the_ID() );
		$item = $item->get_api_data();
	} catch ( \CP_Library\Exception $e ) {
		error_log( $e );

		return;
	}
} else {
	$item = $args['item'];
}
?>
<div class="cpl-item--info cpl-info">
	<?php if ( ! empty( $item['speakers'] ) ) : ?>
		<div class="cpl-item--speakers">
			<?php echo Helpers::get_icon( 'speaker' ); ?>
			<?php echo implode( ', ', wp_list_pluck( $item['speakers'], 'title' ) ); ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $item['types'] ) ) : ?>
		<div class="cpl-item--types">
			<?php echo Helpers::get_icon( 'type' ); ?>
			<?php foreach ( $item['types'] as $type ) : ?>
				<a href="<?php echo esc_url( $type['permalink'] ); ?>"><?php echo esc_html( $type['title'] ); ?></a>
				<span class="cpl-separator">, </span>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>


