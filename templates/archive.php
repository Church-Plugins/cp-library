<?php
use CP_Library\Templates;

$type = Templates::get_type();
$description = get_the_archive_description();
?>

<div class="cpl-archive cpl-archive--<?php echo esc_attr( $type ); ?>">

	<?php do_action( 'cpl_before_archive' ); ?>
	<?php do_action( 'cpl_before_archive_'  . $type ); ?>

	<h1 class="page-title"><?php echo post_type_archive_title(); ?></h1>

	<div class="cpl-archive--container">

		<div class="cpl-archive--container--filter">
			<?php Templates::get_template_part( "parts/filter" ); ?>
		</div>

		<div class="cpl-archive--container--list">
			<?php Templates::get_template_part( "parts/filter-selected" ); ?>

			<div class="cpl-archive--list">
				<?php if ( have_posts() ) : ?>
					<?php while( have_posts() ) : the_post();  ?>
						<div class="cpl-archive--list--item">
							<?php Templates::get_template_part( "parts/" . Templates::get_type() . "-list" ); ?>
						</div>
					<?php endwhile; ?>
				<?php else : ?>
						<p><?php printf( __( "No %s found.", 'cp-library' ), $type->plural_label ); ?></p>
				<?php endif; ?>
			</div>
		</div>

	</div>

	<?php do_action( 'cpl_after_archive' ); ?>
	<?php do_action( 'cpl_after_archive_'  . $type ); ?>
</div>
