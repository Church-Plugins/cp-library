<?php
namespace CP_Library\Setup\PostTypes;

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

use ChurchPlugins\Setup\Tables\SourceMeta;
use CP_Library\Admin\Settings;
use ChurchPlugins\Exception;
use CP_Library\Models\Item as ItemModel;
use CP_Library\Models\ServiceType as ServiceType_Model;

use ChurchPlugins\Setup\PostTypes\PostType;

/**
 * Setup for custom post type: Speaker
 *
 * @author costmo
 * @since 1.0
 */
class ServiceType extends PostType {

	/**
	 * Child class constructor. Punts to the parent.
	 *
	 * @author costmo
	 */
	protected function __construct() {
		$this->post_type = CP_LIBRARY_UPREFIX . "_service_type";

		$this->single_label = apply_filters( "cpl_single_{$this->post_type}_label", Settings::get_service_type( 'singular_label', 'Service Type' ) );
		$this->plural_label = apply_filters( "cpl_plural_{$this->post_type}_label", Settings::get_service_type( 'plural_label', 'Service Types' ) );

		parent::__construct( 'CP_Library' );
	}

	public function add_actions() {
		parent::add_actions();

		add_filter( 'cmb2_override_meta_value', [ $this, 'meta_get_override' ], 10, 4 );
		
		// Handle all meta updates (both CMB2 and direct WordPress functions)
		add_action( 'updated_post_meta', [ $this, 'handle_updated_meta' ], 10, 4 );
		add_action( 'added_post_meta', [ $this, 'handle_updated_meta' ], 10, 4 );

		$item_type = Item::get_instance()->post_type;
		add_filter( "manage_{$item_type}_posts_columns", [ $this, 'service_type_column' ] );
		add_action( "manage_{$item_type}_posts_custom_column", [ $this, 'service_type_column_cb' ], 10, 2 );
		add_action( 'pre_get_posts', [ $this, 'service_type_query' ] );

		// Add columns to service type list view
		add_filter( "manage_{$this->post_type}_posts_columns", [ $this, 'service_type_list_columns' ], 20 );
		add_action( "manage_{$this->post_type}_posts_custom_column", [ $this, 'service_type_list_column_cb' ], 10, 2 );

		// Register facets
		add_action( 'cpl_register_facets', [ $this, 'register_facets' ] );

		// Variations
		add_filter( 'cpl_variations_sources', [ $this, 'variation_source' ] );

		if ( $this->post_type == cp_library()->setup->variations->get_source() ) {
			add_filter( 'cpl_get_item_source', [ $this, 'variation_item_source' ], 10, 2 );
			add_filter( 'cpl_variations_source_items_' . $this->post_type, [ $this, 'variation_source_items' ] );
			add_action( 'cpl_save_item_source_' . $this->post_type, [ $this, 'variation_item_save' ], 10, 2 );
		}

	}

	/**
	 * Add custom columns to the service type admin list view
	 *
	 * @param array $columns The existing columns
	 * @return array Modified columns
	 */
	public function service_type_list_columns($columns) {
		$new_columns = [];
		foreach ($columns as $key => $column) {
			if ('date' === $key) {
				$new_columns['sermons'] = cp_library()->setup->post_types->item->plural_label;
			}

			$new_columns[$key] = $column;
		}

		// in case date isn't set
		if (!isset($columns['date'])) {
			$new_columns['sermons'] = cp_library()->setup->post_types->item->plural_label;
		}

		return $new_columns;
	}

	/**
	 * Output content for the custom sermons count column
	 *
	 * @param string $column The column name
	 * @param int $post_id The post ID
	 */
	public function service_type_list_column_cb($column, $post_id) {
		switch ($column) {
			case 'sermons':
				try {
					$service_type = ServiceType_Model::get_instance_from_origin($post_id);
					$items = $service_type->get_all_items();

					if (empty($items)) {
						_e('—', 'cp-library');
					} else {
						$url = add_query_arg([
							'post_type' => cp_library()->setup->post_types->item->post_type,
							'service-type' => $service_type->id
						], admin_url('edit.php'));
						echo sprintf('<a href="%s">%s</a>', $url, count($items));
					}
				} catch (\Exception $e) {
					_e('—', 'cp-library');
				}
				break;
		}
	}

