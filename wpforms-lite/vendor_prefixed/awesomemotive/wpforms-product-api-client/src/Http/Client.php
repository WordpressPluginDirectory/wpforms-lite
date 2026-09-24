<?php

namespace WPForms\Vendor\ProductApi\Http;

use WPForms\Vendor\ProductApi\Auth\AbstractAuthStrategy;
use WPForms\Vendor\ProductApi\Auth\AuthMiddleware;
use WPForms\Vendor\ProductApi\Http\Middleware\MiddlewareInterface;
use WP_Error;
/**
 * Product API Client.
 *
 * @since 1.0.0
 */
class Client
{
    /**
     * Base URL.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected $base_url;
    /**
     * Current site URL.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected $site_url;
    /**
     * License key, or a callable resolving it when a request is sent.
     *
     * @since 1.0.0
     * @since 1.2.0 A callable is accepted.
     *
     * @var string|callable
     */
    protected $license_key;
    /**
     * User agent.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected $user_agent;
    /**
     * Authentication strategy.
     *
     * @since 1.0.0
     *
     * @var AbstractAuthStrategy|null
     */
    protected $auth_strategy = null;
    /**
     * Middleware stack.
     *
     * @since 1.0.0
     *
     * @var array<array{priority: int, middleware: MiddlewareInterface}>
     */
    protected $middleware = [];
    /**
     * Constructor.
     *
     * @since 1.0.0
     *
     * @param string $base_url Base URL.
     * @param array  $args     Initialization arguments.
     */
    public function __construct($base_url, $args)
    {
        $this->base_url = $base_url;
        $this->site_url = $args['site_url'] ?? get_site_url();
        $this->license_key = $args['license_key'] ?? '';
        $this->user_agent = $args['user_agent'] ?? '';
    }
    /**
     * Set the authentication strategy.
     *
     * Returns a new client instance with the authentication strategy set.
     * The original client remains unchanged (immutable pattern).
     *
     * @since 1.0.0
     *
     * @param AbstractAuthStrategy $auth_strategy Authentication strategy.
     *
     * @return self New client instance with auth strategy set.
     */
    public function auth_strategy(AbstractAuthStrategy $auth_strategy)
    {
        $new_client = clone $this;
        $new_client->auth_strategy = $auth_strategy;
        return $new_client;
    }
    /**
     * Register middleware.
     *
     * Returns a new client instance with the middleware added.
     * The original client remains unchanged (immutable pattern).
     *
     * @since 1.0.0
     *
     * @param MiddlewareInterface $middleware Middleware instance.
     * @param int                 $priority   Priority (lower numbers execute first). Default: 10.
     *
     * @return self New client instance with middleware added.
     */
    public function middleware(MiddlewareInterface $middleware, $priority = 10)
    {
        $new_client = clone $this;
        $new_client->middleware[] = ['priority' => (int) $priority, 'middleware' => $middleware];
        return $new_client;
    }
    /**
     * Create a GET request.
     *
     * @since 1.0.0
     *
     * @param string $endpoint Request endpoint.
     *
     * @return Request
     */
    public function get($endpoint)
    {
        return (new Request('GET', $endpoint))->client($this);
    }
    /**
     * Create a POST request.
     *
     * @since 1.0.0
     *
     * @param string $endpoint Request endpoint.
     *
     * @return Request
     */
    public function post($endpoint)
    {
        return (new Request('POST', $endpoint))->client($this);
    }
    /**
     * Create a PUT request.
     *
     * @since 1.0.0
     *
     * @param string $endpoint Request endpoint.
     *
     * @return Request
     */
    public function put($endpoint)
    {
        return (new Request('PUT', $endpoint))->client($this);
    }
    /**
     * Create a DELETE request.
     *
     * @since 1.0.0
     *
     * @param string $endpoint Request endpoint.
     *
     * @return Request
     */
    public function delete($endpoint)
    {
        return (new Request('DELETE', $endpoint))->client($this);
    }
    /**
     * Send a request.
     *
     * @since 1.0.0
     *
     * @param Request $request The request to send.
     *
     * @return Response|WP_Error
     */
    public function send(Request $request)
    {
        if ($request->get_base_url() === null) {
            $request->base_url($this->base_url);
        }
        $auth_strategy = $request->get_auth_strategy() ?? $this->auth_strategy;
        if ($auth_strategy !== null) {
            $request->middleware(new AuthMiddleware($auth_strategy), -1);
        }
        $middleware = \array_merge($this->middleware, $request->get_middleware());
        if (!empty($middleware)) {
            return $this->execute_middleware_stack($request, $middleware);
        }
        return $this->execute_request($request);
    }
    /**
     * Execute middleware stack.
     *
     * @since 1.0.0
     *
     * @param Request $request          Request object.
     * @param array   $middleware_stack Middleware stack to execute.
     *
     * @return Response|WP_Error
     */
    private function execute_middleware_stack(Request $request, array $middleware_stack)
    {
        // Sort middleware by priority (lower numbers execute first).
        \usort($middleware_stack, function ($a, $b) {
            return $a['priority'] <=> $b['priority'];
        });
        // Create the final handler that makes the actual HTTP request.
        $final_handler = function (Request $request) {
            return $this->execute_request($request);
        };
        // Build the middleware chain by wrapping each middleware around the next.
        $handler = $final_handler;
        foreach (\array_reverse($middleware_stack) as $entry) {
            $current_middleware = $entry['middleware'];
            $next_handler = $handler;
            $handler = function (Request $request) use($current_middleware, $next_handler) {
                return $current_middleware->handle($request, $next_handler);
            };
        }
        // Execute the chain.
        return $handler($request);
    }
    /**
     * Execute the actual HTTP request.
     *
     * @since 1.0.0
     *
     * @param Request $request Request object.
     *
     * @return Response|WP_Error
     */
    private function execute_request(Request $request)
    {
        // phpcs:ignore Generic.Metrics.CyclomaticComplexity.TooHigh
        $url = $request->get_url();
        $request_args = ['method' => $request->get_method()];
        $headers = $request->get_headers();
        $headers['X-Site-Url'] = $this->site_url;
        $headers['Accept'] = 'application/json';
        if (!empty($this->user_agent)) {
            $request_args['user-agent'] = $this->user_agent;
        }
        if ($request->get_json() !== null) {
            $request_args['body'] = wp_json_encode($request->get_json());
        } elseif ($request->get_query() !== null) {
            $url = add_query_arg($request->get_query(), $url);
        }
        if ($request->get_timeout() !== null) {
            $request_args['timeout'] = $request->get_timeout();
        }
        if ($request->get_blocking() !== null) {
            $request_args['blocking'] = $request->get_blocking();
        }
        $license_key = $this->resolve_license_key();
        if ($license_key !== '') {
            $headers['X-License-Key'] = $license_key;
        }
        $request_args['headers'] = $headers;
        $response = wp_remote_request($url, $request_args);
        return new Response($response);
    }
    /**
     * Resolve the license key for this request.
     *
     * A plain string is used as is, a closure or array callable is called, so a key configured
     * lazily is read at send time rather than at construction.
     *
     * @since 1.2.0
     *
     * @return string
     */
    private function resolve_license_key()
    {
        $license_key = $this->license_key;
        if (!\is_string($license_key) && \is_callable($license_key)) {
            $license_key = \call_user_func($license_key);
        }
        return (string) $license_key;
    }
}
