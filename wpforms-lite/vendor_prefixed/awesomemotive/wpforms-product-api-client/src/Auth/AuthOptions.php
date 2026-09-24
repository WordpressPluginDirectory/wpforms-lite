<?php

namespace WPForms\Vendor\ProductApi\Auth;

use WPForms\Vendor\ProductApi\Context;
use WPForms\Vendor\ProductApi\Options;
/**
 * Auth Options.
 *
 * Handles environment-scoped auth credential storage.
 * Environment is auto-detected from API URL hostname.
 *
 * @since 1.0.2
 */
class AuthOptions
{
    /**
     * Context instance.
     *
     * @since 1.0.2
     *
     * @var Context
     */
    private $context;
    /**
     * Options instance.
     *
     * @since 1.0.2
     *
     * @var Options
     */
    private $options;
    /**
     * Cached environment value.
     *
     * @since 1.0.2
     *
     * @var string|null
     */
    private $environment = null;
    /**
     * Constructor.
     *
     * @since 1.0.2
     *
     * @param Context $context The context instance.
     * @param Options $options The options instance.
     */
    public function __construct(Context $context, Options $options)
    {
        $this->context = $context;
        $this->options = $options;
    }
    /**
     * Get auth option by key.
     *
     * @since 1.0.2
     *
     * @param string $key           Option key (without 'auth.' prefix).
     * @param mixed  $default_value Default value.
     *
     * @return mixed
     */
    public function get($key, $default_value = null)
    {
        return $this->options->get($this->prefix($key), $default_value);
    }
    /**
     * Update auth options.
     *
     * @since 1.0.2
     *
     * @param array $values Key-value pairs (without 'auth.' prefix).
     */
    public function update(array $values)
    {
        $prefixed = [];
        foreach ($values as $key => $value) {
            $prefixed[$this->prefix($key)] = $value;
        }
        $this->options->update($prefixed);
    }
    /**
     * Get current environment.
     *
     * @since 1.0.2
     *
     * @return string Environment name: 'production', 'staging', or 'local'.
     */
    public function get_environment()
    {
        if ($this->environment === null) {
            $this->environment = $this->detect_environment();
        }
        return $this->environment;
    }
    /**
     * Detect environment from API URL hostname.
     *
     * Rules:
     * - Hostname starts with 'staging.' or 'staging-' → 'staging'
     * - Hostname is 'localhost' or '127.0.0.1' → 'local'
     * - Hostname ends with '.localhost', '.local', '.dev', or '.test' → 'local'
     * - Everything else → 'production'
     *
     * @since 1.0.2
     *
     * @return string Environment name.
     */
    private function detect_environment()
    {
        $host = \parse_url($this->context->get_api_url(), \PHP_URL_HOST);
        if (empty($host)) {
            return 'production';
        }
        // Staging: hostname starts with staging. or staging-.
        if (\preg_match('/^staging[.\\-]/', $host)) {
            return 'staging';
        }
        // Local: localhost, 127.0.0.1, or ends with .localhost, .local, .dev, .test.
        if ($host === 'localhost' || $host === '127.0.0.1' || \preg_match('/\\.(localhost|local|dev|test)$/', $host)) {
            return 'local';
        }
        return 'production';
    }
    /**
     * Prefix key with environment scope.
     *
     * Production keys unchanged for backwards compatibility.
     * Other environments use: environments.{env}.auth.{key}
     *
     * @since 1.0.2
     *
     * @param string $key Option key.
     *
     * @return string Prefixed key.
     */
    private function prefix($key)
    {
        $env = $this->get_environment();
        $auth_key = 'auth.' . $key;
        return $env === 'production' ? $auth_key : 'environments.' . $env . '.' . $auth_key;
    }
}
