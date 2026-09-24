<?php

namespace WPForms\Vendor\ProductApi\Auth;

use WPForms\Vendor\ProductApi\Http\Request;
use WP_Error;
/**
 * Abstract Authentication Strategy.
 *
 * @since 1.0.0
 */
abstract class AbstractAuthStrategy
{
    /**
     * Authenticate the request by modifying it (e.g., adding auth headers).
     *
     * @since 1.0.0
     *
     * @param Request $request The request to authenticate.
     *
     * @return true|WP_Error Returns true on success, WP_Error on failure.
     */
    public abstract function authenticate_request(Request $request);
}
