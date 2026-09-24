<?php

namespace WPForms\Vendor\ProductApi\Http\Middleware;

use WPForms\Vendor\ProductApi\Http\Request;
use WPForms\Vendor\ProductApi\Http\Response;
use WPForms\Vendor\ProductApi\Options;
use WP_Error;
/**
 * Rate Limit Middleware.
 *
 * Limits the number of requests per time period to prevent abuse.
 *
 * @since 1.0.0
 */
class RateLimitMiddleware implements MiddlewareInterface
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
     * Maximum number of requests allowed per time period.
     *
     * @since 1.0.0
     *
     * @var int
     */
    private $max_requests;
    /**
     * Time period in seconds.
     *
     * @since 1.0.0
     *
     * @var int
     */
    private $period;
    /**
     * Error message when rate limit is exceeded.
     *
     * @since 1.0.0
     *
     * @var string|null
     */
    private $error_message;
    /**
     * Constructor.
     *
     * @since 1.0.0
     *
     * @param string  $action        Action name (e.g., 'auth_token', 'api_requests').
     * @param Options $options       Options instance.
     * @param int     $max_requests  Maximum number of requests allowed per time period.
     * @param int     $period        Time period in seconds.
     * @param string  $error_message Error message when rate limit is exceeded.
     */
    public function __construct($action, Options $options, $max_requests, $period, $error_message = null)
    {
        $this->action = $action;
        $this->options = $options;
        $this->max_requests = (int) $max_requests;
        $this->period = (int) $period;
        $this->error_message = $error_message;
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
        // Check if rate limit is exceeded.
        if ($this->is_rate_limited()) {
            $message = $this->error_message ? $this->error_message : esc_html__('Rate limit exceeded. Please try again later.', 'wpforms-product-api-client');
            return new WP_Error('rate_limit_exceeded', $message);
        }
        $response = $next($request);
        // Increment request count, only if the request was actually made.
        if ($response instanceof Response) {
            $this->increment_request_count();
        }
        return $response;
    }
    /**
     * Check if rate limit is exceeded.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    private function is_rate_limited()
    {
        return $this->get_window()['count'] >= $this->max_requests;
    }
    /**
     * Increment request count for the time period.
     *
     * The window is fixed: it starts with the first request counted and ends one
     * period later, whatever happens in between. Writing the transient with the full
     * period on every request made the window slide instead, ending one period after
     * the last request, so a site sending steadily never got its count back and, once
     * over the limit, went dark for a whole period from that point.
     *
     * @since 1.0.0
     * @since 1.2.1 The window no longer restarts on every request.
     */
    private function increment_request_count()
    {
        $window = $this->get_window();
        ++$window['count'];
        $this->options->set_transient($this->get_rate_limit_transient_key(), $window, $window['window_start'] + $this->period - \time());
    }
    /**
     * Get the current window: how many requests it holds and when it started.
     *
     * @since 1.2.1
     *
     * @return array
     */
    private function get_window()
    {
        $stored = $this->options->get_transient($this->get_rate_limit_transient_key(), null);
        if (\is_array($stored) && isset($stored['count'], $stored['window_start'])) {
            $window = ['count' => (int) $stored['count'], 'window_start' => (int) $stored['window_start']];
            // The transient expires with the window, but a cache that ignores expirations
            // or a clock that moved must not leave a spent window in place for good.
            return $window['window_start'] + $this->period > \time() ? $window : $this->new_window();
        }
        $window = $this->new_window();
        // A count written before 1.2.1 has no start. Treating it as a window that starts
        // now keeps the count and costs at most one more period, once, after an update.
        // Written back at once, with the period as its expiration: a count already at the
        // limit refuses every request, so nothing else would ever write it, and on a cache
        // that ignores expirations the integer would refuse for good.
        if (\is_numeric($stored)) {
            $window['count'] = (int) $stored;
            $this->options->set_transient($this->get_rate_limit_transient_key(), $window, $this->period);
        }
        return $window;
    }
    /**
     * An empty window starting now.
     *
     * @since 1.2.1
     *
     * @return array
     */
    private function new_window()
    {
        return ['count' => 0, 'window_start' => \time()];
    }
    /**
     * Get the transient key for storing rate limit count.
     *
     * @since 1.0.0
     *
     * @return string
     */
    private function get_rate_limit_transient_key()
    {
        return "rate_limit_{$this->action}";
    }
}
