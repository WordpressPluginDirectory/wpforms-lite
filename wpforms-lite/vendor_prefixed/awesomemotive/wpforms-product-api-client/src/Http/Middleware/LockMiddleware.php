<?php

namespace WPForms\Vendor\ProductApi\Http\Middleware;

use WPForms\Vendor\ProductApi\Http\Request;
use WPForms\Vendor\ProductApi\Options;
use WP_Error;
/**
 * Lock Middleware.
 *
 * Prevents concurrent execution of requests by acquiring and releasing locks.
 *
 * @since 1.0.0
 */
class LockMiddleware implements MiddlewareInterface
{
    /**
     * Action name.
     *
     * @since 1.0.0
     *
     * @var string
     */
    private $action;
    /**
     * Options instance.
     *
     * @since 1.0.0
     *
     * @var Options
     */
    private $options;
    /**
     * Lock expiration time in seconds.
     *
     * @since 1.0.0
     *
     * @var int
     */
    private $expiration;
    /**
     * Error message when the operation is locked.
     *
     * @since 1.0.0
     *
     * @var string|null
     */
    private $lock_error_message;
    /**
     * Constructor.
     *
     * @since 1.0.0
     *
     * @param string  $action             Action name (e.g., 'auth_token', 'register_site_url').
     * @param Options $options            Options instance.
     * @param int     $expiration         Lock expiration time in seconds. Default: MINUTE_IN_SECONDS.
     * @param string  $lock_error_message Error message when action is locked.
     */
    public function __construct($action, Options $options, $expiration = MINUTE_IN_SECONDS, $lock_error_message = null)
    {
        $this->action = $action;
        $this->options = $options;
        $this->expiration = $expiration;
        $this->lock_error_message = $lock_error_message;
    }
    /**
     * Handle the request.
     *
     * @since 1.0.0
     *
     * @param Request  $request The request object.
     * @param callable $next    The next middleware in the stack.
     *
     * @return mixed Response|WP_Error
     */
    public function handle(Request $request, callable $next)
    {
        // Try to acquire lock to prevent concurrent execution.
        if (!$this->acquire_lock()) {
            $message = $this->lock_error_message ? $this->lock_error_message : esc_html__('Operation already started by another request.', 'wpforms-product-api-client');
            return new WP_Error('operation_in_progress', $message);
        }
        try {
            // Execute the request.
            return $next($request);
        } finally {
            // Always release lock, even if request throws exception.
            $this->release_lock();
        }
    }
    /**
     * Check if an operation is currently locked (in progress).
     *
     * @since 1.0.0
     *
     * @return bool
     */
    private function is_locked()
    {
        $lock_key = $this->get_lock_transient_key();
        return (bool) $this->options->get_transient($lock_key);
    }
    /**
     * Acquire a lock for an operation.
     *
     * @since 1.0.0
     *
     * @return bool True if lock was acquired, false if already locked.
     */
    private function acquire_lock()
    {
        if ($this->is_locked()) {
            return \false;
        }
        $this->options->set_transient($this->get_lock_transient_key(), \true, $this->expiration);
        return \true;
    }
    /**
     * Release a lock for an operation.
     *
     * @since 1.0.0
     */
    private function release_lock()
    {
        $this->options->delete_transient($this->get_lock_transient_key());
    }
    /**
     * Get the transient key for storing lock.
     *
     * @since 1.0.0
     *
     * @return string
     */
    private function get_lock_transient_key()
    {
        return "lock_{$this->action}";
    }
}
