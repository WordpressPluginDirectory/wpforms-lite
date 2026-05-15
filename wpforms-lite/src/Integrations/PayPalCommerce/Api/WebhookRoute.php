<?php

namespace WPForms\Integrations\PayPalCommerce\Api;

use Exception;
use RuntimeException;
use BadMethodCallException;
use WPForms\Integrations\PayPalCommerce\Api\Webhooks\Exceptions\AmountMismatchException;
use WPForms\Integrations\PayPalCommerce\Connection;
use WPForms\Integrations\PayPalCommerce\Helpers;
use WPForms\Integrations\PayPalCommerce\WebhooksHealthCheck;

/**
 * Webhooks Rest Route handler.
 *
 * @since 1.10.0
 */
class WebhookRoute {

	/**
	 * Event type.
	 *
	 * @since 1.10.0
	 *
	 * @var string
	 */
	private $event_type = 'unknown';

	/**
	 * Raw payload.
	 *
	 * @since 1.10.0
	 *
	 * @var array
	 */
	private $payload = [];

	/**
	 * Response message.
	 *
	 * @since 1.10.0
	 *
	 * @var string
	 */
	private $response = '';

	/**
	 * Response code.
	 *
	 * @since 1.10.0
	 *
	 * @var int
	 */
	private $response_code = 200;

	/**
	 * Initialize.
	 *
	 * @since 1.10.0
	 */
	public function init(): void {

		$this->hooks();
	}

	/**
	 * Register hooks.
	 *
	 * @since 1.10.0
	 */
	private function hooks(): void {

		if ( $this->is_rest_verification() ) {
			add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

			return;
		}

		// Do not serve the regular page when it seems PayPal Webhooks are still sending requests to disabled PHP endpoint.
		// phpcs:disable WordPress.Security.NonceVerification.Recommended
		if (
			isset( $_GET[ Helpers::get_webhook_endpoint_data()['fallback'] ] ) &&
			( ! Helpers::is_webhook_enabled() || $this->is_rest_api_set() )
		) {
			add_action( 'wp', [ $this, 'dispatch_with_error_500' ] );

			return;
		}
		// phpcs:enable WordPress.Security.NonceVerification.Recommended

		// Check if the PayPal connection is configured.
		if ( ! Connection::get() ) {
			return;
		}

		if ( ! Helpers::is_webhook_enabled() || ! $this->is_webhook_configured() ) {
			return;
		}

		if ( $this->is_rest_api_set() ) {
			add_action( 'rest_api_init', [ $this, 'register_rest_routes' ] );

			return;
		}

		add_action( 'wp', [ $this, 'dispatch_with_url_param' ] );
	}

	/**
	 * Register webhook REST route.
	 *
	 * @since 1.10.0
	 */
	public function register_rest_routes(): void {

		$methods = [ 'POST' ];

		if ( $this->is_rest_verification() ) {
			$methods[] = 'GET';
		}

		register_rest_route(
			Helpers::get_webhook_endpoint_data()['namespace'],
			'/' . Helpers::get_webhook_endpoint_data()['route'],
			[
				'methods'             => $methods,
				'callback'            => [ $this, 'dispatch_paypal_webhooks_payload' ],
				'show_in_index'       => false,
				'permission_callback' => '__return_true',
			]
		);
	}

	/**
	 * Dispatch PayPal webhooks payload for the URL param (PHP listener) method.
	 *
	 * @since 1.10.0
	 */
	public function dispatch_with_url_param(): void {

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( ! isset( $_GET[ Helpers::get_webhook_endpoint_data()['fallback'] ] ) ) {
			return;
		}

		$this->dispatch_paypal_webhooks_payload();
	}

	/**
	 * Dispatch PayPal webhooks payload for the URL param with error 500.
	 * Runs when the URL param is not configured or webhooks are not enabled at all.
	 *
	 * @since 1.10.0
	 */
	public function dispatch_with_error_500(): void {

		$this->response      = esc_html__( 'It seems to be request to PayPal PHP Listener method handler but the site is not configured to use it.', 'wpforms-lite' );
		$this->response_code = 500;

		$this->respond();
	}

