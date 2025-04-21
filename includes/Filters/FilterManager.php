<?php
/**
 * Filter Manager Class
 *
 * Provides registry and factory functionality for filter implementations.
 *
 * @package CP_Library\Filters
 * @since 1.6.0
 */

namespace CP_Library\Filters;

/**
 * FilterManager class - Registry and factory for filter implementations.
 *
 * This class manages different filter implementations for various post types.
 * It follows a registry pattern where filter managers are registered and retrieved
 * for specific post types.
 *
 * @since 1.6.0
 */
class FilterManager {

    /**
     * Registered filter managers by post type.
     *
     * @var array
     */
    private static $managers = [];

    /**
     * Register a filter manager for a post type.
     *
     * @param string $post_type The post type identifier
     * @param string $class     The fully qualified class name
     * @param array  $args      Optional arguments for the filter manager
     *
     * @return bool True if registration succeeded, false otherwise
     */
    public static function register_filter_manager( $post_type, $class, $args = [] ) {
        // Validate inputs
        if ( empty( $post_type ) || empty( $class ) ) {
            return false;
        }

        if ( ! class_exists( $class ) ) {
            return false;
        }

        // Store the registration
        self::$managers[ $post_type ] = [
            'class' => $class,
            'args'  => $args,
            'instance' => new $class,
        ];

        return true;
    }

    /**
     * Get a filter manager for a post type
     *
     * @param string $post_type The post type identifier
     * @param array  $args      Optional. Override the arguments used when creating the instance
     *
     * @return object|null The filter manager instance or null if not registered
     */
    public static function get_filter_manager( $post_type, $args = [] ) {
        if ( empty( $post_type ) || ! isset( self::$managers[ $post_type ] ) ) {
            return null;
        }

        $manager = self::$managers[ $post_type ];

        // Create instance if it doesn't exist
        if ( null === $manager['instance'] ) {
            $args = ! empty( $args ) ? $args : $manager['args'];
            $class = $manager['class'];
            self::$managers[ $post_type ]['instance'] = new $class( $args );
        }

        return self::$managers[ $post_type ]['instance'];
    }

    /**
     * Get the current filter manager based on post type in the query
     *
     * @return object|null The current filter manager or null if none found
     */
    public static function get_current_manager() {
        // Check if we're on a singular post
        if ( is_singular() && ! empty( get_post_type() ) ) {
            return self::get_filter_manager( get_post_type() );
        }

        // Check if we're on an archive page
        global $wp_query;
        if ( isset( $wp_query ) && isset( $wp_query->query_vars['post_type'] ) ) {
            $post_type = $wp_query->query_vars['post_type'];
            if ( is_array( $post_type ) && ! empty( $post_type ) ) {
                $post_type = reset( $post_type ); // Get the first post type
            }

            if ( ! empty( $post_type ) ) {
                return self::get_filter_manager( $post_type );
            }
        }

        return null;
    }

    /**
     * Get all registered filter managers
     *
     * @return array Associative array of post_type => filter manager instance
     */
    public static function get_active_managers() {
        $active = [];

        foreach ( array_keys( self::$managers ) as $post_type ) {
            $manager = self::get_filter_manager( $post_type );
            if ( $manager ) {
                $active[ $post_type ] = $manager;
            }
        }

        return $active;
    }

    /**
     * Get all registered filter manager configurations
     *
     * @return array Array of filter manager configurations indexed by post type
     */
    public static function get_registered_managers() {
        return self::$managers;
    }

    /**
     * Enqueue scripts and styles for all registered filter managers
     */
    public static function enqueue_scripts() {
        foreach ( self::get_active_managers() as $manager ) {
            if ( method_exists( $manager, 'enqueue_scripts' ) ) {
                $manager->enqueue_scripts();
            }
        }
    }
}
