<?php

namespace WPForms\Vendor\ProductApi\Auth;

use WPForms\Vendor\ProductApi\Http\Middleware\MiddlewareInterface;
use WPForms\Vendor\ProductApi\Http\Request;
use WPForms\Vendor\ProductApi\Http\Response;
use WP_Error;
/**
 * Authentication Middleware.
 *
 * @since 1.0.0
 */
class AuthMiddleware implements MiddlewareInterface
{
    /**
     * Authentication strategy.
     *
     * @since 1.0.0
     *
     * @var AbstractAuthStrategy
     */
    private $auth_strategy;
    /**
     * Constructor.
     *
     * @since 1.0.0
     *
     * @param AbstractAuthStrategy $auth_strategy Authentication strategy.
     */
    public function __construct(AbstractAuthStrategy $auth_strategy)
    {
        $this->auth_strategy = $auth_strategy;
    }
    /**
     * Handle the request.
     *
     * @since 1.0.0
     *
     * @param Request  $request The request object.
     * @param callable $next    The next middleware in the stack.
     *
     * @return Response|WP_Error
     */
    public function handle(Request $request, callable $next)
    {
        $auth_result = $this->auth_strategy->authenticate_request($request);
        if (is_wp_error($auth_result)) {
            return $auth_result;
        }
        return $next($request);
    }
}
