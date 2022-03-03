<?php
$type = \CP_Library\Templates::get_type();
?>

<?php if ( have_posts() ) : ?>
	<div class="cpl-single cpl-single--<?php echo esc_attr( $type ); ?>">
		<?php do_action( 'cpl_before_cpl_single_'  . $type ); ?>

		<?php while( have_posts() ) : the_post(); ?>
			<?php \CP_Library\Templates::get_template_part( "parts/$type-single" ); ?>
		<?php endwhile; ?>

		<?php do_action( 'cpl_after_cpl_single_'  . $type ); ?>
	</div>
<?php endif; ?>
