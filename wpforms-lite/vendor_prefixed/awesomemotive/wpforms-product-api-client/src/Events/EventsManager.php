<?php

namespace WPForms\Vendor\ProductApi\Events;

use WPForms\Vendor\ProductApi\Context;
use WPForms\Vendor\ProductApi\Http\Client;
use WPForms\Vendor\ProductApi\Options;
/**
 * Product API Events Manager.
 *
 * @since 1.0.0
 */
class EventsManager
{
    /**
     * Events config instance.
     *
     * @since 1.0.0
     *
     * @var EventsConfig
     */
    private $config;
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
     * Event tracker instance.
     *
     * @since 1.0.0
     *
     * @var EventTracker
     */
    private $tracker;
    /**
     * Client side events instance.
     *
     * @since 1.0.0
     *
     * @var ClientSideEventsProvider
     */
    private $client_side_events_provider;
    /**
     * Constructor.
     *
     * @since 1.0.0
     *
     * @param EventsConfig $config  Events config instance.
     * @param Context      $context Context instance.
     * @param Options      $options Options instance.
     * @param Client       $client  Client instance.
     */
    public function __construct(EventsConfig $config, Context $context, Options $options, Client $client)
    {
        $this->config = $config;
        $this->context = $context;
        $this->options = $options;
        $this->client = $client;
        $auth_strategy = new EventsAuthStrategy($this->context, $this->options, $this->client);
        $this->tracker = new EventTracker($this->context, $this->options, $this->client->auth_strategy($auth_strategy));
        $this->client_side_events_provider = new ClientSideEventsProvider($this->config, $this->context, $this->tracker);
    }
    /**
     * Hooks.
     *
     * @since 1.0.0
     */
    public function hooks()
    {
        $this->client_side_events_provider->hooks();
    }
    /**
     * Get the event tracker instance.
     *
     * @since 1.0.0
     *
     * @return EventTracker
     */
    public function get_tracker()
    {
        return $this->tracker;
    }
}
