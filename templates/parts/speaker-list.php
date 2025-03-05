<div class="cpl-list-speaker">

	<div class="cpl-list-speaker--thumb" onclick="window.location = jQuery(this).parent().find('a').attr('href');">
		<div class="cpl-list-speaker--thumb--canvas">
			<?php if ( has_post_thumbnail() ) : ?>
				<img alt="<?php echo esc_attr( get_the_title() ); ?>" src="<?php echo get_the_post_thumbnail_url(); ?>">
			<?php endif; ?>
		</div>
	</div>

	<div class="cpl-list-speaker--details">
		<h3 class="cpl-list-speaker--title"><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></h3>
	</div>

</div>
