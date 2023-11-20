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
$fields = \CP_Library\Admin\Settings::get_item( 'info_items', [ 'speakers', 'locations', 'types' ] );
?>
<div class="cpl-item--info cpl-info">
	<?php foreach( $fields as $field ) : ?>
		<?php if ( 'date' == $field ) : ?>
			<div class="cpl-info--date">
				<?php echo Helpers::get_icon( 'date' ); ?>

				<span><?php echo $item["date"]["desc"]; ?></span>
			</div>
		<?php elseif ( 'topics' == $field ) : ?>
			<?php if ( ! empty( $item['topics'] ) ) : ?>
				<div class="cpl-info--topics">
					<?php echo Helpers::get_icon( 'topics' ); ?>

					<?php foreach ( $item['topics'] as $topic ) : ?>
						<a href="<?php echo esc_url( $topic['url'] ); ?>"><?php echo esc_html( $topic['name'] ); ?></a>
						<span class="cpl-separator">,&nbsp;</span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		<?php elseif ( 'scripture' == $field ) : ?>
			<?php if ( ! empty( $item['scripture'] ) ) : ?>
				<div class="cpl-info--scripture">
					<?php echo Helpers::get_icon( 'scripture' ); ?>

					<?php foreach ( $item['scripture'] as $scripture ) : ?>
						<a href="<?php echo esc_url( $scripture['url'] ); ?>"><?php echo esc_html( $scripture['name'] ); ?></a>
						<span class="cpl-separator">,&nbsp;</span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		<?php elseif ( 'speakers' == $field ) : ?>
			<?php if ( ! empty( $item['speakers'] ) ) : ?>
				<div class="cpl-info--speakers">
					<?php echo Helpers::get_icon( 'speaker' ); ?>
					<?php echo implode( ', ', wp_list_pluck( $item['speakers'], 'title' ) ); ?>
				</div>
			<?php endif; ?>
		<?php elseif ( 'locations' == $field ) : ?>
			<?php if ( ! empty( $item['locations'] ) ) : ?>
				<div class="cpl-info--locations">
					<?php echo Helpers::get_icon( 'location' ); ?>
					<?php echo implode( ', ', wp_list_pluck( $item['locations'], 'title' ) ); ?>
				</div>
			<?php endif; ?>
		<?php elseif ( 'types' == $field ) : ?>
			<?php if ( ! empty( $item['types'] ) ) : ?>
				<div class="cpl-info--types">
					<?php echo Helpers::get_icon( 'type' ); ?>
					<?php foreach ( $item['types'] as $type ) : ?>
						<a href="<?php echo esc_url( $type['permalink'] ); ?>"><?php echo esc_html( $type['title'] ); ?></a>
						<span class="cpl-separator">,&nbsp;</span>
					<?php endforeach; ?>
				</div>
			<?php endif; ?>
		<?php elseif ( 'service_type' == $field ) : ?>
			<?php if ( ! empty( $item['service_types'] ) ) : ?>
				<div class="cpl-info--service-types">
					<?php echo Helpers::get_icon( 'location' ); ?>
					<?php echo implode( ', ', wp_list_pluck( $item['service_types'], 'title' ) ); ?>
				</div>
			<?php endif; ?>
		<?php else : ?>
			<?php do_action( 'cp_library_info_field_' . $field, $item ); ?>
		<?php endif; ?>

	<?php endforeach; ?>
</div>


