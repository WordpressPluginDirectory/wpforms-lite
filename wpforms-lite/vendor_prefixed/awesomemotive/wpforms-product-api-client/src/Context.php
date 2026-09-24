<?php

namespace WPForms\Vendor\ProductApi;

/**
 * Context class.
 *
 * Contains all the context/configuration data for the package.
 *
 * @since 1.0.0
 */
class Context
{
    /**
     * API base URL.
     *
     * @since 1.0.0
     *
     * @var string
     */
    private $api_url;
    /**
     * Current site URL.
     *
     * @since 1.0.0
     *
     * @var string
     */
    private $site_url;
    /**
     * License key, or a callable resolving it when it is read.
     *
     * @since 1.0.0
     * @since 1.2.0 A callable is accepted.
     *
     * @var string|callable
     */
    private $license_key;
    /**
     * License is valid, or a callable resolving it when it is read.
     *
     * @since 1.0.0
     * @since 1.2.0 A callable is accepted.
     *
     * @var bool|callable
     */
    private $license_valid;
    /**
     * Whether the Pro plugin.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    private $is_pro;
    /**
     * User agent user for the request.
     *
     * @since 1.0.0
     *
     * @var string
     */
    private $user_agent;
    /**
     * Environment.
     *
     * @since 1.0.0
     *
     * @var string
     */
    private $environment;
    /**
     * Plugin slug (e.g., 'wpforms' or 'wp-mail-smtp').
     *
     * @since 1.0.0
     *
     * @var string
     */
    private $plugin_slug;
    /**
     * Plugin version.
     *
     * @since 1.0.0
     *
     * @var string
     */
    private $plugin_version;
    /**
     * Create a new context instance from an array of arguments.
     *
     * @since 1.0.0
     *
     * @param array $args Arguments.
     *
     * @return self
     */
    public static function from_array(array $args)
    {
        // phpcs:ignore Generic.Metrics.CyclomaticComplexity.MaxExceeded, Generic.Metrics.CyclomaticComplexity.TooHigh
        $config = new self();
        $config->api_url = $args['api_url'] ?? '';
        $config->site_url = $args['site_url'] ?? get_site_url();
        // TODO: Stripe protocol and www.
        $config->license_key = $args['license_key'] ?? '';
        $config->license_valid = $args['license_valid'] ?? \false;
        $config->is_pro = $args['is_pro'] ?? \false;
        $config->user_agent = $args['user_agent'] ?? '';
        $config->environment = $args['environment'] ?? 'production';
        $config->plugin_slug = $args['plugin_slug'] ?? '';
        $config->plugin_version = $args['plugin_version'] ?? '';
        return $config;
    }
    /**
     * Get the API base URL.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function get_api_url()
    {
        return $this->api_url;
    }
    /**
     * Get the current site URL.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function get_site_url()
    {
        return $this->site_url;
    }
    /**
     * Get the license key.
     *
     * Resolved on every read when a callable was configured, so a key the plugin saved after
     * `configure()` ran, on the request where the user entered it, is what the request carries.
     *
     * @since 1.0.0
     * @since 1.2.0 Resolves a callable.
     *
     * @return string
     */
    public function get_license_key()
    {
        return (string) $this->resolve($this->license_key);
    }
    /**
     * Check if the license is valid.
     *
     * Resolved on every read when a callable was configured, see `get_license_key()`.
     *
     * @since 1.0.0
     * @since 1.2.0 Resolves a callable.
     *
     * @return bool
     */
    public function is_license_valid()
    {
        return (bool) $this->resolve($this->license_valid);
    }
    /**
     * Resolve a value that may have been configured as a callable.
     *
     * A plain string is never called, even when a function of that name exists, so a license
     * key cannot be mistaken for a function name. A closure or an array callable is.
     *
     * @since 1.2.0
     *
     * @param mixed $value Configured value.
     *
     * @return mixed
     */
    private function resolve($value)
    {
        return !\is_string($value) && \is_callable($value) ? \call_user_func($value) : $value;
    }
    /**
     * Check if the Pro plugin is installed.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public function is_pro()
    {
        return $this->is_pro;
    }
    /**
     * Get the user agent.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function get_user_agent()
    {
        return $this->user_agent;
    }
    /**
     * Get the environment.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function get_environment()
    {
        return $this->environment;
    }
    /**
     * Get the plugin slug.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function get_plugin_slug()
    {
        return $this->plugin_slug;
    }
    /**
     * Get plugin identifier (slug with underscores instead of hyphens).
     *
     * @since 1.0.0
     *
     * @return string Plugin identifier.
     */
    public function get_plugin_identifier()
    {
        return \str_replace('-', '_', $this->plugin_slug);
    }
    /**
     * Get the plugin version.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function get_plugin_version()
    {
        return $this->plugin_version;
    }
}
