<?php

namespace WPForms\Vendor\ProductApi\Http;

use LogicException;
use WPForms\Vendor\ProductApi\Auth\AbstractAuthStrategy;
use WPForms\Vendor\ProductApi\Http\Middleware\MiddlewareInterface;
use WP_Error;
/**
 * Product API Request (Pending Request pattern).
 *
 * @since 1.0.0
 */
class Request
{
    /**
     * Request method.
     *
     * @since 1.0.0
     *
     * @var string
     */
    private $method;
    /**
     * Request endpoint.
     *
     * @since 1.0.0
     *
     * @var string
     */
    private $endpoint;
    /**
     * Base URL.
     *
     * @since 1.0.0
     *
     * @var string|null
     */
    private $base_url = null;
    /**
     * JSON body data (POST, PUT, PATCH requests).
     *
     * @since 1.0.0
     *
     * @var array|null
     */
    private $json = null;
    /**
     * Query parameters (GET requests).
     *
     * @since 1.0.0
     *
     * @var array|null
     */
    private $query = null;
    /**
     * Request timeout in seconds.
     *
     * @since 1.0.0
     *
     * @var int|null
     */
    private $timeout = null;
    /**
     * Request headers.
     *
     * @since 1.0.0
     *
     * @var array
     */
    private $headers = [];
    /**
     * Request blocking.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    private $blocking = null;
    /**
     * Authentication strategy.
     *
     * @since 1.0.0
     *
     * @var AbstractAuthStrategy|null
     */
    private $auth_strategy = null;
    /**
     * Request-specific middleware stack.
     *
     * @since 1.0.0
     *
     * @var array<array{priority: int, middleware: MiddlewareInterface}>
     */
    private $middleware = [];
    /**
     * Product API client.
     *
     * @since 1.0.0
     *
     * @var Client
     */
    private $client = null;
    /**
     * Constructor.
     *
     * @since 1.0.0
     *
     * @param string $method   Request method.
     * @param string $endpoint Request endpoint.
     */
    public function __construct($method, $endpoint)
    {
        $this->method = $method;
        $this->endpoint = '/' . \trim($endpoint, '/');
    }
    /**
     * Set the base URL.
     *
     * @since 1.0.0
     *
     * @param string $base_url The base URL.
     *
     * @return self
     */
    public function base_url($base_url)
    {
        $this->base_url = untrailingslashit($base_url);
        return $this;
    }
    /**
     * Set JSON body data (POST, PUT, PATCH requests).
     *
     * @since 1.0.0
     *
     * @param array $json JSON body data.
     *
     * @return self
     */
    public function json(array $json)
    {
        $this->json = $json;
        $this->header('Content-Type', 'application/json');
        return $this;
    }
    /**
     * Add a request header.
     *
     * @since 1.0.0
     *
     * @param string $name  Header name.
     * @param string $value Header value.
     *
     * @return self
     */
    public function header($name, $value)
    {
        $this->headers[$name] = $value;
        return $this;
    }
    /**
     * Set query parameters (GET requests).
     *
     * @since 1.0.0
     *
     * @param array $query Query parameters.
     *
     * @return self
     */
    public function query(array $query)
    {
        $this->query = $query;
        return $this;
    }
    /**
     * Set request timeout.
     *
     * @since 1.0.0
     *
     * @param int $timeout Timeout in seconds.
     *
     * @return self
     */
    public function timeout($timeout)
    {
        $this->timeout = $timeout;
        return $this;
    }
    /**
     * Set request headers.
     *
     * Merges the provided headers with existing headers. If a header with the same name
     * already exists, it will be overwritten with the new value.
     *
     * @since 1.0.0
     *
     * @param array $headers Request headers to merge.
     *
     * @return self
     */
    public function headers(array $headers)
    {
        $this->headers = \array_merge($this->headers, $headers);
        return $this;
    }
    /**
     * Set request blocking.
     *
     * @since 1.0.0
     *
     * @param bool $blocking Request blocking.
     *
     * @return self
     */
    public function blocking($blocking)
    {
        $this->blocking = $blocking;
        return $this;
    }
    /**
     * Set authentication strategy.
     *
     * @since 1.0.0
     *
     * @param AbstractAuthStrategy|null $auth_strategy Authentication strategy.
     *
     * @return self
     */
    public function auth_strategy($auth_strategy)
    {
        $this->auth_strategy = $auth_strategy;
        return $this;
    }
    /**
     * Register middleware for this request.
     *
     * @since 1.0.0
     *
     * @param MiddlewareInterface $middleware Middleware instance.
     * @param int                 $priority   Priority (lower numbers execute first). Default: 10.
     *
     * @return self
     */
    public function middleware(MiddlewareInterface $middleware, $priority = 10)
    {
        $this->middleware[] = ['priority' => (int) $priority, 'middleware' => $middleware];
        return $this;
    }
    /**
     * Set the client.
     *
     * @since 1.0.0
     *
     * @param Client $client The client.
     *
     * @return self
     */
    public function client(Client $client)
    {
        $this->client = $client;
        return $this;
    }
    /**
     * Get request method.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function get_method()
    {
        return $this->method;
    }
    /**
     * Get request endpoint.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function get_endpoint()
    {
        return $this->endpoint;
    }
    /**
     * Get the base URL.
     *
     * @since 1.0.0
     *
     * @return string|null
     */
    public function get_base_url()
    {
        return $this->base_url;
    }
    /**
     * Get JSON body data (POST, PUT, PATCH requests).
     *
     * @since 1.0.0
     *
     * @return array|null
     */
    public function get_json()
    {
        return $this->json;
    }
    /**
     * Get query parameters (GET requests).
     *
     * @since 1.0.0
     *
     * @return array|null
     */
    public function get_query()
    {
        return $this->query;
    }
    /**
     * Get request timeout.
     *
     * @since 1.0.0
     *
     * @return int|null
     */
    public function get_timeout()
    {
        return $this->timeout;
    }
    /**
     * Get request headers.
     *
     * @since 1.0.0
     *
     * @return array
     */
    public function get_headers()
    {
        return $this->headers;
    }
    /**
     * Get request blocking.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public function get_blocking()
    {
        return $this->blocking;
    }
    /**
     * Get authentication strategy.
     *
     * @since 1.0.0
     *
     * @return AbstractAuthStrategy|null
     */
    public function get_auth_strategy()
    {
        return $this->auth_strategy;
    }
    /**
     * Get request middleware.
     *
     * @since 1.0.0
     *
     * @return array<array{priority: int, middleware: MiddlewareInterface}>
     */
    public function get_middleware()
    {
        return $this->middleware;
    }
    /**
     * Get the request URL.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function get_url()
    {
        return $this->base_url . $this->endpoint;
    }
    /**
     * Send the request.
     *
     * This is a helper methods that allow sending a request from the
     * request object directly in a fluent way.
     *
     * @since 1.0.0
     *
     * @return Response|WP_Error
     *
     * phpcs:ignore Squiz.Commenting.FunctionCommentThrowTag.Missing
     */
    public function send()
    {
        if ($this->client === null) {
            throw new LogicException('The client is not set. Please set the client using the client() method before sending the request.');
        }
        return $this->client->send($this);
    }
}
