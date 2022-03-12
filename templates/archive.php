<?php
use CP_Library\Templates;

$type = Templates::get_type();
?>

<?php if ( have_posts() ) : ?>
	<div class="cpl-archive cpl-archive--<?php echo esc_attr( $type ); ?>">
		<?php do_action( 'cpl_before_cpl_archive_'  . $type ); ?>

		<div class="cpl-archive--list">
			<?php while( have_posts() ) : the_post();  ?>
				<div class="cpl-archive--list--item">
					<?php Templates::get_template_part( "parts/" . Templates::get_type() . "-list" ); ?>
				</div>
			<?php endwhile; ?>
		</div>

		<?php do_action( 'cpl_after_archive' ); ?>
		<?php do_action( 'cpl_after_archive_'  . $type ); ?>
	</div>
<?php endif; ?>
