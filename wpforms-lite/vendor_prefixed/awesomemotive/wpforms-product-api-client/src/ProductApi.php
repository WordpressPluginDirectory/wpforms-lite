<?php

namespace WPForms\Vendor\ProductApi;

use Exception;
use WPForms\Vendor\ProductApi\Http\Client;
use WPForms\Vendor\ProductApi\Auth\AuthManager;
use WPForms\Vendor\ProductApi\Events\EventsConfig;
use WPForms\Vendor\ProductApi\Events\EventsManager;
use WPForms\Vendor\ProductApi\Auth\HMACAuthStrategy;
use WPForms\Vendor\ProductApi\Auth\SiteRegistration;
use WPForms\Vendor\ProductApi\Auth\AuthOptions;
/**
 * Product API.
 *
 * Main entry point for the Product API client library.
 * Provides a facade pattern for easy static access after configuration.
 *
 * @since 1.0.0
 */
final class ProductApi
{
    /**
     * Singleton instance.
     *
     * @since 1.0.0
     *
     * @var self|null
     */
    private static $instance = null;
    /**
     * Initialization arguments.
     *
     * @since 1.0.0
     *
     * @var array
     */
    private $args;
    /**
     * Container instance.
     *
     * @since 1.0.0
     *
     * @var Container
     */
    private $container;
    /**
     * Constructor.
     *
     * @since 1.0.0
     *
     * @param array $args Initialization arguments.
     */
    private function __construct($args)
    {
        $this->args = $args;
        $this->container = new Container();
    }
    /**
     * Configure the Product API client.
     *
     * This is the main entry point. Call this once during plugin initialization.
     *
     * @since 1.0.0
     *
     * @param array $args Configuration arguments. See Context::from_array() for available options.
     *
     * @return self Instance for method chaining.
     */
    public static function configure($args) : self
    {
        if (self::$instance !== null) {
            throw new Exception(esc_html__('ProductApi is already configured.', 'wpforms-product-api-client'));
        }
        self::$instance = new self($args);
        self::$instance->register();
        return self::$instance;
    }
    /**
     * Get the configured instance.
     *
     * @since 1.0.0
     *
     * @throws Exception If ProductApi is not configured.
     * @return self
     *
     */
    public static function instance() : self
    {
        if (self::$instance === null) {
            throw new Exception(esc_html__('ProductApi is not configured. Call ProductApi::configure() first.', 'wpforms-product-api-client'));
        }
        return self::$instance;
    }
    /**
     * Register package classes in the container.
     *
     * @since 1.0.0
     */
    private function register()
    {
        $this->container->singleton(Context::class, function () {
            return Context::from_array($this->args);
        });
        $this->container->singleton(Options::class, function () {
            return new Options($this->resolve(Context::class));
        });
        $this->container->singleton(AuthOptions::class, function () {
            return new AuthOptions($this->resolve(Context::class), $this->resolve(Options::class));
        });
        $this->container->singleton(Client::class, function () {
            $context = $this->resolve(Context::class);
            return new Client($context->get_api_url(), [
                'site_url' => $context->get_site_url(),
                // Read through the context on every request, so a key configured as a
                // callable reaches the header with its current value, not the boot one.
                'license_key' => static function () use($context) {
                    return $context->get_license_key();
                },
                'user_agent' => $context->get_user_agent(),
            ]);
        });
        $this->container->singleton(AuthManager::class, function () {
            return new AuthManager($this->resolve(Context::class), $this->resolve(Options::class));
        });
        $this->container->bind(HMACAuthStrategy::class, function () {
            return new HMACAuthStrategy($this->resolve(Context::class), $this->resolve(AuthOptions::class), $this->resolve(Options::class));
        });
        $this->container->bind(SiteRegistration::class, function () {
            return new SiteRegistration($this->resolve(Context::class), $this->resolve(AuthOptions::class), $this->resolve(Options::class), $this->resolve(Client::class));
        });
    }
    /**
     * Enable plugin events tracking.
     *
     * @since 1.0.0
     *
     * @param array $args Arguments. See EventsConfig for available arguments.
     *
     * @return self Instance for method chaining.
     */
    public function with_events($args = []) : self
    {
        $this->container->singleton(EventsManager::class, function () use($args) {
            return new EventsManager(EventsConfig::from_array($args), $this->resolve(Context::class), $this->resolve(Options::class), $this->resolve(Client::class));
        });
        return $this;
    }
    /**
     * Bootstrap the package.
     *
     * Registers WordPress hooks. Call this after all configuration is complete.
     *
     * @since 1.0.0
     *
     * @return self Instance for method chaining.
     */
    public function boot() : self
    {
        $this->resolve(AuthManager::class)->hooks();
        if ($this->container->has(EventsManager::class)) {
            $this->resolve(EventsManager::class)->hooks();
        }
        return $this;
    }
    /**
     * Resolve an instance of a class from the container.
     *
     * @since 1.0.0
     *
     * @template T
     *
     * @param class-string<T> $class_name The class to retrieve.
     *
     * @return T
     *
     * @throws Exception If the class is not found in the container.
     */
    public function resolve($class_name)
    {
        return $this->container->get($class_name);
    }
    // =========================================================================
    // FACADE STATIC METHODS
    // =========================================================================
    /**
     * Get an instance of a class from the container (static facade).
     *
     * @since 1.0.0
     *
     * @template T
     *
     * @param class-string<T> $class_name The class to retrieve.
     *
     * @return T
     *
     * @throws Exception If the class is not found in the container.
     */
    public static function get($class_name)
    {
        return self::instance()->resolve($class_name);
    }
    /**
     * Get HTTP client instance (static facade).
     *
     * @since 1.0.0
     *
     * @return Client
     */
    public static function client() : Client
    {
        return self::get(Client::class);
    }
}
