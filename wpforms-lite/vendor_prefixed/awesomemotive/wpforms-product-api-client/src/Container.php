<?php

namespace WPForms\Vendor\ProductApi;

use Closure;
use Exception;
/**
 * Container.
 *
 * A dependency injection container that manages class instances and their creation.
 *
 * @since 1.0.0
 */
class Container
{
    /**
     * Class definitions.
     *
     * @since 1.0.0
     *
     * @var array<class-string, Closure>
     */
    private $definitions = [];
    /**
     * Singleton definitions.
     *
     * @since 1.0.0
     *
     * @var array<class-string, Closure>
     */
    private $singletons = [];
    /**
     * Initialized singleton instances.
     *
     * @since 1.0.0
     *
     * @var array<class-string, mixed>
     */
    private $initialized_singletons = [];
    /**
     * Register a singleton class.
     *
     * @since 1.0.0
     *
     * @template T
     *
     * @param class-string<T>  $class_name The class to register.
     * @param null|Closure():T $builder    Optional custom builder function.
     */
    public function singleton($class_name, ?Closure $builder = null) : void
    {
        $this->singletons[$class_name] = $builder ?? function () use($class_name) {
            return new $class_name();
        };
    }
    /**
     * Register a class binding.
     *
     * @since 1.0.0
     *
     * @template T
     *
     * @param class-string<T>  $class_name The class to register.
     * @param null|Closure():T $builder    Optional custom builder function.
     */
    public function bind($class_name, ?Closure $builder = null) : void
    {
        $this->definitions[$class_name] = $builder ?? function () use($class_name) {
            return new $class_name();
        };
    }
    /**
     * Get an instance of a class from the container.
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
    public function get($class_name)
    {
        if (\array_key_exists($class_name, $this->initialized_singletons)) {
            return $this->initialized_singletons[$class_name];
        }
        if (\array_key_exists($class_name, $this->singletons)) {
            // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.Found
            return $this->initialized_singletons[$class_name] = $this->singletons[$class_name]();
        }
        if (\array_key_exists($class_name, $this->definitions)) {
            return $this->definitions[$class_name]();
        }
        // Auto-register as singleton if class exists and can be instantiated.
        try {
            if (\class_exists($class_name)) {
                // phpcs:ignore Squiz.PHP.DisallowMultipleAssignments.Found
                return $this->initialized_singletons[$class_name] = new $class_name();
            }
        } catch (Exception $e) {
            // phpcs:ignore Generic.CodeAnalysis.EmptyStatement.DetectedCatch
            // Do nothing.
        }
        throw new Exception(\sprintf(
            /* translators: %1$s: class name. */
            esc_html__('Class not found in container: %1$s.', 'wpforms-product-api-client'),
            esc_html($class_name)
        ));
    }
    /**
     * Check if a class is registered in the container.
     *
     * @since 1.0.0
     *
     * @param string $class_name The class to check.
     *
     * @return bool
     */
    public function has(string $class_name) : bool
    {
        return \array_key_exists($class_name, $this->definitions) || \array_key_exists($class_name, $this->singletons);
    }
}
