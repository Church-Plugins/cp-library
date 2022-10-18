<?php
use ChurchPlugins\Helpers;

$taxonomies = cp_library()->setup->taxonomies->get_objects();
$uri = explode( '?', $_SERVER['REQUEST_URI'] )[0];
$get = $_GET;
$display = '';

if ( empty( $get ) ) {
	$display = 'style="display: none;"';
}

$is_item_archive = is_post_type_archive( cp_library()->setup->post_types->item->post_type );
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

		<?php if ( $is_item_archive && cp_library()->setup->post_types->speaker_enabled() ) : ?>
			<div class="cpl-filter--<?php echo esc_attr( cp_library()->setup->post_types->speaker->post_type ); ?> cpl-filter--has-dropdown" <?php echo $display; ?>>
				<a href="#" class="cpl-filter--dropdown-button cpl-button is-light"><?php echo cp_library()->setup->post_types->speaker->plural_label; ?></a>
				<div class="cpl-filter--dropdown">
					<?php foreach ( \CP_Library\Models\Speaker::get_all_speakers() as $speaker ) : ?>
						<label>
							<input type="checkbox" <?php checked( in_array( $speaker->id, Helpers::get_param( $_GET, 'speaker', [] ) ) ); ?>name="speaker[]" value="<?php echo esc_attr( $speaker->id ); ?>"/> <?php echo esc_html( $speaker->title ); ?>
						</label>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>

		<?php if ( $is_item_archive && cp_library()->setup->post_types->service_type_enabled() ) : ?>
			<div class="cpl-filter--<?php echo esc_attr( cp_library()->setup->post_types->service_type->post_type ); ?> cpl-filter--has-dropdown" <?php echo $display; ?>>
				<a href="#" class="cpl-filter--dropdown-button cpl-button is-light"><?php echo cp_library()->setup->post_types->service_type->plural_label; ?></a>
				<div class="cpl-filter--dropdown">
					<?php foreach ( \CP_Library\Models\ServiceType::get_all_service_types() as $type ) : ?>
						<label>
							<input type="checkbox" <?php checked( in_array( $type->id, Helpers::get_param( $_GET, 'service-type', [] ) ) ); ?>name="service-type[]" value="<?php echo esc_attr( $type->id ); ?>"/> <?php echo esc_html( $type->title ); ?>
						</label>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endif; ?>

		<div class="cpl-filter--search">
			<div class="cpl-filter--search--box">
				<button type="submit"><span class="material-icons-outlined">search</span></button>
				<input type="text" name="s" value="<?php echo Helpers::get_param( $_GET, 's' ); ?>" placeholder="<?php _e( 'Search', 'cp-library' ); ?>"/>
			</div>
		</div>

	</form>

	<script>

		var $ = jQuery;
	  	$('.cpl-filter--toggle--button').on('click', function(e) {
			e.preventDefault();
			$('.cpl-filter--has-dropdown').toggle();
		});

	  	$( '.cpl-filter--form input[type=checkbox]' ).on( 'change',
			function() {

				// Munge the URL to discard pagination when fiilter options change
				var form = $( this ).parents( 'form.cpl-filter--form' );
				var location = window.location;
				var baseUrl = location.protocol + '//' + location.hostname;
				var pathSplit = location.pathname.split( '/' );
				let finalPath = '';

				// Get the URL before the `page` element
				var gotBoundary = false;
				$( pathSplit ).each(
					function(index, token) {

						if( 'page' === token ) {
							gotBoundary = true;
						}
						if( !gotBoundary ) {

							if( '' === token ) {
								if( !finalPath.endsWith( '/' ) ) {
									finalPath += '/';
								}
							} else {
								finalPath += token;
								if( !finalPath.endsWith( '/' ) ) {
									finalPath += '/';
								}
							}

						}
					}
				);
				// Finish and add already-used GET params
				if( !finalPath.endsWith( '/' ) ) {
					finalPath += '/';
				}
				if( location.search && location.search.length > 0 ) {
					finalPath += location.search;
				}
				// Set form property and do it
				$( form ).attr( 'action', baseUrl + finalPath );
				$('.cpl-filter--form').submit();
			});

		$('.cpl-filter--has-dropdown a').on( 'click', function(e) {
			e.preventDefault();
			$(this).parent().toggleClass('open');
		})
	</script>
</div>
