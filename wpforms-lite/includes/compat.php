<?php
/**
 * Compatibility shims (polyfills) for WordPress functions that bundled third-party
 * libraries may call before WPForms' minimum supported WordPress version (5.5) provides them.
 *
 * Each shim is guarded by a function_exists() check, so it only defines the function on older
 * WordPress versions and never collides with WordPress core.
 *
 * @since 2.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! function_exists( 'wp_is_serving_rest_request' ) ) {
	/**
	 * Determine whether the current request is a WordPress REST API request.
	 *
	 * Polyfill for the function introduced in WordPress 6.5.
	 *
	 * @since 2.0.0
	 *
	 * @return bool Whether the current request is a REST API request.
	 */
	function wp_is_serving_rest_request() {

		return defined( 'REST_REQUEST' ) && REST_REQUEST;
	}
}
