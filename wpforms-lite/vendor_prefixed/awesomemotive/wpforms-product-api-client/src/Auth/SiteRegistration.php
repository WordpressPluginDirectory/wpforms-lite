<?php

namespace WPForms\Vendor\ProductApi\Auth;

use WP_Error;
use WPForms\Vendor\ProductApi\Context;
use WPForms\Vendor\ProductApi\Options;
use WPForms\Vendor\ProductApi\Http\Client;
use WPForms\Vendor\ProductApi\Http\Middleware\LockMiddleware;
use WPForms\Vendor\ProductApi\Http\Middleware\RateLimitMiddleware;
/**
 * Site Registration.
 *
 * Handles explicit site registration with the Product API.
 *
 * @since 1.0.1
 */
class SiteRegistration
{
    /**
     * Context instance.
     *
     * @since 1.0.1
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
     * @since 1.0.1
     *
     * @var Options
     */
    private $options;
    /**
     * Client instance.
     *
     * @since 1.0.1
     *
     * @var Client
     */
    private $client;
    /**
     * Installation ID instance.
     *
     * @since 1.0.1
     *
     * @var InstallationId
     */
    private $installation_id;
    /**
     * Constructor.
     *
     * @since 1.0.1
     *
     * @param Context     $context      The context instance.
     * @param AuthOptions $auth_options The auth options instance.
     * @param Options     $options      The options instance.
     * @param Client      $client       The client instance.
     */
    public function __construct(Context $context, AuthOptions $auth_options, Options $options, Client $client)
    {
        $this->context = $context;
        $this->auth_options = $auth_options;
        $this->options = $options;
        $this->client = $client;
        $this->installation_id = new InstallationId($this->options);
    }
    /**
     * Check if the site is registered.
     *
     * @since 1.0.1
     *
     * @return bool True if registered, false otherwise.
     */
    public function is_registered()
    {
        $site_id = $this->auth_options->get('site_id');
        $signing_secret = $this->auth_options->get('signing_secret');
        $verification_key = $this->auth_options->get('verification_key');
        $site_url = $this->context->get_site_url();
        return !empty($site_id) && !empty($signing_secret) && !empty($verification_key) && $this->is_site_url_registered($site_url);
    }
    /**
     * Register the site with the Product API.
     *
     * @since 1.0.1
     *
     * @param array $args {
     *     Optional registration arguments.
     *
     *     @type array $contact {
     *         Contact info from subscription form. Falls back to current user data if not provided.
     *
     *         @type string $email      Contact email.
     *         @type string $first_name Contact first name.
     *         @type string $last_name  Contact last name.
     *     }
     * }
     *
     * @return array|WP_Error The credentials array or WP_Error on failure.
     */
    public function register(array $args = [])
    {
        $action = 'auth_register_site';
        $challenge_secret = new ChallengeSecret($this->options, $action);
        $response = $this->client->post('/auth/v2/keys')->middleware(new RateLimitMiddleware($action, $this->options, 5, DAY_IN_SECONDS), 0)->middleware(new LockMiddleware($action, $this->options), 20)->timeout(15)->json(\array_merge($this->get_site_info($args), ['installation_id' => $this->installation_id->get(), 'site_url' => $this->context->get_site_url(), 'challenge_secret' => $challenge_secret->get()]))->send();
        if (is_wp_error($response)) {
            return $response;
        }
        if (!$response->is_successful()) {
            return $response->get_errors();
        }
        $body = $response->get_body();
        if (empty($body['site_id']) || empty($body['signing_secret']) || empty($body['verification_key'])) {
            return new WP_Error('invalid_response', esc_html__('Invalid response for authentication credentials generation.', 'wpforms-product-api-client'));
        }
        $site_id = $body['site_id'];
        $signing_secret = $body['signing_secret'];
        $verification_key = $body['verification_key'];
        $site_urls = \array_unique(\array_merge($this->auth_options->get('site_urls', []), [$this->clear_site_url($this->context->get_site_url())]));
        $this->auth_options->update(['site_id' => $site_id, 'signing_secret' => $signing_secret, 'verification_key' => $verification_key, 'site_urls' => $site_urls]);
        return ['site_id' => $site_id, 'signing_secret' => $signing_secret, 'verification_key' => $verification_key];
    }
    /**
     * Check if the site URL is registered.
     *
     * @since 1.0.1
     *
     * @param string $site_url The site URL to check.
     *
     * @return bool True if the site URL is registered, false otherwise.
     */
    private function is_site_url_registered($site_url)
    {
        $site_urls = $this->auth_options->get('site_urls', []);
        return \in_array($this->clear_site_url($site_url), $site_urls, \true);
    }
    /**
     * Get site info data.
     *
     * @since 1.0.1
     *
     * @param array $args Optional arguments with 'contact' key containing email, first_name, last_name.
     *
     * @return array
     */
    private function get_site_info(array $args = [])
    {
        $info = ['site_title' => get_bloginfo('name'), 'site_locale' => get_locale(), 'site_timezone' => wp_timezone_string(), 'is_multisite' => is_multisite(), 'environment' => wp_get_environment_type()];
        $contact = $this->resolve_contact_info($args['contact'] ?? []);
        if (!empty($contact['email'])) {
            $info['contact_email'] = $contact['email'];
        }
        if (!empty($contact['first_name'])) {
            $info['contact_first_name'] = $contact['first_name'];
        }
        if (!empty($contact['last_name'])) {
            $info['contact_last_name'] = $contact['last_name'];
        }
        return $info;
    }
    /**
     * Resolve contact info from provided data or current user.
     *
     * @since 1.0.1
     *
     * @param array $contact Provided contact info.
     *
     * @return array Resolved contact info with 'email', 'first_name', 'last_name'.
     */
    private function resolve_contact_info(array $contact)
    {
        $email = $contact['email'] ?? '';
        $first_name = $contact['first_name'] ?? '';
        $last_name = $contact['last_name'] ?? '';
        // If contact info provided, use it.
        if (!empty($email)) {
            return ['email' => $email, 'first_name' => $first_name, 'last_name' => $last_name];
        }
        // Fall back to current user data.
        $current_user = wp_get_current_user();
        if ($current_user->ID === 0) {
            return ['email' => '', 'first_name' => '', 'last_name' => ''];
        }
        return ['email' => $current_user->user_email, 'first_name' => $current_user->first_name, 'last_name' => $current_user->last_name];
    }
    /**
     * Clear site URL.
     *
     * @since 1.0.1
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
