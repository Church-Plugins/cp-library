<?php
use ChurchPlugins\Helpers;

$taxonomies = cp_library()->setup->taxonomies->get_objects();
$uri = explode( '?', $_SERVER['REQUEST_URI'] )[0];
$get = $_GET;
?>
<div class="cpl-filter">

	<form method="get" class="cpl-filter--form">

		<div class="cpl-filter--submit">
			<button type="submit" class="cpl-filter--submit--button cpl-button"><?php _e( 'Filter', 'cp-library' ); ?></button>
		</div>

		<?php foreach( $taxonomies as $tax ) :
			$terms = get_terms( [ 'taxonomy' => $tax->taxonomy ] );

			if ( is_wp_error( $terms ) || empty( $terms ) ) {
				continue;
			} ?>

			<div class="cpl-filter--<?php echo esc_attr( $tax->taxonomy ); ?> cpl-filter--has-dropdown">
				<button class="cpl-filter--dropdown-button cpl-button cpl-button--transparent"><?php echo $tax->plural_label; ?></button>
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
				<input type="text" name="s" value="<?php echo Helpers::get_param( $_GET, 's' ); ?>" />
			</div>
		</div>

	</form>

	<script>
		jQuery('.cpl-filter--has-dropdown button').on( 'click', function(e) {
			e.preventDefault();
			jQuery(this).parent().toggleClass('open');
		})
	</script>
</div>
