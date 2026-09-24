<?php

namespace WPForms\Vendor\ProductApi\Events;

use WP_Error;
use WPForms\Vendor\ProductApi\Context;
use WPForms\Vendor\ProductApi\Options;
use WPForms\Vendor\ProductApi\Http\Client;
use WPForms\Vendor\ProductApi\Http\Request;
use WPForms\Vendor\ProductApi\Auth\ChallengeSecret;
use WPForms\Vendor\ProductApi\Auth\InstallationId;
use WPForms\Vendor\ProductApi\Auth\AbstractAuthStrategy;
use WPForms\Vendor\ProductApi\Http\Middleware\LockMiddleware;
use WPForms\Vendor\ProductApi\Http\Middleware\RateLimitMiddleware;
/**
 * Events Authentication Strategy.
 *
 * @since 1.0.0
 */
class EventsAuthStrategy extends AbstractAuthStrategy
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
     * Options instance.
     *
     * @since 1.0.0
     *
     * @var Options
     */
    private $options;
    /**
     * Client instance.
     *
     * @since 1.0.0
     *
     * @var Client
     */
    private $client;
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
     * @param Context $context The context instance.
     * @param Options $options The options instance.
     * @param Client  $client  The client instance.
     */
    public function __construct(Context $context, Options $options, Client $client)
    {
        $this->context = $context;
        $this->options = $options;
        $this->client = $client;
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
        if ($this->context->is_pro()) {
            if (empty($this->context->get_license_key())) {
                return new WP_Error('license_key_missing', esc_html__('License key is required.', 'wpforms-product-api-client'));
            }
            if (!$this->context->is_license_valid()) {
                return new WP_Error('license_invalid', esc_html__('Valid license is required.', 'wpforms-product-api-client'));
            }
        }
        $token = $this->get_auth_token();
        if (is_wp_error($token)) {
            return $token;
        }
        $request->header('X-Installation-Id', $this->installation_id->get());
        $request->header('X-Token', $token);
        return \true;
    }
    /**
     * Get the authentication token.
     *
     * @since 1.0.0
     *
     * @return string|WP_Error The authentication token or WP_Error on failure.
     */
    private function get_auth_token()
    {
        $token = $this->options->get('plugin_events.auth.token');
        $site_url = $this->context->get_site_url();
        if (empty($token) || !$this->is_site_url_registered($site_url)) {
            $token = $this->register_site();
        }
        return $token;
    }
    /**
     * Register site.
     *
     * @since 1.0.0
     *
     * @return string|WP_Error The authentication token or WP_Error on failure.
     */
    private function register_site()
    {
        $action = 'events_register_site';
        $challenge_secret = new ChallengeSecret($this->options, $action);
        $response = $this->client->post('/product-events/v1/sites/register')->middleware(new RateLimitMiddleware($action, $this->options, 5, DAY_IN_SECONDS), 0)->middleware(new LockMiddleware($action, $this->options), 20)->timeout(15)->json(\array_merge($this->get_site_info(), ['installation_id' => $this->installation_id->get(), 'challenge_secret' => $challenge_secret->get()]))->send();
        if (is_wp_error($response)) {
            return $response;
        }
        if (!$response->is_successful()) {
            return $response->get_errors();
        }
        $body = $response->get_body();
        if (!isset($body['token'])) {
            return new WP_Error('invalid_response', esc_html__('Invalid response for authentication token generation.', 'wpforms-product-api-client'));
        }
        $token = $body['token'];
        $site_urls = \array_unique(\array_merge($this->options->get('plugin_events.auth.site_urls', []), [$this->clear_site_url($this->context->get_site_url())]));
        $this->options->update(['plugin_events.auth.token' => $token, 'plugin_events.auth.site_urls' => $site_urls]);
        return $token;
    }
    /**
     * Check if the site URL is registered.
     *
     * @since 1.0.0
     *
     * @param string $site_url The site URL to check.
     *
     * @return bool True if the site URL is registered, false otherwise.
     */
    private function is_site_url_registered($site_url)
    {
        $site_urls = $this->options->get('plugin_events.auth.site_urls', []);
        return \in_array($this->clear_site_url($site_url), $site_urls, \true);
    }
    /**
     * Get site info data.
     *
     * @since 1.0.0
     *
     * @return array
     */
    private function get_site_info()
    {
        $site_title = get_bloginfo('name');
        $admin_email = get_option('admin_email');
        $info = ['site_title' => $site_title, 'site_locale' => get_locale(), 'site_timezone' => wp_timezone_string(), 'admin_email' => $admin_email, 'environment' => wp_get_environment_type(), 'is_multisite' => is_multisite()];
        if (empty($admin_email)) {
            return $info;
        }
        $user = get_user_by('email', $admin_email);
        if ($user === \false) {
            return $info;
        }
        if (!empty($user->first_name)) {
            $info['admin_first_name'] = $user->first_name;
        }
        if (!empty($user->last_name)) {
            $info['admin_last_name'] = $user->last_name;
        }
        return $info;
    }
    /**
     * Clear site URL.
     *
     * @since 1.0.0
     *
     * @param string $url The site URL to clear.
     *
     * @return string The cleared site URL.
     */
    private function clear_site_url($url)
    {
        $parts = \parse_url($url);
        if ($parts === \false) {
            // Fallback for malformed URLs.
            return \strtolower(\rtrim(\str_replace(['http://', 'https://', 'www.'], '', $url), '/'));
        }
        $host = $parts['host'] ?? '';
        // Remove leading www.
        if (\strpos($host, 'www.') === 0) {
            $host = \substr($host, 4);
        }
        $path = $parts['path'] ?? '';
        $path = \rtrim($path, '/');
        $host = \strtolower($host);
        return $host . $path;
    }
}
