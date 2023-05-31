<?php
global $post;
$post_orig = $post;
if ( empty( $args ) ) {
	return;
}

$item = $args['item'];
$resources = false;

$post = get_post( $item['originID'] );
setup_postdata( $post->ID );

if ( function_exists( 'cp_resources' ) ) {
	$resources = \CP_Resources\Models\Resource::get_all_resources( $item['originID'] );
}

$variations = [];

if ( empty( $item['variations'] ) ) {
	$variations[] = $item;
} else {
	$variations = $item['variations'];
}
?>

<?php do_action( 'cpl_single_item_before', $item ); ?>

<div class="cpl-widget cpl-widget--single-item-alt">

	<div class="cpl-single-item">

		<div class="cpl-columns">

			<?php if ( ! empty( $args['player'] ) && $args['player'] !== 'false') : ?>
				<div class="cpl-single-item--media">
					<div class="cpl-single-item--media--wrapper">
						<div class="cpl-single-item--media--bg" style="background-image: url(<?php echo esc_url( $item['thumb'] ); ?>"></div>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $args['details'] ) && $args['details'] !== 'false') : ?>
				<div class="cpl-single-item--details">

					<div class="cpl-single-item--heading">
						<?php if ( ! empty( $item['types'] ) ) : ?>
							<div class="cpl-item--types">
								<?php foreach ( $item['types'] as $type ) : ?>
									<a href="<?php echo esc_url( $type['permalink'] ); ?>"><?php echo esc_html( $type['title'] ); ?></a>
									<span class="cpl-separator">,&nbsp;</span>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>

						<h2 class="cpl-single-item--title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>
						<?php \CP_Library\Templates::get_template_part( 'parts/item-single/meta', $args ); ?>
					</div>

					<?php if ( ! empty( $resources ) ) : ?>
						<div class="cpl-single-item--resources resources-hide" onclick="jQuery(this).toggleClass('resources-hide');">
							<?php cp_resources()->templates->get_template_part( 'widgets/item-resources', [ 'id' => $item['originID'], 'title' => count( $resources ) . ' ' . __( 'attachments', 'cp-library' ) ] ); ?>
							<?php foreach( $resources as $r ) : $resource = new \CP_Resources\Controllers\Resource( $r->id ) ?>
								<?php // echo $resource->get_title(); ?>
							<?php endforeach; ?>
						</div>
					<?php endif; ?>

					<div class="cpl-single-item--variations">
						<?php foreach ( $variations as $variation ) : ?>
							<div class="cpl-single-item--variations--variant">
								<div class="cpl-single-item--variant--info">
									<?php if ( ! empty( $variation['variation'] ) ) : ?>
										<div class="cpl-single-item--variant--title"><?php echo esc_html( $variation['variation'] ); ?></div>
									<?php endif; ?>

									<?php if ( ! empty( $variation['speakers'] ) ) : ?>
										<div class="cpl-single-item--variant--speakers">
											Speaker: <?php echo implode( ', ', wp_list_pluck( $variation['speakers'], 'title' ) ); ?>
										</div>
									<?php endif; ?>
								</div>

								<div class="cpl_item_actions cpl-item-actions" data-item="<?php echo esc_attr( json_encode( $variation ) ); ?>"></div>
							</div>
						<?php endforeach; ?>
					</div>

				</div>
			<?php endif; ?>
		</div>

	</div>

</div>

<script>
	jQuery('.cpl_item_actions').on('cpl-rendered', function (e) {
	  jQuery(e.currentTarget).find('.cpl-button--outlined').removeClass('is-outlined').removeClass('cpl-button--outlined');
	});
</script>
<?php
do_action( 'cpl_single_item_after', $item );
wp_reset_postdata();
$post = $post_orig;
?>
