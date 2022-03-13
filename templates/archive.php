<?php
use CP_Library\Templates;

$type = Templates::get_type();
$description = get_the_archive_description();
?>

<?php if ( have_posts() ) : ?>
	<div class="cpl-archive cpl-archive--<?php echo esc_attr( $type ); ?>">

		<?php do_action( 'cpl_before_archive' ); ?>
		<?php do_action( 'cpl_before_archive_'  . $type ); ?>

		<?php the_archive_title( '<h1 class="page-title">', '</h1>' ); ?>
		<?php if ( $description ) : ?>
			<div class="archive-description"><?php echo wp_kses_post( wpautop( $description ) ); ?></div>
		<?php endif; ?>

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
