<?php
use CP_Library\Admin\Settings;
use CP_Library\Templates;

$type = Templates::get_type();
$description = get_the_archive_description();

$classes = [ 'cpl-archive', 'cpl-archive--' . $type ];

if ( 'item-type' == $type && $ratio = Settings::get_item_type( 'image_ratio' ) ) {
	$classes[] = 'cpl-archive--ratio-' . $ratio;
}

$classes = apply_filters( 'cpl_archive_classes', $classes, $type );
?>

<div class="<?php echo esc_attr( implode( ' ', $classes ) ); ?>">

	<?php do_action( 'cpl_before_archive' ); ?>
	<?php do_action( 'cpl_before_archive_'  . $type ); ?>

	<div class="cpl-archive--header">
		<h1 class="page-title"><?php echo apply_filters( 'cpl-archive-title', post_type_archive_title(), $type ); ?></h1>
		<?php Templates::type_switcher(); ?>
	</div>

	<div class="cpl-archive--container">

		<div class="cpl-archive--container--filter">
			<?php cp_library()->templates->get_template_part( "parts/filter" ); ?>
		</div>

		<div class="cpl-archive--container--list">
			<?php cp_library()->templates->get_template_part( "parts/filter-selected" ); ?>

			<div class="cpl-archive--list">
				<?php if ( have_posts() ) { ?>
					<?php while( have_posts() ) : the_post();  ?>
						<div class="cpl-archive--list--item">
							<?php cp_library()->templates->get_template_part( "parts/" . Templates::get_type() . "-list" ); ?>
						</div>
					<?php endwhile; ?>
				<?php } else if( !empty( $type ) && is_object( $type ) && !empty( $type->plural_label ) ) { ?>
						<p><?php printf( __( "No %s found.", 'cp-library' ), $type->plural_label ); ?></p>
				<?php }; ?>
			</div>
		</div>

	</div>

	<?php do_action( 'cpl_after_archive' ); ?>
	<?php do_action( 'cpl_after_archive_'  . $type ); ?>
</div>
