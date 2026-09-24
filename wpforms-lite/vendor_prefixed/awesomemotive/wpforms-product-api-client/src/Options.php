<?php

namespace WPForms\Vendor\ProductApi;

use ArrayAccess;
/**
 * Class Options.
 *
 * Plugin options.
 *
 * @since 1.0.0
 */
class Options
{
    /**
     * Keys string separator.
     *
     * @since 1.0.0
     *
     * @var string
     */
    const KEY_SEPARATOR = '.';
    /**
     * Context instance.
     *
     * @since 1.0.0
     *
     * @var Context
     */
    private $context;
    /**
     * Option name.
     *
     * @since 1.0.0
     *
     * @var string
     */
    private $option_name;
    /**
     * Options data.
     *
     * @since 1.0.0
     *
     * @var array
     */
    private $options = null;
    /**
     * Constructor.
     *
     * @since 1.0.0
     *
     * @param Context $context Context instance.
     */
    public function __construct(Context $context)
    {
        $this->context = $context;
        $this->option_name = $this->context->get_plugin_identifier() . '_product_api_options';
    }
    /**
     * Get option by key.
     *
     * @since 1.0.0
     *
     * @param string $key           Option key.
     * @param mixed  $default_value Default value.
     *
     * @return mixed
     */
    public function get($key, $default_value = null)
    {
        return $this->get_value($this->get_options(), $key, $default_value);
    }
    /**
     * Save options to DB.
     *
     * @since 1.0.0
     *
     * @param array $data  Options data.
     * @param bool  $merge Whether to merge with existing options or overwrite all option with provided.
     */
    public function update($data, $merge = \true)
    {
        // Convert dot notation keys to nested array structure.
        $data = $this->convert_dot_notation_to_nested($data);
        if ($merge) {
            $data = $this->merge_options($this->get_options(), $data);
        }
        $this->update_option($data);
    }
    /**
     * Merge options.
     *
     * @since 1.0.0
     *
     * @param array $old_options Old options.
     * @param array $new_options New options.
     *
     * @return array
     */
    private function merge_options($old_options, $new_options)
    {
        $merged = $old_options;
        foreach ($new_options as $key => $value) {
            if (\is_array($value) && isset($merged[$key]) && \is_array($merged[$key]) && !empty($value) && \array_keys($value)[0] !== 0) {
                $merged[$key] = $this->merge_options($merged[$key], $value);
            } else {
                $merged[$key] = $value;
            }
        }
        return $merged;
    }
    /**
     * Get all options.
     *
     * @since 1.0.0
     *
     * @return array
     */
    public function get_options()
    {
        $this->maybe_load_option();
        return $this->options;
    }
    /**
     * Load option.
     *
     * @since 1.0.0
     */
    private function maybe_load_option()
    {
        if ($this->options !== null) {
            return;
        }
        $options = get_option($this->option_name, []);
        $this->options = !empty($options) && \is_array($options) ? $options : [];
    }
    /**
     * Update options.
     *
     * @since 1.0.0
     *
     * @param array $data Options data.
     */
    private function update_option($data)
    {
        update_option($this->option_name, $data, \false);
        $this->options = $data;
    }
    /**
     * Convert dot notation keys to nested array structure.
     *
     * @since 1.0.0
     *
     * @param array $data Data array that may contain dot notation keys.
     *
     * @return array Data with nested array structure.
     */
    private function convert_dot_notation_to_nested($data)
    {
        $result = [];
        foreach ($data as $key => $value) {
            $this->set_value($result, $key, $value);
        }
        return $result;
    }
    /**
     * Get nested array value by string key.
     *
     * @since 1.0.0
     *
     * @param array  $array   Input array.
     * @param string $str_key String key. E.g. "level1.level2.level3".
     * @param mixed  $default The default value that should be returned if value by key not found.
     *
     * @return mixed
     */
    private function get_value($array, $str_key, $default = null)
    {
        // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.arrayFound, Universal.NamingConventions.NoReservedKeywordParameterNames.defaultFound
        if (\is_array($array) && \array_key_exists($str_key, $array)) {
            return $array[$str_key];
        }
        $keys = $this->get_keys_array($str_key);
        foreach ($keys as $key) {
            if (!\is_array($array) && !$array instanceof ArrayAccess) {
                return $default;
            }
            if ($array instanceof ArrayAccess && $array->offsetExists($key) || \array_key_exists($key, $array)) {
                $array = $array[$key];
            } else {
                return $default;
            }
        }
        return $array;
    }
    /**
     * Set value in array using dot notation key.
     *
     * @since 1.0.0
     *
     * @param array  $array   Array to set value in (by reference).
     * @param string $str_key Dot notation key (e.g., "level1.level2.level3").
     * @param mixed  $value   Value to set.
     */
    private function set_value(array &$array, $str_key, $value)
    {
        // phpcs:ignore Universal.NamingConventions.NoReservedKeywordParameterNames.arrayFound
        // Fast path for non-nested key.
        if (\strpos($str_key, self::KEY_SEPARATOR) === \false) {
            $array[$str_key] = $value;
            return;
        }
        $keys = $this->get_keys_array($str_key);
        $tmp =& $array;
        $keys_count = \count($keys);
        while ($keys_count > 0) {
            $key = \array_shift($keys);
            --$keys_count;
            if (!\is_array($tmp)) {
                $tmp = [];
            }
            $tmp =& $tmp[$key];
        }
        $tmp = $value;
    }
    /**
     * Get keys array from keys string.
     *
     * @since 1.0.0
     *
     * @param string $str_key String key. E.g. "level1.level2.level3".
     *
     * @return array
     */
    private function get_keys_array($str_key)
    {
        return \explode(self::KEY_SEPARATOR, $str_key);
    }
    /**
     * Get transient value.
     *
     * @since 1.0.0
     *
     * @param string $key           Transient key.
     * @param mixed  $default_value Default value if transient doesn't exist.
     *
     * @return mixed
     */
    public function get_transient($key, $default_value = \false)
    {
        $transient_key = $this->get_transient_key($key);
        $value = get_transient($transient_key);
        return $value !== \false ? $value : $default_value;
    }
    /**
     * Set transient value.
     *
     * @since 1.0.0
     *
     * @param string $key        Transient key.
     * @param mixed  $value      Value to store.
     * @param int    $expiration Expiration time in seconds. Default: 0 (no expiration).
     *
     * @return bool True if the value was set, false otherwise.
     */
    public function set_transient($key, $value, $expiration = 0)
    {
        $transient_key = $this->get_transient_key($key);
        return set_transient($transient_key, $value, $expiration);
    }
    /**
     * Delete transient.
     *
     * @since 1.0.0
     *
     * @param string $key Transient key.
     *
     * @return bool True if the transient was deleted, false otherwise.
     */
    public function delete_transient($key)
    {
        $transient_key = $this->get_transient_key($key);
        return delete_transient($transient_key);
    }
    /**
     * Get transient key with plugin identifier prefix.
     *
     * @since 1.0.0
     *
     * @param string $key Transient key.
     *
     * @return string
     */
    private function get_transient_key($key)
    {
        return $this->context->get_plugin_identifier() . '_product_api_' . $key;
    }
}
