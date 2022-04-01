<?php
use ChurchPlugins\Helpers;

$taxonomies = cp_library()->setup->taxonomies->get_objects();
$uri = explode( '?', $_SERVER['REQUEST_URI'] )[0];
$get = $_GET;
?>
<div class="cpl-filter--filters">
	<?php if ( ! empty( $_GET['s'] ) ) : unset( $get[ 's' ] ); ?>
		<a href="<?php echo esc_url( add_query_arg( $get, $uri ) ); ?>" class="cpl-filter--filters--filter"><?php echo __( 'Search:' ) . ' ' . Helpers::get_request('s' ); ?></a>
	<?php endif; ?>

	<?php foreach ( $taxonomies as $tax ) : if ( empty( $_GET[ $tax->taxonomy ] ) ) continue; ?>
		<?php foreach( $_GET[ $tax->taxonomy ] as $slug ) :
			if ( ! $term = get_term_by( 'slug', $slug, $tax->taxonomy ) ) {
				continue;
			}

			$get = $_GET;
			unset( $get[ $tax->taxonomy ][ array_search( $slug, $get[ $tax->taxonomy ] ) ] );
			?>
			<a href="<?php echo esc_url( add_query_arg( $get, $uri ) ); ?>" class="cpl-filter--filters--filter"><?php echo $term->name; ?></a>
		<?php endforeach; ?>
	<?php endforeach; ?>
</div>
