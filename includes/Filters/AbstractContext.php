<?php
/**
 * Abstract Context Class
 *
 * Base class for filter contexts that define how filters are applied in different situations.
 *
 * @package CP_Library\Filters
 * @since 1.6.0
 */

namespace CP_Library\Filters;

/**
 * AbstractContext class - Base implementation for filter contexts.
 *
 * Contexts define different scenarios where filters are applied, such as archives,
 * service types, or other specialized views. Each context can modify how queries
 * are constructed for filtering.
 *
 * @since 1.6.0
 */
abstract class AbstractContext {

    /**
     * Context identifier
     *
     * @var string
     */
    protected $id = '';

    /**
     * Context display label
     *
     * @var string
     */
    protected $label = '';

    /**
     * Post type this context applies to
     *
     * @var string
     */
    protected $post_type = '';

    /**
     * Constructor
     *
     * @param array $args Configuration arguments
     */
    public function __construct( $args = [] ) {
        if ( isset( $args['label'] ) ) {
            $this->label = $args['label'];
        }

        if ( isset( $args['post_type'] ) ) {
            $this->post_type = $args['post_type'];
        }
    }

    /**
     * Get the context identifier
     *
     * @return string The context ID
     */
    public function get_id() {
        return $this->id;
    }

    /**
     * Get the context display name
     *
     * @return string The context display name
     */
    public function get_label() {
        return $this->label;
    }

    /**
     * Get the post type this context applies to
     *
     * @return string Post type name
     */
    public function get_post_type() {
        return $this->post_type;
    }

    /**
     * Modify a query for this context
     *
     * @param array $query_args WP_Query arguments
     * @param array $context_args Context-specific arguments
     * @return array Modified query arguments
     */
    abstract public function modify_query( $query_args, $context_args );

    /**
     * Check if this context is currently active
     *
     * @return bool Whether this context is active
     */
    abstract public function is_active();
}