<?php
// TODO: Undo the structure that's requiring output buffering here
ob_start();
$controller = new CP_Library\API\Sources();

// $request = new WP_REST_Request( '', '', [] );
// $items = $controller->get_items( $request );

$request = new WP_REST_Request( 'GET', "/" . $controller->get_namespace() . "/" . $controller->get_rest_base(), [] );
$response = rest_do_request( $request );
$sources = $response->get_data();
// echo "SOURCES HERE";
// echo var_export( $sources, true );
?>
<div class="cpl-source-list">

	<?php foreach ( $sources as $source ) : ?>

		<div class="cpl-source-list--item">

			<div class="cpl-source-list--item--thumb">
				<?php if ( $source['thumb'] ) : ?>
					<a class="cpl-source-list--item--thumb--img" href="#" style="background-url: url(<?php echo esc_url( $source['thumb'] ); ?>);"></a>
				<?php endif; ?>
			</div>

			<div class="cpl-source-list--item--details">
				<div class="cpl-source-list--item--title"><?php echo $source['title']; ?></div>

				<div class="cpl-source-list--item--desc"><?php echo $source['desc']; ?></div>

				<div class="cpl-source-list--item--meta">
					<div class="cpl-source-list--item--date"><?php echo $source['date']; ?></div>
					<div class="cpl-source-list--item--category"><?php echo implode( ', ', $source['category'] ); ?></div>
				</div>
			</div>

			<div class="cpl-source-list--item--actions">
				<?php if ( $source['video'] ) : ?>
					<div class="cpl-source-list--item--actions--video"></div>
				<?php endif; ?>

				<?php if ( $source['audio'] ) : ?>
					<div class="cpl-source-list--item--actions--audio"></div>
				<?php endif; ?>
			</div>

		</div>

	<?php endforeach; ?>

</div>
<?php
return ob_get_clean();
