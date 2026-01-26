<?php
/**
 * Archive Context Class
 *
 * Implementation of the filter context for post type archives.
 *
 * @package CP_Library\Filters
 * @since 1.6.0
 */

namespace CP_Library\Filters\Contexts;

use CP_Library\Filters\AbstractContext;

/**
 * ArchiveContext class - Context implementation for post type archives.
 *
 * Provides context-specific behavior for filtering on archive pages.
 *
 * @since 1.6.0
 */
class ArchiveContext extends AbstractContext {

    /**
     * Context identifier
     *
     * @var string
     */
    protected $id = 'archive';

    /**
     * Constructor
     *
     * @param array $args Configuration arguments
     */
    public function __construct( $args = [] ) {
        parent::__construct( $args );
        
        if ( ! $this->label ) {
            $this->label = __( 'Archive', 'cp-library' );
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
        // For the archive context, we're mostly using the default query args
        // but we ensure the post type is set correctly
        
        if ( ! empty( $this->post_type ) ) {
            $query_args['post_type'] = $this->post_type;
        }
        
        // Apply any archive-specific modifications
        if ( ! empty( $context_args['author'] ) ) {
            $query_args['author'] = $context_args['author'];
        }
        
        if ( ! empty( $context_args['term'] ) && ! empty( $context_args['taxonomy'] ) ) {
            $query_args['tax_query'] = [
                [
                    'taxonomy' => $context_args['taxonomy'],
                    'field'    => 'slug',
                    'terms'    => $context_args['term'],
                ]
            ];
        }
        
        return $query_args;
    }

    /**
     * Check if this context is currently active
     *
     * @return bool Whether this context is active
     */
    public function is_active() {
        if ( empty( $this->post_type ) ) {
            return false;
        }
        
        // Check if we're on the relevant post type archive
        if ( is_post_type_archive( $this->post_type ) ) {
            return true;
        }
        
        // Check if we're on a taxonomy archive related to this post type
        if ( is_tax() ) {
            $taxonomy = get_query_var( 'taxonomy' );
            $tax_obj = get_taxonomy( $taxonomy );
            
            if ( $tax_obj && in_array( $this->post_type, $tax_obj->object_type ) ) {
                return true;
            }
        }
        
        return false;
    }
}