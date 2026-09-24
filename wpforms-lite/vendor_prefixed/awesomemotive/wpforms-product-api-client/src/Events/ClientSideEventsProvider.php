<?php

namespace WPForms\Vendor\ProductApi\Events;

use WPForms\Vendor\ProductApi\Context;
/**
 * Client-side events provider.
 *
 * @since 1.0.0
 */
class ClientSideEventsProvider
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
     * Event tracker instance.
     *
     * @since 1.0.0
     *
     * @var EventTracker
     */
    private $tracker;
    /**
     * AJAX action name for tracking events.
     *
     * @since 1.0.0
     *
     * @var string
     */
    private $ajax_action;
    /**
     * Constructor.
     *
     * @since 1.0.0
     *
     * @param EventsConfig $config  Events config instance.
     * @param Context      $context Context instance.
     * @param EventTracker $tracker Event tracker instance.
     */
    public function __construct(EventsConfig $config, Context $context, EventTracker $tracker)
    {
        $this->config = $config;
        $this->context = $context;
        $this->tracker = $tracker;
        $this->ajax_action = $this->context->get_plugin_identifier() . '_product_events_log';
    }
    /**
     * Hooks.
     *
     * @since 1.0.0
     */
    public function hooks()
    {
        // Enqueue scripts.
        add_action('admin_enqueue_scripts', [$this, 'enqueue_scripts'], 0);
        // AJAX endpoints for JavaScript events.
        add_action('wp_ajax_' . $this->ajax_action, [$this, 'ajax_handle_event']);
    }
    /**
     * Enqueue frontend scripts.
     *
     * @since 1.0.0
     */
    public function enqueue_scripts()
    {
        $handle = $this->context->get_plugin_slug() . '-product-events';
        wp_register_script($handle, '', ['jquery']);
        wp_add_inline_script($handle, $this->get_script());
    }
    /**
     * Handle AJAX event from JavaScript.
     *
     * @since 1.0.0
     */
    public function ajax_handle_event()
    {
        if (!check_ajax_referer($this->ajax_action, \false, \false) || !current_user_can($this->config->get_log_events_cap()) || !isset($_POST['data'])) {
            wp_send_json_error();
        }
        // phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode, WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
        $data = \json_decode(\base64_decode(wp_unslash($_POST['data'])), \true);
        if (empty($data['events']) || !\is_array($data['events'])) {
            wp_send_json_error();
        }
        $events = \array_filter(\array_map([$this, 'create_event_from_data'], $data['events']));
        if (!empty($events)) {
            foreach ($events as $event) {
                $this->tracker->track($event);
            }
        }
        wp_send_json_success();
    }
    /**
     * Create an Event instance from raw event data.
     *
     * @since 1.0.0
     *
     * @param array $event_data Event data array with event_name and properties.
     *
     * @return Event|null Event instance or null if invalid.
     */
    private function create_event_from_data(array $event_data)
    {
        if (empty($event_data['event_name'])) {
            return null;
        }
        $event_name = sanitize_key($event_data['event_name']);
        $event_properties = [];
        foreach ($event_data['properties'] ?? [] as $property_name => $property_value) {
            $event_properties[sanitize_key($property_name)] = sanitize_text_field($property_value);
        }
        return new Event($event_name, $event_properties);
    }
    /**
     * Get the script content.
     *
     * @since 1.0.0
     *
     * @return string
     */
    private function get_script()
    {
        $function_name = $this->context->get_plugin_identifier() . '_log';
        \ob_start();
        // The script tag is for IDE syntax highlighting only and will be stripped on output.
        ?>
		<script>
			(function() {
				var ajaxUrl = '<?php 
        echo esc_url(admin_url('admin-ajax.php'));
        ?>';
				var action = '<?php 
        echo esc_attr($this->ajax_action);
        ?>';
				var nonce = '<?php 
        echo esc_attr(wp_create_nonce($this->ajax_action));
        ?>';

				function safeBase64Encode( str ) {
					return btoa(
						encodeURIComponent( str ).replace(
							/%([0-9A-F]{2})/g,
							function toSolidBytes( match, p1 ) {
								return String.fromCharCode( "0x" + p1 );
							},
						),
					);
				}

				function dispatchEvents( events ) {
					var payload = safeBase64Encode( JSON.stringify( {
						events: events
					} ) );

					try {
						// Use sendBeacon for non-blocking, reliable dispatch.
						if ( 'sendBeacon' in navigator ) {
							var formData = new FormData();
							formData.append( 'action', action );
							formData.append( '_ajax_nonce', nonce );
							formData.append( 'data', payload );

							navigator.sendBeacon( ajaxUrl, formData );
						} else {
							// Fallback to jQuery AJAX for older browsers.
							jQuery.ajax( {
								url: ajaxUrl,
								type: 'POST',
								data: {
									action: action,
									_ajax_nonce: nonce,
									data: payload
								}
							} );
						}
					} catch ( e ) {
					}
				}

				window.<?php 
        echo sanitize_key($function_name);
        ?> = function( eventName, properties ) {
					dispatchEvents( [ {
						event_name: eventName,
						properties: properties
					} ] );
				};
			})();
		</script>
		<?php 
        return \preg_replace('/^\\s*<script>|<\\/script>\\s*$/s', '', \ob_get_clean());
    }
}