	/**
	 * Return custom meta keys
	 *
	 * @return array|mixed|void
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function meta_keys() {
		return SourceMeta::get_keys();
	}

	/**
	 * Setup arguments for this CPT
	 *
	 * @return array
	 * @author costmo
	 */
	public function get_args() {
		$args                       = parent::get_args();
		$args['menu_icon']          = apply_filters( "{$this->post_type}_icon", 'dashicons-format-gallery' );
		$args['show_in_menu']       = 'edit.php?post_type=' . cp_library()->setup->post_types->item->post_type;

		return $args;
	}

	public function register_metaboxes() {
		$this->item_service_type();
		$this->service_type_options();
	}

	protected function service_type_options() {
		$cmb = new_cmb2_box( array(
			'id'           => 'cpl_service_type_options',
			'object_types' => [ $this->post_type ],
			'title'        => __( 'Service Type Options', 'cp-library' ),
			'context'      => 'normal',
			'priority'     => 'default',
		) );

		$cmb->add_field( [
			'name' => __( 'Exclude from Main List', 'cp-library' ),
			'desc' => __( 'When checked, sermons with this service type will not appear in the main sermon list. They will still appear in service type archives.', 'cp-library' ),
			'id'   => 'exclude_from_main_list',
			'type' => 'checkbox',
		] );
	}

	protected function item_service_type() {

		$service_types = ServiceType_Model::get_all_service_types();

		$cmb = new_cmb2_box( array(
			'id'           => 'cpl_service_type_data',
			'object_types' => [ cp_library()->setup->post_types->item->post_type ],
			'title'        => $this->single_label,
			'context'      => 'side',
			'show_names'   => false,
			'priority'     => 'default',
			'closed'       => false,
		) );

		if ( empty( $service_types ) ) {
			$cmb->add_field( [
				'desc' => sprintf( __( 'No %s have been created yet. <a href="%s">Create one here.</a>', 'cp-library' ), $this->plural_label, add_query_arg( [ 'post_type' => $this->post_type ], admin_url( 'post-new.php' ) )  ),
				'type' => 'title',
				'id' => 'cpl_no_service_types',
			] );

			return;
		}

		$service_types = array_combine( wp_list_pluck( $service_types, 'id' ), wp_list_pluck( $service_types, 'title' ) );
		$cmb->add_field( apply_filters( "{$this->post_type}_metabox_field_args", [
			'name' => __( 'Assign', 'cp-library' ) . ' ' . $this->single_label,
			'desc' => sprintf( __( 'Create a new %s <a target="_blank" href="%s">here</a>.', 'cp-library' ), $this->plural_label, add_query_arg( [ 'post_type' => $this->post_type ], admin_url( 'post-new.php' ) )  ),
			'id'   => 'cpl_service_type',
			'type' => 'pw_multiselect',
			'options' => $service_types,
			'show_option_none' => true,
			'default' => [ Settings::get_service_type( 'default_service_type' ) ],
			'attributes' => [
				'placeholder' => sprintf( __( 'Select a %s', 'cp-library' ), $this->single_label ),
				'data-maximum-selection-length' => '1',
			],
		], $this ) );
	}

	/**
	 * Meta override
	 *
	 * @param $data
	 * @param $object_id
	 * @param $data_args
	 * @param $field
	 *
	 * @return array|null
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function meta_get_override( $data, $object_id, $data_args, $field ) {

		try {
			switch ( $data_args['field_id'] ) {
				case 'cpl_service_type':
					$item = ItemModel::get_instance_from_origin( $object_id );
					return $item->get_service_types();
			}
		} catch ( Exception $e ) {
			error_log( $e );
		}

		return $data;
	}

	/**
	 * Handle meta updates for service type field
	 *
	 * @param int $meta_id ID of the meta value
	 * @param int $object_id Post ID
	 * @param string $meta_key Meta key
	 * @param mixed $meta_value Meta value
	 * 
	 * @since 1.6.0
	 */
	public function handle_updated_meta( $meta_id, $object_id, $meta_key, $meta_value ) {
		// Only process our specific meta key
		if ( 'cpl_service_type' !== $meta_key ) {
			return;
		}
		
		// Get post type of the object
		$post_type = get_post_type( $object_id );
		
		// Only process sermon post type
		if ( Item::get_instance()->post_type !== $post_type ) {
			return;
		}
		
		try {
			$item = ItemModel::get_instance_from_origin( $object_id );
			$service_type_ids = $this->process_service_type_data( $meta_value );
			$item->update_service_types( $service_type_ids );
		} catch ( Exception $e ) {
			error_log( 'CP Library Service Type Meta Update: ' . $e->getMessage() );
		}
	}
	
