<?php
/**
 * Filter System Error Handler
 *
 * Centralizes error handling for the filter system.
 *
 * @package CP_Library\Filters
 * @since 1.6.0
 */

namespace CP_Library\Filters;

/**
 * ErrorHandler class - Centralized error handling.
 *
 * This class provides centralized error handling functionality for the
 * filter system, including methods for standardized error logging,
 * error response formatting, and integration with the WordPress error
 * reporting system.
 *
 * @since 1.6.0
 */
class ErrorHandler {

    /**
     * Instance of this class
     *
     * @var ErrorHandler
     */
    private static $instance = null;

    /**
     * Get the singleton instance
     *
     * @return ErrorHandler
     */
    public static function get_instance() {
        if (null === self::$instance) {
            self::$instance = new self();
        }

        return self::$instance;
    }

    /**
     * Constructor
     */
    private function __construct() {
        // Add custom error handling for AJAX requests
        add_action('wp_ajax_nopriv_cpl_filter_error', [$this, 'handle_ajax_error']);
        add_action('wp_ajax_cpl_filter_error', [$this, 'handle_ajax_error']);
    }

    /**
     * Log an error message
     *
     * @param string $message   Error message
     * @param string $error_code Error code
     * @param array  $context   Additional context data
     */
    public function log_error($message, $error_code = '', $context = []) {
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            return;
        }

        $log_entry = sprintf(
            '[CPL Filter Error] [%s] %s | %s',
            !empty($error_code) ? $error_code : 'unknown',
            $message,
            !empty($context) ? json_encode($context) : '{}'
        );

        error_log($log_entry);
    }

    /**
     * Handle AJAX error reporting from client-side
     */
    public function handle_ajax_error() {
        // Verify nonce
        if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'cpl_filter_nonce')) {
            wp_send_json_error([
                'code'    => ErrorCodes::UNAUTHORIZED,
                'message' => 'Security check failed.',
                'status'  => 401
            ]);
        }

        // Get error data
        $error_code = isset($_POST['code']) ? sanitize_text_field($_POST['code']) : ErrorCodes::GENERAL_ERROR;
        $message = isset($_POST['message']) ? sanitize_text_field($_POST['message']) : '';
        $data = isset($_POST['data']) ? $_POST['data'] : [];

        // Sanitize data if it's an array
        if (is_array($data)) {
            array_walk_recursive($data, function(&$value) {
                $value = sanitize_text_field($value);
            });
        } else {
            $data = sanitize_text_field($data);
        }

        // Log the client-side error
        $this->log_error(
            'Client-side error: ' . $message,
            $error_code,
            [
                'data' => $data,
                'url' => isset($_POST['url']) ? esc_url_raw($_POST['url']) : '',
                'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field($_SERVER['HTTP_USER_AGENT']) : '',
            ]
        );

        // Return confirmation
        wp_send_json_success([
            'message' => 'Error logged successfully.',
        ]);
    }

    /**
     * Format an error for display to users
     *
     * @param string $error_code Error code
     * @param string $message    Custom message (optional)
     * @param bool   $include_code Whether to include the error code in the displayed message
     * 
     * @return string Formatted error message
     */
    public function format_user_error($error_code, $message = '', $include_code = false) {
        if (empty($message)) {
            $message = ErrorCodes::get_message($error_code);
        }

        // For security, don't show internal error details to users in production
        if (!defined('WP_DEBUG') || !WP_DEBUG) {
            // Use generic messages for critical errors
            if (in_array($error_code, [
                ErrorCodes::INTERNAL_ERROR,
                ErrorCodes::QUERY_ERROR,
                ErrorCodes::FILTER_REGISTRATION,
                ErrorCodes::CACHE_ERROR,
            ])) {
                $message = __('An error occurred. Please try again later.', 'cp-library');
            }
        }

        // Include error code in debug mode if requested
        if (($include_code || (defined('WP_DEBUG') && WP_DEBUG)) && !empty($error_code)) {
            $message = sprintf('%s [%s]', $message, $error_code);
        }

        return $message;
    }

    /**
     * Handle an exception, optionally sending a JSON response
     *
     * @param \Exception $exception The exception to handle
     * @param bool       $send_json Whether to send a JSON response
     * 
     * @return array|void Error data array if $send_json is false, void otherwise
     */
    public function handle_exception($exception, $send_json = true) {
        // Default error data
        $error_data = [
            'code'    => ErrorCodes::GENERAL_ERROR,
            'message' => $exception->getMessage(),
            'status'  => 500,
        ];

        // Get more specific data for our custom exception
        if ($exception instanceof FilterException) {
            $error_data = $exception->get_error_response();
        }

        // Log the error
        $this->log_error(
            $error_data['message'],
            $error_data['code'],
            [
                'exception' => get_class($exception),
                'file'      => $exception->getFile(),
                'line'      => $exception->getLine(),
                'trace'     => $exception->getTraceAsString(),
            ]
        );

        // Send JSON response if requested
        if ($send_json) {
            wp_send_json_error($error_data);
        }

        return $error_data;
    }
}