	/**
	 * Dispatch PayPal webhooks payload.
	 *
	 * @since 1.10.0
	 *
	 * @throws RuntimeException Error in reading and handling the payload.
	 */
	public function dispatch_paypal_webhooks_payload(): void {

		if ( $this->is_rest_verification() ) {
			wp_send_json_success();
		}

		try {
			// Get raw payload.
			$this->payload = file_get_contents( 'php://input' );

			if ( empty( $this->payload ) ) {
				throw new RuntimeException( 'Empty webhook payload.' );
			}

			// Verify the webhook signature before processing.
			if ( ! $this->verify_webhook_signature( $this->payload ) ) {
				throw new RuntimeException( 'Webhook signature verification failed.' );
			}

			$event = json_decode( $this->payload, false );

			$event_whitelist = self::get_webhooks_events_list();

			if ( ! in_array( $event->event_type, $event_whitelist, true ) ) {
				throw new RuntimeException( 'PayPal event type is not whitelisted.' );
			}

			// Update webhook site health status.
			WebhooksHealthCheck::save_status( WebhooksHealthCheck::ENDPOINT_OPTION, WebhooksHealthCheck::STATUS_OK );

			$this->event_type = $event->event_type;
			$this->response   = 'WPForms PayPal: ' . $this->event_type . ' event received.';

			$processed = $this->process_event( $event );

			$this->response_code = $processed ? 200 : 202; // 202 Accepted if unhandled.

			$this->respond();
		} catch ( AmountMismatchException $exception ) {

			$this->response_code = $exception->getCode();
			$this->response      = $exception->getMessage();

			$this->respond();
		} catch ( Exception $e ) {

			$this->response      = $e->getMessage();
			$this->response_code = $e instanceof BadMethodCallException ? 501 : 500;

			$this->respond();
		}
	}

	/**
	 * Retrieve stored webhook ID.
	 *
	 * @since 1.10.0
	 *
	 * @return string
	 */
	private function get_webhook_id(): string {

		$mode = Helpers::get_mode();

		$webhook_id = wpforms_setting( 'paypal-commerce-webhooks-id-' . $mode );

		return is_string( $webhook_id ) ? $webhook_id : '';
	}

	/**
	 * Determine if the REST API is selected in settings.
	 *
	 * @since 1.10.0
	 *
	 * @return bool
	 */
	private function is_rest_api_set(): bool {

		return Helpers::get_webhook_communication() === 'rest';
	}

	/**
	 * Process PayPal event.
	 * Map the event to a handler if it exists. If no handler is registered, return false.
	 *
	 * @since 1.10.0
	 *
	 * @param object $event PayPal event object.
	 *
	 * @return bool True if processed by a handler, false otherwise.
	 */
	private function process_event( object $event ): bool {

		$webhooks = self::get_event_whitelist();

		// Event can't be handled.
		if ( ! isset( $webhooks[ $event->event_type ] ) || ! class_exists( $webhooks[ $event->event_type ] ) ) {
			return false;
		}

		/* @var Webhooks\Base $handler Webhook handler instance. */
		$handler = new $webhooks[ $event->event_type ]();

		$handler->setup( $event );

		return $handler->handle();
	}

	/**
	 * Get event allowlist mapping to handlers (if available).
	 *
	 * @since 1.10.0
	 *
	 * @return array
	 */
	private static function get_event_whitelist(): array {

		// Placeholder for potential future handlers. Keep keys in sync with the registration list.
		return [
			'PAYMENT.CAPTURE.COMPLETED'      => Webhooks\PaymentCaptureCompleted::class,
			'PAYMENT.CAPTURE.DENIED'         => Webhooks\PaymentCaptureDenied::class,
			'PAYMENT.CAPTURE.REFUNDED'       => Webhooks\PaymentCaptureRefunded::class,
			'CHECKOUT.ORDER.COMPLETED'       => Webhooks\CheckoutOrderCompleted::class,
			'BILLING.SUBSCRIPTION.ACTIVATED' => Webhooks\BillingSubscriptionActivated::class,
			'BILLING.SUBSCRIPTION.CANCELLED' => Webhooks\BillingSubscriptionCancelled::class,
			'BILLING.SUBSCRIPTION.SUSPENDED' => Webhooks\BillingSubscriptionSuspended::class,
			'BILLING.SUBSCRIPTION.UPDATED'   => Webhooks\BillingSubscriptionUpdated::class,
			'BILLING.SUBSCRIPTION.EXPIRED'   => Webhooks\BillingSubscriptionExpired::class,
			'PAYMENT.SALE.COMPLETED'         => Webhooks\PaymentSaleCompleted::class,
			'PAYMENT.SALE.REFUNDED'          => Webhooks\PaymentSaleRefunded::class,
		];
	}

	/**
	 * Get a webhook events list (keys of whitelist).
	 *
	 * @since 1.10.0
	 *
	 * @return array
	 */
	public static function get_webhooks_events_list(): array {

		return array_keys( self::get_event_whitelist() );
	}

