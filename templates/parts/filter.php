<?php
use ChurchPlugins\Helpers;

$taxonomies = cp_library()->setup->taxonomies->get_objects();
$uri = explode( '?', $_SERVER['REQUEST_URI'] )[0];
$get = $_GET;

$display = '';

if ( empty( $get ) ) {
	$display = 'style="display: none;"';
}

$display = apply_filters( 'cpl_filters_display', $display );
?>
<div class="cpl-filter">

	<form method="get" class="cpl-filter--form">

		<div class="cpl-filter--toggle">
			<a href="#" class="cpl-filter--toggle--button cpl-button"><span><?php _e( 'Filter', 'cp-library' ); ?></span> <?php echo Helpers::get_icon( 'filter' ); ?></a>
		</div>

		<?php foreach( $taxonomies as $tax ) :
			$terms = get_terms( [ 'taxonomy' => $tax->taxonomy ] );

			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			} ?>

			<div class="cpl-filter--<?php echo esc_attr( $tax->taxonomy ); ?> cpl-filter--has-dropdown" <?php echo $display; ?>>
				<a href="#" class="cpl-filter--dropdown-button cpl-button is-light"><?php echo $tax->plural_label; ?></a>
				<div class="cpl-filter--dropdown">
					<?php foreach ( $terms as $term ) : ?>
						<label>
							<input type="checkbox" <?php checked( in_array( $term->slug, Helpers::get_param( $_GET, $tax->taxonomy, [] ) ) ); ?> name="<?php echo esc_attr( $tax->taxonomy ); ?>[]" value="<?php echo esc_attr( $term->slug ); ?>"/> <?php echo esc_html( $term->name ); ?>
						</label>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endforeach; ?>

		<div class="cpl-filter--search">
			<div class="cpl-filter--search--box">
				<button type="submit"><span class="material-icons-outlined">search</span></button>
				<input type="text" name="s" value="<?php echo Helpers::get_param( $_GET, 's' ); ?>" placeholder="<?php _e( 'Search', 'cp-library' ); ?>"/>
			</div>
		</div>

	</form>

	<script>

	  	jQuery('.cpl-filter--toggle--button').on('click', function(e) {
			e.preventDefault();
			jQuery('.cpl-filter--has-dropdown').toggle();
		});

	  	jQuery('.cpl-filter--form input[type=checkbox]').on('change', function() {
			jQuery('.cpl-filter--form').submit();
		});

		jQuery('.cpl-filter--has-dropdown a').on( 'click', function(e) {
			e.preventDefault();
			jQuery(this).parent().toggleClass('open');
		})
	</script>
</div>
