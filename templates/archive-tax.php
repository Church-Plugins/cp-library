<?php
use CP_Library\Templates;

$description = get_the_archive_description();
$term = get_queried_object();
?>

<?php if ( have_posts() ) : ?>
	<div class="cpl-archive cpl-archive--<?php echo esc_attr( $term->slug ); ?>">

		<?php do_action( 'cpl_before_archive' ); ?>
		<?php do_action( 'cpl_before_archive_'  . $term->slug ); ?>

		<?php the_archive_title( '<h1 class="page-title">', '</h1>' ); ?>
		<?php if ( $description ) : ?>
			<div class="archive-description"><?php echo wp_kses_post( wpautop( $description ) ); ?></div>
		<?php endif; ?>

		<?php foreach( [ cp_library()->setup->post_types->item_type, cp_library()->setup->post_types->item ] as $type ) : $found = 0; ?>
			<div class="cpl-archive--<?php echo esc_attr( Templates::get_type( $type->post_type ) ); ?>">
				<h2><?php echo esc_html( $type->plural_label ); ?></h2>
				<div class="cpl-archive--list">
					<?php while ( have_posts() ) : the_post(); if ( $type->post_type !== get_post_type() ) continue; $found = 1; ?>
						<div class="cpl-archive--list--item">
							<?php Templates::get_template_part( "parts/" . Templates::get_type() . "-list" ); ?>
						</div>
					<?php endwhile; ?>
				</div>

				<?php if ( ! $found ) : ?>
					<p><?php printf( __( "No %s found.", 'cp-library' ), $type->plural_label ); ?></p>
				<?php endif; ?>
			</div>
		<?php rewind_posts(); endforeach; ?>

		<?php do_action( 'cpl_after_archive' ); ?>
		<?php do_action( 'cpl_after_archive_'  . $term->slug ); ?>
	</div>
<?php endif; ?>
