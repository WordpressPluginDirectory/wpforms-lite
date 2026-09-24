<?php

namespace WPForms\Vendor\ProductApi\Events;

/**
 * Product API Events Config.
 *
 * @since 1.0.0
 */
class EventsConfig
{
    /**
     * Capability that allows to log client-side events.
     *
     * @since 1.0.0
     *
     * @var string
     */
    private $log_events_cap;
    /**
     * Create a new events config instance from an array of arguments.
     *
     * @since 1.0.0
     *
     * @param array $args Arguments.
     *
     * @return self
     */
    public static function from_array(array $args)
    {
        $config = new self();
        $config->log_events_cap = $args['log_events_cap'] ?? 'manage_options';
        return $config;
    }
    /**
     * Get the log events capability.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function get_log_events_cap()
    {
        return $this->log_events_cap;
    }
}
