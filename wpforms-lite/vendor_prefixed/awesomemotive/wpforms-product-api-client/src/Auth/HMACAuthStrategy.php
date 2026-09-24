<?php

namespace WPForms\Vendor\ProductApi\Auth;

use WP_Error;
use WPForms\Vendor\ProductApi\Context;
use WPForms\Vendor\ProductApi\Options;
use WPForms\Vendor\ProductApi\Http\Request;
/**
 * HMAC Signature Authentication Strategy.
 *
 * @since 1.0.0
 */
class HMACAuthStrategy extends AbstractAuthStrategy
{
    /**
     * Context instance.
     *
     * @since 1.0.0
     *
     * @var Context
     */
    private $context;
    /**
     * Auth options instance.
     *
     * @since 1.0.2
     *
     * @var AuthOptions
     */
    private $auth_options;
    /**
     * Options instance.
     *
     * @since 1.0.0
     *
     * @var Options
     */
    private $options;
    /**
     * Installation ID instance.
     *
     * @since 1.0.0
     *
     * @var InstallationId
     */
    private $installation_id;
    /**
     * Constructor.
     *
     * @since 1.0.0
     *
     * @param Context     $context      The context instance.
     * @param AuthOptions $auth_options The auth options instance.
     * @param Options     $options      The options instance.
     */
    public function __construct(Context $context, AuthOptions $auth_options, Options $options)
    {
        $this->context = $context;
        $this->auth_options = $auth_options;
        $this->options = $options;
        $this->installation_id = new InstallationId($this->options);
    }
    /**
     * Authenticate the request.
     *
     * @since 1.0.0
     *
     * @param Request $request The request to authenticate.
     *
     * @return true|WP_Error Returns true on success, WP_Error on failure.
     */
    public function authenticate_request(Request $request)
    {
        $credentials = $this->get_credentials();
        if (is_wp_error($credentials)) {
            return $credentials;
        }
        $site_id = $credentials['site_id'];
        $signing_secret = $credentials['signing_secret'];
        $verification_key = $credentials['verification_key'];
        // Generate timestamp.
        $timestamp = \time();
        // Get request body for hashing.
        $json_body = $request->get_json();
        $body = $json_body !== null ? wp_json_encode($json_body) : '';
        $body_hash = \hash('sha256', $body);
        // Build canonical string.
        $method = \strtolower($request->get_method());
        $path = $request->get_endpoint();
        $canonical_string = $this->build_canonical_string($method, $path, $timestamp, $body_hash);
        // Generate HMAC signature.
        $signature = \hash_hmac('sha256', $canonical_string, $signing_secret);
        // Add authentication headers.
        $request->header('X-Site-Id', $site_id);
        $request->header('X-Installation-Id', $this->installation_id->get());
        $request->header('X-Verification-Key', $verification_key);
        $request->header('X-Timestamp', (string) $timestamp);
        $request->header('X-Signature', $signature);
        return \true;
    }
    /**
     * Get the authentication credentials.
     *
     * @since 1.0.0
     *
     * @return array|WP_Error The credentials array or WP_Error on failure.
     */
    private function get_credentials()
    {
        $site_id = $this->auth_options->get('site_id');
        $signing_secret = $this->auth_options->get('signing_secret');
        $verification_key = $this->auth_options->get('verification_key');
        if (empty($site_id) || empty($signing_secret) || empty($verification_key)) {
            return new WP_Error('site_not_registered', esc_html__('Site is not registered. Please register the site first.', 'wpforms-product-api-client'));
        }
        return ['site_id' => $site_id, 'signing_secret' => $signing_secret, 'verification_key' => $verification_key];
    }
    /**
     * Build canonical string for HMAC signing.
     *
     * Format:
     * <lowercase HTTP method>\n
     * <path>\n
     * <timestamp>\n
     * <sha256(body)>
     *
     * @since 1.0.0
     *
     * @param string $method    HTTP method (lowercase).
     * @param string $path      Request path.
     * @param int    $timestamp Unix timestamp.
     * @param string $body_hash SHA256 hash of the request body.
     *
     * @return string The canonical string.
     */
    private function build_canonical_string($method, $path, $timestamp, $body_hash)
    {
        return $method . "\n" . $path . "\n" . $timestamp . "\n" . $body_hash;
    }
}