	/**
	 * Check if REST verification is requested.
	 *
	 * @since 1.10.0
	 *
	 * @return bool
	 */
	private function is_rest_verification(): bool {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		return isset( $_GET['verify'] ) && $_GET['verify'] === '1';
	}

	/**
	 * Respond to the request and exit.
	 *
	 * @since 1.10.0
	 */
	private function respond(): void {

		$this->log_webhook();

		wp_die( esc_html( $this->response ), '', (int) $this->response_code );
	}

	/**
	 * Log webhook request when debugging is enabled.
	 *
	 * @since 1.10.0
	 */
	private function log_webhook(): void {

		// log only if WP_DEBUG_LOG and WPFORMS_WEBHOOKS_DEBUG are set to true.
		if (
			! defined( 'WPFORMS_WEBHOOKS_DEBUG' ) ||
			! WPFORMS_WEBHOOKS_DEBUG ||
			! defined( 'WP_DEBUG_LOG' ) ||
			! WP_DEBUG_LOG
		) {
			return;
		}

		// If it is set to explicitly display logs on output, return: this would make the response malformed.
		if ( defined( 'WP_DEBUG_DISPLAY' ) && WP_DEBUG_DISPLAY ) {
			return;
		}

		$webhook_log = maybe_serialize(
			[
				'event_type'    => $this->event_type,
				'response_code' => $this->response_code,
				'response'      => $this->response,
				'payload'       => $this->payload,
			]
		);

		// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
		error_log( $webhook_log );
	}

	/**
	 * Check if the webhook is configured (webhook ID stored).
	 *
	 * @since 1.10.0
	 *
	 * @return bool
	 */
	private function is_webhook_configured(): bool {

		return $this->get_webhook_id() !== '';
	}

	/**
	 * Verify webhook signature.
	 *
	 * Routes to the appropriate verification method based on connection type:
	 * - Legacy (first-party) connections verify directly with PayPal API via the addon's WebhooksManager.
	 * - Third-party connections verify the HMAC signature added by the Product API.
	 *
	 * @since 1.10.0.5
	 *
	 * @param string $payload Raw webhook payload body.
	 *
	 * @return bool True if signature is valid, false otherwise.
	 */
	private function verify_webhook_signature( string $payload ): bool {

		if ( Helpers::is_legacy() ) {
			// phpcs:ignore WPForms.PHP.BackSlash.UseShortSyntax
			if ( ! method_exists( \WPFormsPaypalCommerce\Api\WebhooksManager::class, 'verify_webhook_signature' ) ) {
				return false;
			}

			// phpcs:ignore WPForms.PHP.BackSlash.UseShortSyntax
			return ( new \WPFormsPaypalCommerce\Api\WebhooksManager() )->verify_webhook_signature( $payload, $this->get_webhook_id() );
		}

		return $this->verify_product_api_webhook_signature( $payload );
	}

	/**
	 * Verify the HMAC-SHA256 signature added by the Product API when forwarding webhooks.
	 *
	 * Checks the X-WPForms-Signature and X-WPForms-Timestamp headers against
	 * the raw payload body using the shared secret established during onboarding.
	 * Rejects payloads older than 5 minutes to prevent replay attacks.
	 *
	 * @since 1.10.0.5
	 *
	 * @param string $payload Raw webhook payload body.
	 *
	 * @return bool True if signature is valid, false otherwise.
	 */
	private function verify_product_api_webhook_signature( string $payload ): bool {

		$signature = isset( $_SERVER['HTTP_X_WPFORMS_SIGNATURE'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WPFORMS_SIGNATURE'] ) ) : '';
		$timestamp = isset( $_SERVER['HTTP_X_WPFORMS_TIMESTAMP'] ) ? sanitize_text_field( wp_unslash( $_SERVER['HTTP_X_WPFORMS_TIMESTAMP'] ) ) : '';

		if ( empty( $signature ) || empty( $timestamp ) ) {
			return false;
		}

		/**
		 * Filter the maximum age (in seconds) for webhook signature timestamps.
		 *
		 * @since 1.10.0.5
		 *
		 * @param int $max_age Maximum allowed age in seconds. Default 300 (5 minutes).
		 */
		$max_age = (int) apply_filters( 'wpforms_integrations_pay_pal_commerce_api_webhook_route_signature_max_age', 5 * MINUTE_IN_SECONDS );

		if ( abs( time() - (int) $timestamp ) > $max_age ) {
			return false;
		}

		$connection = Connection::get();

		if ( ! $connection ) {
			return false;
		}

		$secret   = $connection->get_secret();
		$expected = hash_hmac( 'sha256', $timestamp . '.' . $payload, $secret );

		return hash_equals( $expected, $signature );
	}
}
