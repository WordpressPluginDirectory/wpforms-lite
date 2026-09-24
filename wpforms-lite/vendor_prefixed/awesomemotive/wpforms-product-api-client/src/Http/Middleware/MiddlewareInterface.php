<?php

namespace WPForms\Vendor\ProductApi\Http\Middleware;

use WPForms\Vendor\ProductApi\Http\Request;
use WPForms\Vendor\ProductApi\Http\Response;
use WP_Error;
/**
 * Request Middleware Interface.
 *
 * Middleware can modify requests before they are sent and handle responses.
 *
 * @since 1.0.0
 */
interface MiddlewareInterface
{
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
    public function handle(Request $request, callable $next);
}
