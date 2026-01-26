# CP Library Filter System: Error Handling

This document describes the error handling system for the CP Library filter system.

## Overview

The error handling system in the CP Library filter system is designed to provide consistent, informative, and user-friendly error handling across both server-side PHP code and client-side JavaScript. It includes:

- Standardized error codes
- Consistent error message formatting
- Exception handling with contextual information
- Client-side error handling and reporting
- Accessible error messages for all users
- Debugging assistance during development

## Server-Side Components

### Error Codes

The `ErrorCodes` class provides a centralized repository of error codes, messages, and HTTP status codes. Key features:

- Constants for all possible error types
- Default error messages for each code
- Associated HTTP status codes
- Methods to create standardized error responses

```php
// Example usage
use CP_Library\Filters\ErrorCodes;

// Get a standard error response
$error = ErrorCodes::get_error_response(
    ErrorCodes::FACET_NOT_FOUND,
    'The requested filter facet was not found: ' . $facet_id,
    ['facet_id' => $facet_id]
);

// Send a JSON error response
ErrorCodes::send_json_error(
    ErrorCodes::INVALID_POST_TYPE,
    'Invalid post type for filter operation',
    ['post_type' => $post_type]
);
```

### FilterException

The `FilterException` class extends the base plugin exception to provide specialized error handling for the filter system:

- Integration with the `ErrorCodes` class
- Additional context data for errors
- HTTP status code handling
- Methods for generating standardized error responses
- Automatic logging when in debug mode

```php
// Example usage
use CP_Library\Filters\FilterException;
use CP_Library\Filters\ErrorCodes;

try {
    // Some operation that might fail
    if (!$facet) {
        throw new FilterException(
            'Invalid facet ID: ' . $facet_id,
            ErrorCodes::FACET_NOT_FOUND,
            ['facet_id' => $facet_id]
        );
    }
} catch (FilterException $e) {
    // Handle the exception
    $e->send_json_error(); // Sends wp_send_json_error() with formatted data
}
```

### ErrorHandler

The `ErrorHandler` class centralizes error handling operations:

- Singleton instance for global access
- Methods for standardized error logging
- AJAX endpoint for client-side error reporting
- User-friendly error message formatting
- Exception handling with context preservation

```php
// Example usage
use CP_Library\Filters\ErrorHandler;

// Get the error handler instance
$error_handler = ErrorHandler::get_instance();

// Log an error
$error_handler->log_error(
    'Failed to load filter options',
    ErrorCodes::QUERY_ERROR,
    ['query' => $query, 'error' => $db->last_error]
);

// Handle an exception
try {
    // Operation that might throw an exception
} catch (\Exception $e) {
    $error_handler->handle_exception($e, true); // true = send JSON response
}
```

## Client-Side Components

### Error Handling Module

The JavaScript error handling module (`errorHandler.js`) provides:

- Parallel error codes to the server-side system
- User-friendly error messages
- Error reporting to the server
- Consistent error formatting
- Methods for handling AJAX errors
- Methods for handling JavaScript exceptions

```javascript
// Example usage
import * as ErrorHandler from './errorHandler';

// Report an error to the server
ErrorHandler.reportError(
    ErrorHandler.ERROR_CODES.NETWORK_ERROR,
    'Failed to connect to the server',
    { url: requestUrl }
);

// Create a formatted error
const error = ErrorHandler.createError(
    ErrorHandler.ERROR_CODES.INVALID_PARAMETER,
    'Invalid filter parameter',
    { param: 'facet-type', value: value }
);

// Display an error to the user
ErrorHandler.displayError(container, error);

// Handle an AJAX error
jQuery.ajax({
    // ...
    error: (jqXHR, textStatus, errorThrown) => {
        ErrorHandler.handleAjaxError(jqXHR, textStatus, errorThrown, container);
    }
});

// Handle a JavaScript error
try {
    // Operation that might throw an error
} catch (error) {
    ErrorHandler.handleCaughtError(error, container);
}
```

### Integration with Filter Class

The main `CPLibraryFilter` class integrates with the error handling system:

- Error logging with context information
- User-friendly error display
- Announcements for screen readers
- Error recovery mechanisms
- Debug mode handling

## Error Categories

The error handling system covers these categories of errors:

### General Errors
- General errors
- Invalid requests
- Missing parameters
- Authentication issues
- Access control issues
- Resource not found errors
- Internal server errors

### Filter Manager Errors
- Manager not found
- Invalid post type
- Registration errors

### Facet Errors
- Facet not found
- Invalid facet configuration
- Incompatible facet types

### AJAX Errors
- Invalid actions
- Response processing errors
- Request formation errors

### Query Errors
- Database query errors
- Invalid query parameters

### Validation Errors
- Input validation failures
- Context validation errors

### Client-Specific Errors
- Network connectivity issues
- Request timeouts
- Response parsing errors

## Best Practices

When working with the error handling system:

1. **Use Specific Error Codes**: Always use the most specific error code available.

2. **Provide Context**: Include relevant context data with errors to assist in debugging.

3. **Handle Exceptions**: Use try/catch blocks to gracefully handle exceptions.

4. **User-Friendly Messages**: Ensure error messages displayed to users are helpful.

5. **Accessibility**: Ensure errors are announced to screen readers.

6. **Recovery Mechanisms**: Provide ways for users to recover from errors when possible.

7. **Log Errors**: Always log errors for debugging purposes.

## Debugging

In development environments (when `WP_DEBUG` is true):

- PHP errors are logged to the error log
- JavaScript errors are logged to the console
- More detailed error messages are shown to users
- Error codes are included in displayed messages

## Security Considerations

The error handling system balances security with usability:

- In production, internal error details are not exposed to users
- Error messages for users are sanitized
- Error data is sanitized before logging
- AJAX error reporting requires valid nonces
- AJAX errors include minimal necessary information