	/**
	 * Process service type data from metadata
	 * 
	 * @param mixed $data Service type data (IDs or title strings)
	 * @return array Array of service type IDs
	 */
	protected function process_service_type_data( $data ) {
		$service_type_ids = [];
		
		// If single value, convert to array
		if ( !is_array( $data ) ) {
			$data = [ $data ];
		}
		
		foreach ( $data as $value ) {
			if ( is_numeric( $value ) ) {
				// Already a service type ID
				$service_type_ids[] = absint( $value );
			} else if ( is_string( $value ) && ! empty( $value ) ) {
				// Try to find service type by title
				$service_type = get_page_by_title( $value, OBJECT, $this->post_type );
				if ( $service_type ) {
					try {
						$service_type_model = \CP_Library\Models\ServiceType::get_instance_from_origin( $service_type->ID );
						$service_type_ids[] = $service_type_model->id;
					} catch ( Exception $e ) {
						error_log( 'CP Library Service Type Lookup: ' . $e->getMessage() );
					}
				}
			}
		}
		
		return $service_type_ids;
	}


	/**
	 * @param $columns
	 *
	 * @return array
	 * @since  1.0.0
	 *
	 * @author Tanner Moushey
	 */
	public function service_type_column( $columns ) {
		$new_columns = [];
		foreach( $columns as $key => $column ) {
			if ( 'date' === $key ) {
				$new_columns['service_type'] = $this->single_label;
			}

			$new_columns[ $key ] = $column;
		}

		// in case date isn't set
		if ( ! isset( $columns['date'] ) ) {
			$new_columns['service_type'] = $this->single_label;
		}

		return $new_columns;
	}

	public function service_type_column_cb( $column, $post_id ) {
		switch( $column ) {
			case 'service_type' :
				$item = new \CP_Library\Controllers\Item( $post_id );
				$service_types = $item->get_service_types();

				 if ( empty( $service_types ) ) {
					 _e( '—', 'cp-library' );
				 } else {
					 $url = add_query_arg( $_GET, 'edit.php' );
					 $list = [];
					 foreach ( $service_types as $type ) {
						 $list[] = sprintf( '<a href="%s">%s</a>', add_query_arg( 'service-type', $type['id'], $url ), $type['title'] );
					 }

					 echo implode( ', ', $list );
				 }

				break;
		}
	}

	public function service_type_query( $query ) {

		// If the query is neither a main query or a query with a service type filter, exit.
		if ( ! $query->is_main_query() && ! $query->get( 'cpl_service_types' ) ) {
			return;
		}

		$types = $_GET['service-type'] ?? $query->get( 'cpl_service_types' ); // phpcs:ignore

		if ( empty( $types ) ) {
			return;
		}
		if ( ! in_array( $query->get('post_type'), [ Item::get_instance()->post_type ] ) ) {
			return;
		}

		if ( ! is_array( $types ) ) {
			$types = [ $types ];
		}

		$post_in_orig = $query->get( 'post__in' );
		$post_in = [];

		foreach( $types as $type ) {
			$type = absint( $type );

			try {
				$type = $query->get( 'cpl_service_types' ) ? ServiceType_Model::get_instance_from_origin( $type ) : ServiceType_Model::get_instance( $type );
				$post_in = array_merge( $post_in, $type->get_all_items() );
			} catch ( Exception $e ) {
				error_log( $e );
			}

		}

		if ( ! empty( $post_in ) ) {
			if ( ! empty( $post_in_orig ) ) {
				$post_in = array_intersect( $post_in_orig, $post_in );
				$post_in[] = '-1';
			}

			$query->set( 'post__in', $post_in );
		}

	}

