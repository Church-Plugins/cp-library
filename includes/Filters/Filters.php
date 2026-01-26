<?php
/**
 * Filter System Compatibility Layer
 *
 * Provides backward compatibility with the old singleton Filters class.
 *
 * @package CP_Library\Filters
 * @since 1.6.0
 */

namespace CP_Library\Filters;

/**
 * Filters class - Backward compatibility layer for the old singleton Filters class.
 *
 * This class provides a drop-in replacement for the old Filters class,
 * delegating calls to the new filter system while maintaining API compatibility.
 *
 * @since 1.6.0
 */
class Filters {

    /**
     * Singleton instance
     *
     * @var Filters
     */
    private static $instance = null;

    /**
     * Get the singleton instance
     *
     * @return Filters
     */
    public static function get_instance() {
        if ( null === self::$instance ) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Private constructor
     */
    private function __construct() {
        // No actions needed here - all handled by the new filter system
    }

    /**
     * Get the sermon filter manager
     *
     * @return object The sermon filter manager
     */
    private function get_manager() {
        return FilterManager::get_filter_manager( 'cpl_item' );
    }

    /**
     * Register a context - delegates to sermon filter manager
     *
     * @param string $id   Context ID
     * @param array  $args Context arguments
     */
    public function register_context( $id, $args ) {
        if ( $manager = $this->get_manager() ) {
            $manager->register_context( $id, $args );
        }
    }

    /**
     * Get a specific context - delegates to sermon filter manager
     *
     * @param string $id Context ID
     *
     * @return array|null
     */
    public function get_context( $id ) {
        if ( $manager = $this->get_manager() ) {
            return $manager->get_context( $id );
        }
        return null;
    }

    /**
     * Get all registered contexts - delegates to sermon filter manager
     *
     * @return array
     */
    public function get_contexts() {
        if ( $manager = $this->get_manager() ) {
            return $manager->get_contexts();
        }
        return [];
    }

    /**
     * Register a facet - delegates to sermon filter manager
     *
     * @param string $id   Facet ID
     * @param array  $args Facet arguments
     */
    public function register_facet( $id, $args ) {
        if ( $manager = $this->get_manager() ) {
            $manager->register_facet( $id, $args );
        }
    }

    /**
     * Register a taxonomy facet - delegates to sermon filter manager
     *
     * @param string $taxonomy Taxonomy name
     * @param array  $args     Additional facet arguments
     */
    public function register_taxonomy_facet( $taxonomy, $args = [] ) {
        if ( $manager = $this->get_manager() ) {
            $manager->register_taxonomy_facet( $taxonomy, $args );
        }
    }

    /**
     * Register a meta facet - delegates to sermon filter manager
     *
     * @param string $id       Facet ID
     * @param string $meta_key Meta key
     * @param array  $args     Additional facet arguments
     */
    public function register_meta_facet( $id, $meta_key, $args = [] ) {
        if ( $manager = $this->get_manager() ) {
            $manager->register_meta_facet( $id, $meta_key, $args );
        }
    }

    /**
     * Get a specific facet configuration - delegates to sermon filter manager
     *
     * @param string $id Facet ID
     * @return array|null
     */
    public function get_facet( $id ) {
        if ( $manager = $this->get_manager() ) {
            return $manager->get_facet( $id );
        }
        return null;
    }

    /**
     * Get all registered facets - delegates to sermon filter manager
     *
     * @param array $args Filter arguments
     * @return array
     */
    public function get_facets( $args = [] ) {
        if ( $manager = $this->get_manager() ) {
            return $manager->get_facets( $args );
        }
        return [];
    }

    /**
     * AJAX handler for getting filter options - delegates to sermon filter manager
     */
    public function ajax_get_filter_options() {
        if ( $manager = $this->get_manager() ) {
            $manager->ajax_get_filter_options();
        }
    }

    /**
     * Get filter options for a specific facet and context - delegates to sermon filter manager
     *
     * @param string $facet_id Facet ID
     * @param string $context  Context ID
     * @param array  $args     Additional arguments
     *
     * @return array
     */
    public function get_filter_options( $facet_id, $context = 'archive', $args = [] ) {
        if ( $manager = $this->get_manager() ) {
            return $manager->get_filter_options( $facet_id, $context, $args );
        }
        return [];
    }

    /**
     * Get sources (speakers or service types) - delegates to sermon filter manager
     *
     * @param array $args The arguments for the query.
     * @return array
     */
    public function get_sources( $args ) {
        if ( $manager = $this->get_manager() ) {
            if ( $args['facet_type'] === 'speaker' ) {
                return $manager->get_speaker_options( $args );
            } elseif ( $args['facet_type'] === 'service-type' ) {
                return $manager->get_service_type_options( $args );
            }
        }
        return [];
    }

    /**
     * Get terms - delegates to sermon filter manager
     *
     * @param array $args The arguments for getting terms.
     * @return array
     */
    public function get_terms( $args ) {
        if ( $manager = $this->get_manager() ) {
            return $manager->get_taxonomy_options( $args );
        }
        return [];
    }

    /**
     * Render filter form - delegates to sermon filter manager
     *
     * @param array $args Filter form arguments
     */
    public function render_filter_form( $args = [] ) {
        if ( $manager = $this->get_manager() ) {
            return $manager->render_filter_form( $args );
        }
        return '';
    }

    /**
     * Render selected filters - delegates to sermon filter manager
     *
     * @param array $args Selected filters arguments
     */
    public function render_selected_filters( $args = [] ) {
        if ( $manager = $this->get_manager() ) {
            return $manager->render_selected_filters( $args );
        }
        return '';
    }

    /**
     * Apply facet filters to a WP_Query - delegates to sermon filter manager
     *
     * @param \WP_Query $query The query object
     */
    public function apply_facet_filters( $query ) {
        if ( $manager = $this->get_manager() ) {
            $manager->apply_facet_filters( $query );
        }
    }

    /**
     * Get active facets from the current request - delegates to sermon filter manager
     *
     * @return array Array of facet_id => values
     */
    public function get_active_facets_from_request() {
        if ( $manager = $this->get_manager() ) {
            return $manager->get_active_facets_from_request();
        }
        return [];
    }

    /**
     * Default query callback for taxonomy facets - delegates to sermon filter manager
     *
     * @param \WP_Query $query        The query object
     * @param array     $values       The facet values
     * @param array     $facet_config The facet configuration
     */
    public function query_taxonomy_facet( $query, $values, $facet_config ) {
        if ( $manager = $this->get_manager() ) {
            $manager->query_taxonomy_facet( $query, $values, $facet_config );
        }
    }

    /**
     * Default query callback for meta facets - delegates to sermon filter manager
     *
     * @param \WP_Query $query        The query object
     * @param array     $values       The facet values
     * @param array     $facet_config The facet configuration
     */
    public function query_meta_facet( $query, $values, $facet_config ) {
        if ( $manager = $this->get_manager() ) {
            $manager->query_meta_facet( $query, $values, $facet_config );
        }
    }

    /**
     * Convert standard query vars to facet parameter names - delegates to sermon filter manager
     *
     * @param array $query_vars The query variables to convert
     * @return array Updated query vars with facet parameters
     */
    public function convert_query_vars_to_facet_params( $query_vars ) {
        if ( $manager = $this->get_manager() ) {
            return $manager->convert_query_vars_to_facet_params( $query_vars );
        }
        return $query_vars;
    }

    /**
     * Enqueue scripts and styles for filters
     */
    public function enqueue_scripts() {
        // This is now handled by FilterManager
        FilterManager::enqueue_scripts();
    }
}
