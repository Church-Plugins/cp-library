<?php
global $post;
$post_orig = $post;
if ( empty( $args ) ) {
	return;
}

$item = $args['item'];

$post = get_post( $item['originID'] );
setup_postdata( $post->ID );

?>

<?php do_action( 'cpl_single_item_before', $item ); ?>

<div class="cpl-widget cpl-widget--single-item">

	<div class="cpl-single-item">

		<div class="cpl-columns">

			<?php if ( ! empty( $args['player'] ) && $args['player'] !== 'false') : ?>
				<div class="cpl-single-item--media">
					<div class="cpl-single-item--media--wrapper">
						<div class="cpl-single-item--media--bg" style="background-image: url(<?php echo esc_url( $item['thumb'] ); ?>"></div>
						<div class="cpl_item_actions cpl-item-actions" data-item="<?php echo esc_attr( json_encode( $item ) ); ?>"></div>
					</div>
				</div>
			<?php endif; ?>

			<?php if ( ! empty( $args['details'] ) && $args['details'] !== 'false') : ?>
				<div class="cpl-single-item--details">

					<div class="cpl-single-item--heading">
						<h2 class="cpl-single-item--title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h2>

						<?php if ( ! empty( $item['types'] ) ) : ?>
							<div class="cpl-item--types text-xlarge">
								<?php foreach ( $item['types'] as $type ) : ?>
									<a href="<?php echo esc_url( $type['permalink'] ); ?>"><?php echo esc_html( $type['title'] ); ?></a>
									<span class="cpl-separator">,&nbsp;</span>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
					</div>

					<div class="cpl-single-item--desc">
						<?php the_content(); ?>
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