	/**
	 * Add Service Type to the variation list
	 *
	 * @since  1.1.0
	 *
	 * @param $sources
	 *
	 * @return mixed
	 * @author Tanner Moushey, 5/5/23
	 */
	public function variation_source( $sources ) {
		$sources[ $this->post_type ] = $this->plural_label;
		return $sources;
	}

	/**
	 * Get variation items
	 *
	 * @since  1.1.0
	 *
	 * @return array
	 * @author Tanner Moushey, 5/5/23
	 */
	public function variation_source_items() {
		$items = [];

		foreach( ServiceType_Model::get_all_service_types() as $type ) {
			$items[ $type->id ] = $type->title;
		}

		return apply_filters( 'cpl_service_type_variation_source_items', $items );
	}

	/**
	 * Assign service_type source to Item if it exists
	 *
	 * @since  1.1.0
	 *
	 * @param $source
	 * @param $item \CP_Library\Controllers\Item
	 *
	 * @author Tanner Moushey, 5/6/23
	 */
	public function variation_item_source( $source, $item ) {
		try {
			$types = $item->get_service_types();
		} catch ( Exception $e ) {
			return $source;
		}

		if ( empty( $types ) ) {
			return $source;
		}

		// A variant can only have one type, default to the first item
		$type = $types[0];

		$return = [ 'type' => $this->post_type, 'id' => $type['id'], 'label' => $type['title'] ];

		return apply_filters( 'cpl_service_type_variation_item_source', $return, $source, $item );
	}

	/**
	 * Save the variation source to the Item
	 *
	 * @since  1.1.0
	 *
	 * @param $post_id
	 * @param $variation_id
	 *
	 * @author Tanner Moushey, 5/6/23
	 */
	public function variation_item_save( $post_id, $variation_id ) {
		try {
			$item = ItemModel::get_instance_from_origin( $post_id );
			$item->update_service_types( [ absint( $variation_id ) ] );
		} catch ( Exception $e ) {
			error_log( $e );
		}
	}

	/**
	 * Get a ServiceType controller instance for a post ID
	 *
	 * @since 1.6.0
	 * @param int $post_id The post ID
	 * @return \CP_Library\Controllers\ServiceType
	 */
	public function get_controller($post_id) {
		return new \CP_Library\Controllers\ServiceType($post_id);
	}

	/**
	 * Register service type facet
	 *
	 * @param \CP_Library\Filters $filters The filters instance
	 */
	public function register_facets( $filters ) {
		$filters->register_facet( 'service-type', [
			'label'           => $this->single_label,
			'param'           => 'facet-service-type',
			'query_var'       => 'cpl_service_types',
			'type'            => 'source',
			'source_type'     => 'service_type',
			'public'          => true,
			'query_callback'  => [ $this, 'facet_query_callback' ],
		]);
	}

	/**
	 * Query callback for service type facet
	 *
	 * @param \WP_Query $query  The query object
	 * @param array     $values The facet values
	 * @param array     $config The facet configuration
	 */
	public function facet_query_callback( $query, $values, $config ) {
		if ( empty( $values ) ) {
			return;
		}

		// Use the same logic as service_type_query method
		if ( ! is_array( $values ) ) {
			$values = [ $values ];
		}

		$post_in_orig = $query->get( 'post__in' );
		$post_in = [];

		foreach( $values as $type_id ) {
			$type_id = absint( $type_id );

			try {
				$type = ServiceType_Model::get_instance( $type_id );
				$post_in = array_merge( $post_in, $type->get_all_items() );
			} catch ( Exception $e ) {
				error_log( $e );
			}
		}

		if ( ! empty( $post_in ) ) {
			if ( ! empty( $post_in_orig ) ) {
				$post_in = array_intersect( $post_in_orig, $post_in );
				$post_in[] = '-1'; // Ensure we still get no results if there's no intersection
			}

			$query->set( 'post__in', $post_in );
		}
	}

}
