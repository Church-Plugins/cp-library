<?php
use ChurchPlugins\Helpers;

try {
	$item = new \CP_Library\Controllers\Item( get_the_ID() );
	$item = $item->get_api_data();
} catch ( \CP_Library\Exception $e ) {
	error_log( $e );

	return;
}
?>

<div class="cpl-meta">
	<div class="cpl-meta--date">
		<?php echo Helpers::get_icon( 'date' ); ?>

		<span><?php echo $item["date"]["desc"]; ?></span>
	</div>

	<?php if ( ! empty( $item['topics'] ) ) : ?>
		<div class="cpl-meta--topics">
			<?php echo Helpers::get_icon( 'topics' ); ?>

			<?php foreach ( $item['topics'] as $topic ) : ?>
				<a href="<?php echo esc_url( $topic['url'] ); ?>"><?php echo esc_html( $topic['name'] ); ?></a>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $item['scripture'] ) ) : ?>
		<div class="cpl-meta--topics">
			<?php echo Helpers::get_icon( 'scripture' ); ?>

			<?php foreach ( $item['scripture'] as $scripture ) : ?>
				<a href="<?php echo esc_url( $scripture['url'] ); ?>"><?php echo esc_html( $scripture['name'] ); ?></a>
				<span class="cpl-separator">, </span>
			<?php endforeach; ?>
		</div>
	<?php endif; ?>
</div>

