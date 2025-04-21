<?php
/**
 * Service Type Context Class
 *
 * Implementation of the filter context for service type specific views.
 *
 * @package CP_Library\Filters
 * @since 1.6.0
 */

namespace CP_Library\Filters\Contexts;

use CP_Library\Filters\AbstractContext;

/**
 * ServiceTypeContext class - Context implementation for service type filters.
 *
 * Provides context-specific behavior for filtering when viewing items through
 * the lens of a specific service type.
 *
 * @since 1.6.0
 */
class ServiceTypeContext extends AbstractContext {

    /**
     * Context identifier
     *
     * @var string
     */
    protected $id = 'service-type';

    /**
     * Constructor
     *
     * @param array $args Configuration arguments
     */
    public function __construct( $args = [] ) {
        parent::__construct( $args );
        
        if ( ! $this->label ) {
            $this->label = __( 'Service Type', 'cp-library' );
        }
    }

    /**
     * Modify a query for this context
     *
     * @param array $query_args WP_Query arguments
     * @param array $context_args Context-specific arguments
     * @return array Modified query arguments
     */
    public function modify_query( $query_args, $context_args ) {
        // For the service type context, we need to ensure the query includes the service type filter
        if ( ! empty( $this->post_type ) ) {
            $query_args['post_type'] = $this->post_type;
        }
        
        // Apply service type filtering if specified
        if ( ! empty( $context_args['service_type_id'] ) ) {
            $query_args['cpl_service_types'] = $context_args['service_type_id'];
        }
        
        return $query_args;
    }

    /**
     * Check if this context is currently active
     *
     * @return bool Whether this context is active
     */
    public function is_active() {
        // Check if we're viewing content through a service type lens
        // This could be determined by URL parameters, query vars, or other means
        
        // Check URL parameter for service type
        if ( isset( $_GET['service_type'] ) && ! empty( $_GET['service_type'] ) ) {
            return true;
        }
        
        // Check if we're on a service type taxonomy archive
        if ( is_tax( 'cpl_service_type' ) ) {
            return true;
        }
        
        // Check for custom query var that might indicate service type context
        if ( get_query_var( 'cpl_service_type', false ) ) {
            return true;
        }
        
        return false;
    }
}