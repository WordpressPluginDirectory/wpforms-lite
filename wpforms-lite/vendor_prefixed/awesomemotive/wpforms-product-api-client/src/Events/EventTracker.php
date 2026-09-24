<?php

namespace WPForms\Vendor\ProductApi\Events;

use WPForms\Vendor\ProductApi\Context;
use WPForms\Vendor\ProductApi\Http\Client;
use WPForms\Vendor\ProductApi\Http\Middleware\RateLimitMiddleware;
use WPForms\Vendor\ProductApi\Http\Response;
use WPForms\Vendor\ProductApi\Options;
use WP_Error;
/**
 * Product Events Event Tracker class.
 *
 * @since 1.0.0
 */
class EventTracker
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
     * Client instance.
     *
     * @since 1.0.0
     *
     * @var Client
     */
    private $client;
    /**
     * Options instance.
     *
     * @since 1.0.0
     *
     * @var Options
     */
    private $options;
    /**
     * Request-scoped queue of events to dispatch on shutdown.
     *
     * Static to allow queuing from anywhere during the request lifecycle.
     *
     * @since 1.0.0
     *
     * @var Event[]
     */
    private static $queued_events = [];
    /**
     * Whether shutdown hook has been registered.
     *
     * @since 1.0.0
     *
     * @var bool
     */
    private static $shutdown_registered = \false;
    /**
     * Number of events queued without deduplication.
     *
     * Suffixes the queue key, so an event cannot displace an identical one.
     *
     * @since 1.1.0
     *
     * @var int
     */
    private static $sequence = 0;
    /**
     * Constructor.
     *
     * @param Context $context Context instance.
     * @param Options $options Options instance.
     * @param Client  $client  Client instance.
     *
     * @since 1.0.0
     */
    public function __construct(Context $context, Options $options, Client $client)
    {
        $this->context = $context;
        $this->options = $options;
        $this->client = $client;
    }
    /**
     * Queue an event for deferred dispatch on shutdown.
     *
     * Events are stored in a request-scoped queue and dispatched
     * in bulk when PHP shuts down. Duplicate events (same name,
     * properties, and context) are automatically deduplicated
     * using the event key.
     *
     * @since 1.0.0
     *
     * @param Event $event Event to queue.
     *
     * @return void
     */
    public function track(Event $event)
    {
        self::$queued_events[$event->get_key()] = $event;
        $this->maybe_register_shutdown_hook();
    }
    /**
     * Queue an event without deduplicating it against the ones already queued.
     *
     * track() collapses events that agree on name, properties and context. Two
     * replayed rows are two real occurrences, so they must not collapse.
     *
     * @since 1.1.0
     *
     * @param Event $event Event to queue.
     *
     * @return void
     */
    public function track_each(Event $event)
    {
        self::$queued_events[$event->get_key() . '#' . ++self::$sequence] = $event;
        $this->maybe_register_shutdown_hook();
    }
    /**
     * Register shutdown hook if not already registered.
     *
     * @since 1.0.0
     *
     * @return void
     */
    private function maybe_register_shutdown_hook()
    {
        if (self::$shutdown_registered) {
            return;
        }
        self::$shutdown_registered = \true;
        add_action('shutdown', function () {
            $this->flush();
        });
    }
    /**
     * Flush all queued events.
     *
     * Dispatches all queued events in a single bulk request
     * and clears the queue.
     *
     * @since 1.0.0
     *
     * @return void
     */
    private function flush()
    {
        $this->dispatch(\false, 1);
    }
    /**
     * Send the queued events now and hand the response back.
     *
     * Unlike flush(), this blocks and returns what the API answered, so the caller can
     * read the status code and the body rather than a flattened error. A spent rate
     * limit never reaches the API and comes back as a WP_Error instead.
     *
     * A 2xx means the batch reached the API, not that every event was stored. Failure
     * does not requeue: the caller owns the retry. The queue is shared, so this also
     * sends whatever track() queued earlier.
     *
     * @since 1.1.0
     *
     * @param int $timeout Request timeout in seconds.
     *
     * @return Response|WP_Error|null Null when there was nothing to send.
     */
    public function send_now($timeout = 5)
    {
        return $this->dispatch(\true, $timeout);
    }
    /**
     * Dispatch the queued events.
     *
     * @since 1.1.0
     *
     * @param bool $blocking Whether to wait for the response.
     * @param int  $timeout  Request timeout in seconds.
     *
     * @return Response|WP_Error|null Null when the queue was empty.
     */
    private function dispatch($blocking, $timeout)
    {
        if (empty(self::$queued_events)) {
            return null;
        }
        $events = \array_values(self::$queued_events);
        // Clear queue before dispatch to prevent duplicates.
        self::$queued_events = [];
        $events_data = \array_map([$this, 'prepare_event_data'], $events);
        return $this->client->post('/product-events/v1/collect')->middleware(new RateLimitMiddleware('events_collect', $this->options, 60, HOUR_IN_SECONDS), 0)->json($this->prepare_payload($events_data))->timeout($timeout)->blocking($blocking)->send();
    }
    /**
     * Prepare event data for transmission.
     *
     * @since 1.0.0
     *
     * @param Event $event Event instance.
     *
     * @return array Event data.
     */
    private function prepare_event_data(Event $event)
    {
        $event_data = ['event_name' => $event->get_name(), 'event_context' => $event->get_context(), 'properties' => $event->get_properties(), 'event_time' => $event->get_time(), 'system_info' => $this->get_system_info()];
        if ($event->get_context() === 'user') {
            $event_data['user_info'] = $this->get_user_info();
        }
        return $event_data;
    }
    /**
     * Get system information.
     *
     * @since 1.0.0
     *
     * @return array System information.
     */
    private function get_system_info()
    {
        return ['wp_version' => get_bloginfo('version'), 'plugin_version' => $this->context->get_plugin_version()];
    }
    /**
     * Get user information.
     *
     * @since 1.0.0
     *
     * @return array User information.
     */
    private function get_user_info()
    {
        $user = wp_get_current_user();
        if (empty($user->ID) || empty($user->user_email)) {
            $user = get_user_by('email', get_option('admin_email'));
        }
        $email = !empty($user) && !empty($user->user_email) ? $user->user_email : '';
        $first_name = !empty($user) && !empty($user->first_name) ? $user->first_name : '';
        $last_name = !empty($user) && !empty($user->last_name) ? $user->last_name : '';
        return ['id' => $user->ID, 'email' => $email, 'first_name' => $first_name, 'last_name' => $last_name, 'locale' => get_user_locale()];
    }
    /**
     * Prepare payload for transmission.
     *
     * @since 1.0.0
     *
     * @param array $events Array of event data.
     *
     * @return array Prepared payload.
     */
    private function prepare_payload(array $events)
    {
        return [
            // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode
            'data' => \base64_encode(wp_json_encode(['events' => $events])),
        ];
    }
}
