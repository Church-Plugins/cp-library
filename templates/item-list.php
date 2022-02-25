<?php
// TODO: Undo the structure that's requiring output buffering here
$controller = new CP_Library\API\Items();

// $request = new WP_REST_Request( '', '', [] );
// $items = $controller->get_items( $request );

$request = new WP_REST_Request( 'GET', "/" . $controller->get_namespace() . "/" . $controller->get_rest_base(), [] );
$response = rest_do_request( $request );
$items = $response->get_data();
?>
<div class="cpl-item-list">

	<?php foreach ( $items as $item ) : ?>

		<div class="cpl-item-list--item">

			<div class="cpl-item-list--item--thumb">
				<?php if ( $item['thumb'] ) : ?>
					<a class="cpl-item-list--item--thumb--img" href="#" style="background-url: url(<?php echo esc_url( $item['thumb'] ); ?>);"></a>
				<?php endif; ?>
			</div>

			<div class="cpl-item-list--item--details">
				<div class="cpl-item-list--item--title"><?php echo $item['title']; ?></div>

				<div class="cpl-item-list--item--desc"><?php echo $item['desc']; ?></div>

				<div class="cpl-item-list--item--meta">
					<div class="cpl-item-list--item--date"><?php echo $item['date']; ?></div>
					<div class="cpl-item-list--item--category"><?php // echo implode( ', ', $item['category'] ); ?></div>
				</div>
			</div>

			<div class="cpl-item-list--item--actions">
				<?php if ( $item['video'] ) : ?>
					<div class="cpl-item-list--item--actions--video"></div>
				<?php endif; ?>

				<?php if ( $item['audio'] ) : ?>
					<div class="cpl-item-list--item--actions--audio"></div>
				<?php endif; ?>
			</div>

		</div>

	<?php endforeach; ?>

</div>


